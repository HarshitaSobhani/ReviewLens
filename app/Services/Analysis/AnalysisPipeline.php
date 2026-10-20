<?php

namespace App\Services\Analysis;

use App\Models\AnalysisReview;
use App\Models\AnalysisRun;
use App\Models\Finding;
use App\Models\FindingEvidence;
use App\Models\Review;
use App\Models\ReviewTheme;
use App\Models\Theme;
use App\Services\Nlp\AspectExtractor;
use App\Services\Nlp\FindingBuilder;
use App\Services\Nlp\LexiconSentimentClassifier;
use App\Services\Nlp\ModelAnalysisException;
use App\Services\Nlp\ModelAnalyzer;
use App\Services\Nlp\ReviewResult;
use App\Services\Nlp\StatsCalculator;
use App\Services\Nlp\TextNormalizer;
use App\Services\Nlp\ThemeAggregator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stages: 1 normalize/validate, 2 detect unusable/duplicate, 3 normalize ratings,
 * 4 classify sentiment, 5 extract aspects, 6 aggregate, 7 summarize,
 * 8 link evidence, 9 persist with provenance.
 */
class AnalysisPipeline
{
    public function __construct(
        private readonly TextNormalizer $normalizer,
        private readonly LexiconSentimentClassifier $sentiment,
        private readonly AspectExtractor $aspects,
        private readonly ThemeAggregator $aggregator,
        private readonly StatsCalculator $stats,
        private readonly FindingBuilder $findings,
        private readonly ModelAnalyzer $model,
    ) {}

    /** Atomically claim a pending run. Returns false if another worker already has it. */
    public function claim(AnalysisRun $run): bool
    {
        return AnalysisRun::query()
            ->whereKey($run->getKey())
            ->where('status', AnalysisRun::STATUS_PENDING)
            ->update(['status' => AnalysisRun::STATUS_RUNNING, 'started_at' => now()]) === 1;
    }

    public function run(AnalysisRun $run): void
    {
        if (! $this->claim($run)) {
            return; // already running/finished: prevents duplicate processing
        }
        $run->refresh();

        try {
            $this->execute($run);
        } catch (Throwable $e) {
            Log::warning('Analysis run failed', ['run_id' => $run->id, 'exception' => $e::class]);
            $run->forceFill([
                'status' => AnalysisRun::STATUS_FAILED,
                'error' => Str::limit($e instanceof AnalysisException ? $e->getMessage() : 'Analysis failed unexpectedly. See server logs.', 500),
                'finished_at' => now(),
            ])->save();
        }
    }

    private function execute(AnalysisRun $run): void
    {
        $cfg = $run->config ?? [];
        $maxReviews = (int) config('reviewlens.analysis.max_reviews');
        $useModel = ($cfg['engine'] ?? 'baseline') === 'model';

        // Stages 1-3
        $usable = [];
        $skipped = 0;
        $seen = [];
        $totalSeen = 0;
        $fingerprints = [];
        $demo = false;
        $importIds = [];
        $sources = [];
        $dates = [];
        $verified = 0;
        $verifiedKnown = 0;

        Review::query()->where('product_id', $run->product_id)->where('user_id', $run->user_id)
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use (&$usable, &$skipped, &$seen, &$totalSeen, &$fingerprints, &$demo, &$importIds, &$sources, &$dates, &$verified, &$verifiedKnown, $maxReviews) {
                foreach ($chunk as $review) {
                    $totalSeen++;
                    $body = $this->normalizer->clean($review->body);
                    $fp = $this->normalizer->fingerprint($review->title, $body);
                    if ($this->normalizer->unusableReason($body) !== null || isset($seen[$fp]) || count($usable) >= $maxReviews) {
                        $skipped++;

                        continue;
                    }
                    $seen[$fp] = true;
                    $fingerprints[] = $fp;
                    $usable[$review->id] = ['body' => $body, 'rating' => $this->normalizer->normalizeRating($review->rating)];
                    $demo = $demo || $review->is_demo;
                    if ($review->review_import_id) {
                        $importIds[$review->review_import_id] = true;
                    }
                    if ($review->source) {
                        $sources[$review->source] = true;
                    }
                    if ($review->reviewed_at) {
                        $dates[] = $review->reviewed_at->format('Y-m-d');
                    }
                    if ($review->verified_purchase !== null) {
                        $verifiedKnown++;
                        $verified += $review->verified_purchase ? 1 : 0;
                    }
                }
            });

        if (count($usable) < (int) config('reviewlens.analysis.min_reviews')) {
            throw new AnalysisException($totalSeen === 0
                ? 'This product has no imported reviews to analyze.'
                : 'No usable reviews were found (all were empty, too short, or duplicates).');
        }

        // Stages 4-5
        $results = [];
        foreach ($usable as $id => $r) {
            $c = $this->sentiment->classify($r['body'], $r['rating']);
            $res = new ReviewResult($id, $r['body'], $r['rating'], $c['sentiment'], $c['score'], $c['method'], $c['confidence'], $c['conflict']);
            $res->themes = $this->aspects->extract($r['body']);
            $results[$id] = $res;
        }
        $discovered = $this->aspects->discover(array_map(fn ($r) => $r['body'], $usable));
        foreach ($discovered as $key => $byReview) {
            foreach ($byReview as $reviewId => $theme) {
                $results[$reviewId]->themes[$key] = $theme;
            }
        }

        // Optional model adapter with validated output and fallback
        $ai = ['requested' => $useModel, 'used' => false, 'provider' => null, 'fallback_reason' => null];
        if ($useModel) {
            try {
                $this->applyModel($results, $usable);
                $ai['used'] = true;
                $ai['provider'] = $this->model->name();
            } catch (ModelAnalysisException $e) {
                $ai['fallback_reason'] = Str::limit($e->getMessage(), 160);
                Log::notice('Model analysis fell back to baseline', ['run_id' => $run->id]);
            }
        }

        // Stages 6-8
        $themes = $this->aggregator->aggregate($results, (int) config('reviewlens.analysis.min_theme_mentions'));
        $reviewMeta = [
            'date_range' => $dates ? [min($dates), max($dates)] : null,
            'verified_known' => $verifiedKnown,
            'verified_count' => $verified,
            'sources' => array_keys($sources),
            'skipped' => $skipped,
        ];
        $stats = $this->stats->calculate($results, $reviewMeta);
        $findings = $this->findings->build($results, $themes, $stats, [
            'skipped' => $skipped, 'demo' => $demo, 'ai_fallback' => $ai['fallback_reason'],
        ]);

        $datasetHash = hash('sha256', implode('|', collect($fingerprints)->sort()->values()->all()));

        // Stage 9: persist atomically; never report completion before this commits.
        DB::transaction(function () use ($run, $results, $themes, $findings, $stats, $skipped, $datasetHash, $ai, $demo, $importIds) {
            $arIds = [];
            foreach (array_chunk($results, 200, true) as $chunk) {
                foreach ($chunk as $id => $r) {
                    $ar = (new AnalysisReview)->forceFill([
                        'analysis_run_id' => $run->id, 'review_id' => $id, 'sentiment' => $r->sentiment,
                        'sentiment_score' => $r->score, 'sentiment_method' => $r->method,
                        'confidence' => $r->confidence, 'rating_text_conflict' => $r->conflict,
                    ]);
                    $ar->save();
                    $arIds[$id] = $ar->id;
                }
            }
            $themeIds = [];
            foreach ($themes as $key => $t) {
                $model = (new Theme)->forceFill([
                    'analysis_run_id' => $run->id, 'key' => $key, 'label' => $t['label'],
                    'is_discovered' => $t['is_discovered'], 'mention_count' => $t['mention_count'],
                    'positive_count' => $t['positive_count'], 'negative_count' => $t['negative_count'],
                    'neutral_count' => $t['neutral_count'], 'method' => $t['method'],
                    'top_terms' => $t['top_terms'], 'limitations' => $t['limitations'],
                ]);
                $model->save();
                $themeIds[$key] = $model->id;
            }
            foreach ($results as $id => $r) {
                foreach ($r->themes as $key => $t) {
                    if (! isset($themeIds[$key])) {
                        continue;
                    }
                    (new ReviewTheme)->forceFill([
                        'theme_id' => $themeIds[$key], 'analysis_review_id' => $arIds[$id],
                        'polarity' => $t['polarity'], 'matched_terms' => $t['terms'], 'snippet' => $t['snippet'],
                    ])->save();
                }
            }
            foreach ($findings as $pos => $f) {
                $fm = (new Finding)->forceFill([
                    'analysis_run_id' => $run->id, 'theme_id' => $f['theme_key'] ? ($themeIds[$f['theme_key']] ?? null) : null,
                    'type' => $f['type'], 'statement' => $f['statement'], 'strength' => $f['strength'],
                    'origin' => $f['origin'], 'position' => $pos,
                ]);
                $fm->save();
                $used = [];
                foreach ($f['evidence'] as $e) {
                    if (! isset($arIds[$e['review_id']]) || isset($used[$e['review_id']])) {
                        continue; // never reference a review outside this analysis
                    }
                    $used[$e['review_id']] = true;
                    (new FindingEvidence)->forceFill([
                        'finding_id' => $fm->id, 'review_id' => $e['review_id'], 'excerpt' => mb_substr($e['excerpt'], 0, 500),
                    ])->save();
                }
            }

            $run->forceFill([
                'status' => AnalysisRun::STATUS_COMPLETED,
                'review_count' => count($results),
                'skipped_count' => $skipped,
                'dataset_hash' => $datasetHash,
                'stats' => $stats,
                'provenance' => [
                    'pipeline_version' => config('reviewlens.pipeline_version'),
                    'engine' => $ai['used'] ? 'model+baseline' : 'baseline',
                    'sentiment_method' => 'lexicon (negation, intensifiers) combined with star rating',
                    'aspect_method' => 'predefined aspect lexicon + frequency-based discovery',
                    'ai' => $ai,
                    'includes_demo_data' => $demo,
                    'import_ids' => array_keys($importIds),
                    'reviews_analyzed' => count($results),
                ],
                'finished_at' => now(),
                'error' => null,
            ])->save();
        });
    }

    /**
     * @param  ReviewResult[]  $results
     * @param  array<int,array{body:string,rating:?int}>  $usable
     */
    private function applyModel(array $results, array $usable): void
    {
        if (! $this->model->isConfigured()) {
            throw new ModelAnalysisException('No AI provider key is configured.');
        }
        $sample = array_slice(array_map(fn ($u) => $u['body'], $usable), 0, (int) config('reviewlens.ai.max_reviews'), true);
        $labels = $this->model->analyze($sample);

        foreach ($labels as $id => $l) {
            $r = $results[$id] ?? null;
            if (! $r) {
                continue;
            }
            $r->sentiment = $l['sentiment'];
            $r->method = 'model';
            $r->confidence = $l['confidence'];
            $r->score = ['positive' => 0.8, 'negative' => -0.8, 'neutral' => 0.0, 'mixed' => 0.0][$l['sentiment']];
            $rl = $this->sentiment->labelFromRating($r->rating);
            $r->conflict = in_array($l['sentiment'], ['positive', 'negative'], true)
                && in_array($rl, ['positive', 'negative'], true) && $rl !== $l['sentiment'];
            foreach ($l['themes'] as $t) {
                $key = 'model_'.Str::slug($t['label'], '_');
                $r->themes[$key] = [
                    'label' => Str::title($t['label']), 'polarity' => $t['polarity'], 'terms' => [],
                    'snippet' => $t['quote'] ?? mb_substr($r->body, 0, 240), 'discovered' => true, 'method' => 'model',
                ];
            }
        }
    }
}
