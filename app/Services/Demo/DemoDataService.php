<?php

namespace App\Services\Demo;

use App\Models\AnalysisRun;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewImport;
use App\Models\User;
use App\Services\Analysis\AnalysisPipeline;
use App\Services\Nlp\TextNormalizer;

/**
 * Creates clearly labelled SYNTHETIC demonstration products and reviews.
 * All text below was written for this project; none of it is a real customer
 * statement and it is never attributed to a real retailer or person.
 */
class DemoDataService
{
    public const SOURCE = 'Synthetic demo data';

    private const POOLS = [
        'audio' => [
            'quality_durability' => [
                'pos' => ['The build feels solid and the hinges have survived daily commuting.', 'Quality is excellent, nothing has creaked or loosened after months of use.', 'The materials feel durable and the cable is well reinforced.'],
                'neg' => ['The headband cracked after two months, which is disappointing.', 'It feels flimsy and the left hinge started to loosen quickly.', 'Build quality is poor and the coating started peeling near the ear cups.'],
            ],
            'price_value' => [
                'pos' => ['For the price this is a great deal.', 'Genuinely worth the money compared with pricier options.', 'It is affordable and punches above its price.'],
                'neg' => ['Overpriced for what you actually get.', 'Too expensive given the problems I had.', 'The value is not there at this price.'],
            ],
            'performance' => [
                'pos' => ['Sound is crisp with punchy bass and the battery lasts for days.', 'The connection is stable and audio quality is fantastic.', 'Battery life is superb and charging is quick.'],
                'neg' => ['The battery drains far too fast and sound cuts out sometimes.', 'Bluetooth connection keeps dropping, which is frustrating.', 'Audio is muddy and the bass sounds distorted.'],
            ],
            'ease_of_use' => [
                'pos' => ['Setup was simple and pairing took seconds.', 'The app is intuitive and controls are easy to learn.', 'Pairing is easy and the instructions were clear.'],
                'neg' => ['The app is confusing and pairing was complicated.', 'Controls are fiddly and the manual is useless.', 'Setup was difficult and instructions were unclear.'],
            ],
            'design_appearance' => [
                'pos' => ['They look sleek and the matte finish is gorgeous.', 'Stylish design and the color options are lovely.', 'Compact, lightweight and elegant looking.'],
                'neg' => ['The design is bulky and looks dated.', 'They look ugly in person and the finish scratches easily.', 'Too heavy and the shape is awkward.'],
            ],
            'comfort_fit' => [
                'pos' => ['Very comfortable even after hours, the padding is soft.', 'The fit is snug and secure while running.', 'Comfort is great and they do not hurt my ears.'],
                'neg' => ['Uncomfortable after an hour, the padding is stiff.', 'The fit is too tight and gives me a headache.', 'They keep falling out and the fit is loose.'],
            ],
            'delivery_packaging' => [
                'pos' => ['Delivery was quick and the packaging was neat.', 'Arrived early in a sturdy box.'],
                'neg' => ['Delivery was late and the box arrived damaged.', 'Shipping was delayed by over a week.'],
            ],
            'customer_support' => [
                'pos' => ['Customer service was helpful and replaced the unit fast.', 'Support responded quickly and was friendly.'],
                'neg' => ['Customer support ignored my emails for weeks.', 'The seller was rude and refused a replacement.'],
            ],
        ],
        'bag' => [
            'quality_durability' => [
                'pos' => ['The stitching is strong and the fabric is durable.', 'Excellent quality, the zippers still work smoothly after a season.', 'Sturdy material that has held up on every trip.'],
                'neg' => ['A strap tore after a month, the stitching is poor.', 'The zipper broke on the second trip.', 'Fabric started peeling and the quality is disappointing.'],
            ],
            'price_value' => [
                'pos' => ['Great value for the price.', 'It is affordable and worth every penny.'],
                'neg' => ['Pricey for a bag that wears out this fast.', 'Not worth the money.'],
            ],
            'performance' => [
                'pos' => ['It holds a laptop and a full day of gear without sagging.', 'Water resistance works well in heavy rain.'],
                'neg' => ['It leaks in the rain and the zipper jams.', 'The bag sags when full and the pockets are too small.'],
            ],
            'ease_of_use' => [
                'pos' => ['Pockets are easy to reach and the layout is simple.', 'Easy to pack and the compartments are intuitive.'],
                'neg' => ['The pockets are confusing to access.', 'Complicated buckles that are difficult to use.'],
            ],
            'design_appearance' => [
                'pos' => ['It looks stylish and the color is beautiful.', 'Compact, sleek design that goes with everything.'],
                'neg' => ['Looks bulky when full.', 'The color faded and it looks ugly now.'],
            ],
            'comfort_fit' => [
                'pos' => ['Shoulder straps are comfortable with soft padding.', 'Comfortable on long walks and the fit is snug on my back.'],
                'neg' => ['Straps dig into my shoulders, uncomfortable on long walks.', 'The padding is stiff and the fit is loose.'],
            ],
            'delivery_packaging' => [
                'pos' => ['Arrived quickly and was well packaged.'],
                'neg' => ['Delivery was late and the package was damaged.'],
            ],
            'customer_support' => [
                'pos' => ['Customer service replaced the strap quickly and was helpful.'],
                'neg' => ['Support was unhelpful and ignored my warranty claim.'],
            ],
        ],
    ];

    private const OPENERS = ['', '', '', 'Used it for a few weeks. ', 'Here is my experience. ', 'Honest opinion: '];

    /** Aspect → probability of a positive mention, per demo product. */
    private const PROFILES = [
        [
            'name' => 'Demo: Nimbus Over-Ear Headphones', 'brand' => 'Demo Brand A', 'category' => 'Headphones', 'price' => 89.00,
            'kind' => 'audio', 'seed' => 11, 'count' => 36,
            'bias' => ['quality_durability' => .45, 'price_value' => .7, 'performance' => .75, 'ease_of_use' => .7, 'design_appearance' => .8, 'comfort_fit' => .8, 'delivery_packaging' => .8, 'customer_support' => .5],
        ],
        [
            'name' => 'Demo: Aero True-Wireless Earbuds', 'brand' => 'Demo Brand B', 'category' => 'Headphones', 'price' => 59.00,
            'kind' => 'audio', 'seed' => 23, 'count' => 28,
            'bias' => ['quality_durability' => .65, 'price_value' => .85, 'performance' => .45, 'ease_of_use' => .6, 'design_appearance' => .7, 'comfort_fit' => .4, 'delivery_packaging' => .7, 'customer_support' => .6],
        ],
        [
            'name' => 'Demo: Trek 24L Daypack', 'brand' => 'Demo Brand C', 'category' => 'Backpacks', 'price' => 45.00,
            'kind' => 'bag', 'seed' => 37, 'count' => 18,
            'bias' => ['quality_durability' => .4, 'price_value' => .6, 'performance' => .6, 'ease_of_use' => .7, 'design_appearance' => .75, 'comfort_fit' => .55, 'delivery_packaging' => .75, 'customer_support' => .5],
        ],
    ];

    public function __construct(private readonly TextNormalizer $normalizer, private readonly AnalysisPipeline $pipeline) {}

    /** Idempotent: does nothing if the user already has the demo products. @return Product[] */
    public function seedFor(User $user, bool $analyze = true): array
    {
        $products = [];
        foreach (self::PROFILES as $profile) {
            $existing = $user->products()->where('is_demo', true)->where('name', $profile['name'])->first();
            if ($existing) {
                $products[] = $existing;

                continue;
            }
            $product = $user->products()->create([
                'name' => $profile['name'], 'brand' => $profile['brand'], 'category' => $profile['category'],
                'price' => $profile['price'],
                'description' => 'Synthetic demonstration product. Its reviews are generated sample text, not real customer feedback.',
            ]);
            $product->forceFill(['is_demo' => true])->save();

            $import = new ReviewImport;
            $import->forceFill([
                'user_id' => $user->id, 'product_id' => $product->id, 'original_filename' => 'synthetic-demo-data',
                'source_name' => self::SOURCE, 'status' => 'completed', 'is_demo' => true, 'completed_at' => now(),
            ])->save();

            $inserted = $this->insertReviews($user, $product, $import, $profile);
            $import->forceFill(['total_rows' => $inserted, 'accepted_rows' => $inserted])->save();
            $products[] = $product;

            if ($analyze) {
                $run = new AnalysisRun;
                $run->forceFill([
                    'user_id' => $user->id, 'product_id' => $product->id, 'status' => AnalysisRun::STATUS_PENDING,
                    'engine' => 'baseline', 'pipeline_version' => config('reviewlens.pipeline_version'),
                    'config' => ['engine' => 'baseline', 'min_theme_mentions' => config('reviewlens.analysis.min_theme_mentions')],
                ])->save();
                $this->pipeline->run($run);
            }
        }

        return $products;
    }

    private function insertReviews(User $user, Product $product, ReviewImport $import, array $profile): int
    {
        mt_srand($profile['seed']);
        $pool = self::POOLS[$profile['kind']];
        $aspects = array_keys($pool);
        $rows = [];
        $seen = [];
        $now = now();
        $attempts = 0;

        while (count($rows) < $profile['count'] && $attempts++ < 500) {
            $take = mt_rand(1, 3);
            $chosen = array_slice($this->shuffle($aspects), 0, $take);
            $parts = [];
            $score = 0;
            // Reviewer mood is correlated across aspects, which yields some clearly positive,
            // clearly negative and genuinely mixed reviews.
            $happy = mt_rand(1, 100) / 100 <= array_sum($profile['bias']) / count($profile['bias']);
            foreach ($chosen as $aspect) {
                $b = $profile['bias'][$aspect];
                $p = $happy ? 0.75 + 0.25 * $b : 0.25 * $b;
                $positive = mt_rand(1, 100) / 100 <= $p;
                $options = $pool[$aspect][$positive ? 'pos' : 'neg'];
                $parts[] = $options[mt_rand(0, count($options) - 1)];
                $score += $positive ? 1 : -1;
            }
            $body = trim(self::OPENERS[mt_rand(0, count(self::OPENERS) - 1)].implode(' ', $parts));
            $rating = max(1, min(5, 3 + (int) round($score * 1.2) + mt_rand(-1, 1) * (mt_rand(0, 4) === 0 ? 1 : 0)));
            // Deliberately keep a few mismatched ratings so the rating/text conflict caveat is demonstrable.
            if (count($rows) % 17 === 16) {
                $rating = 6 - $rating;
            }
            $fp = $this->normalizer->fingerprint(null, $body);
            if (isset($seen[$fp])) {
                continue;
            }
            $seen[$fp] = true;
            $rows[] = [
                'user_id' => $user->id, 'product_id' => $product->id, 'review_import_id' => $import->id,
                'external_id' => 'demo-'.$profile['seed'].'-'.(count($rows) + 1),
                'fingerprint' => $fp, 'title' => null, 'body' => $body, 'rating' => $rating,
                'reviewed_at' => now()->subDays(mt_rand(5, 400))->format('Y-m-d'),
                'verified_purchase' => null, 'source' => self::SOURCE, 'is_demo' => true,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        Review::query()->insert($rows);

        return count($rows);
    }

    /** Deterministic shuffle using the seeded generator. */
    private function shuffle(array $a): array
    {
        for ($i = count($a) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$a[$i], $a[$j]] = [$a[$j], $a[$i]];
        }

        return $a;
    }
}
