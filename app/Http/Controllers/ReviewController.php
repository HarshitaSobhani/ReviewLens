<?php

namespace App\Http\Controllers;

use App\Models\AnalysisRun;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function index(Request $request, Product $product): Response
    {
        $this->authorize('view', $product);
        $f = $request->validate([
            'q' => 'nullable|string|max:100', 'rating' => 'nullable|integer|between:1,5',
            'sentiment' => 'nullable|in:positive,negative,neutral,mixed', 'theme' => 'nullable|string|max:80',
            'from' => 'nullable|date', 'to' => 'nullable|date',
            'sort' => 'nullable|in:date_desc,date_asc,rating_desc,rating_asc', 'analysis' => 'nullable|integer',
        ]);

        $analysis = $product->analyses()->where('status', AnalysisRun::STATUS_COMPLETED)
            ->when(! empty($f['analysis']), fn ($q) => $q->whereKey($f['analysis']))
            ->latest('id')->first();

        $query = Review::query()->where('reviews.product_id', $product->id)->where('reviews.user_id', $request->user()->id)
            ->select('reviews.*');

        if ($analysis) {
            $query->leftJoin('analysis_reviews as ar', fn ($j) => $j->on('ar.review_id', '=', 'reviews.id')->where('ar.analysis_run_id', $analysis->id))
                ->addSelect('ar.id as ar_id', 'ar.sentiment', 'ar.confidence', 'ar.sentiment_method');
        }
        if (! empty($f['q'])) {
            $like = '%'.ProductController::escapeLike($f['q']).'%';
            $query->where(fn ($q) => $q->whereRaw("reviews.body like ? escape '!'", [$like])->orWhereRaw("reviews.title like ? escape '!'", [$like]));
        }
        if (! empty($f['rating'])) {
            $query->where('reviews.rating', $f['rating']);
        }
        if (! empty($f['from'])) {
            $query->whereDate('reviews.reviewed_at', '>=', $f['from']);
        }
        if (! empty($f['to'])) {
            $query->whereDate('reviews.reviewed_at', '<=', $f['to']);
        }
        if ($analysis && ! empty($f['sentiment'])) {
            $query->where('ar.sentiment', $f['sentiment']);
        }
        if ($analysis && ! empty($f['theme'])) {
            $theme = $analysis->themes()->where('key', $f['theme'])->first();
            $query->whereIn('ar.id', fn ($q) => $q->select('analysis_review_id')->from('review_themes')->where('theme_id', $theme?->id ?? 0));
        }
        match ($f['sort'] ?? 'date_desc') {
            'date_asc' => $query->orderByRaw('reviews.reviewed_at is null')->orderBy('reviews.reviewed_at'),
            'rating_desc' => $query->orderByRaw('reviews.rating is null')->orderByDesc('reviews.rating'),
            'rating_asc' => $query->orderByRaw('reviews.rating is null')->orderBy('reviews.rating'),
            default => $query->orderByRaw('reviews.reviewed_at is null')->orderByDesc('reviews.reviewed_at'),
        };
        $query->orderBy('reviews.id');

        $reviews = $query->paginate(15)->withQueryString()->through(fn ($r) => [
            'id' => $r->id, 'title' => $r->title, 'body' => $r->body, 'rating' => $r->rating,
            'date' => $r->reviewed_at?->format('Y-m-d'), 'verified' => $r->verified_purchase, 'source' => $r->source,
            'is_demo' => $r->is_demo, 'sentiment' => $r->sentiment ?? null, 'confidence' => $r->confidence ?? null,
        ]);

        return Inertia::render('Reviews/Index', [
            'product' => ['id' => $product->id, 'name' => $product->name],
            'reviews' => $reviews,
            'filters' => $request->only('q', 'rating', 'sentiment', 'theme', 'from', 'to', 'sort'),
            'analysis' => $analysis ? ['id' => $analysis->id, 'created_at' => $analysis->created_at->toIso8601String()] : null,
            'themes' => $analysis ? $analysis->themes()->orderByDesc('mention_count')->get(['key', 'label', 'mention_count']) : [],
        ]);
    }

    public function show(Request $request, Review $review): Response
    {
        $this->authorize('view', $review);
        $review->load('product:id,name', 'import:id,original_filename');

        $analysis = $review->product->analyses()->where('status', AnalysisRun::STATUS_COMPLETED)->latest('id')->first();
        $labels = null;
        $cited = [];
        if ($analysis) {
            $ar = $analysis->analysisReviews()->where('review_id', $review->id)->first();
            if ($ar) {
                $labels = [
                    'analysis_id' => $analysis->id, 'sentiment' => $ar->sentiment, 'method' => $ar->sentiment_method,
                    'confidence' => $ar->confidence, 'conflict' => $ar->rating_text_conflict,
                    'themes' => $ar->themeLinks()->with('theme:id,label')->get()->map(fn ($l) => [
                        'label' => $l->theme->label, 'polarity' => $l->polarity, 'snippet' => $l->snippet,
                    ]),
                ];
            }
            $cited = $analysis->findings()->whereHas('evidence', fn ($q) => $q->where('review_id', $review->id))
                ->get(['id', 'type', 'statement'])->map(fn ($f) => $f->only('id', 'type', 'statement'));
        }

        return Inertia::render('Reviews/Show', [
            'review' => [
                'id' => $review->id, 'title' => $review->title, 'body' => $review->body, 'rating' => $review->rating,
                'date' => $review->reviewed_at?->format('Y-m-d'), 'verified' => $review->verified_purchase,
                'source' => $review->source, 'external_id' => $review->external_id, 'is_demo' => $review->is_demo,
                'import' => $review->import ? ['id' => $review->import->id, 'filename' => $review->import->original_filename] : null,
            ],
            'product' => $review->product->only('id', 'name'),
            'labels' => $labels,
            'citedBy' => $cited,
        ]);
    }
}
