<?php

namespace App\Services\Nlp;

/**
 * Transparent word lists used by the baseline NLP engine.
 * These are small hand-curated lists, not a trained model.
 */
final class Lexicon
{
    public const POSITIVE = [
        'good', 'great', 'excellent', 'amazing', 'awesome', 'fantastic', 'love', 'loved', 'loves', 'perfect',
        'perfectly', 'best', 'happy', 'pleased', 'satisfied', 'recommend', 'recommended', 'comfortable',
        'comfy', 'sturdy', 'durable', 'solid', 'reliable', 'fast', 'quick', 'easy', 'simple', 'intuitive',
        'beautiful', 'gorgeous', 'stylish', 'sleek', 'nice', 'impressed', 'impressive', 'smooth', 'responsive',
        'quiet', 'lightweight', 'worth', 'bargain', 'affordable', 'cheap', 'helpful', 'friendly', 'prompt',
        'well', 'brilliant', 'superb', 'wonderful', 'lovely', 'crisp', 'clear', 'bright', 'flawless', 'effective',
        'handy', 'secure', 'snug', 'cozy', 'soft', 'excellent', 'outstanding', 'terrific', 'delighted', 'enjoy',
        'enjoyed', 'improved', 'long-lasting', 'lasts', 'lasted', 'holds', 'worthwhile', 'stellar', 'spotless', 'stable', 'neat', 'quickly', 'clearly',
    ];

    public const NEGATIVE = [
        'bad', 'poor', 'terrible', 'awful', 'horrible', 'worst', 'hate', 'hated', 'disappointed',
        'disappointing', 'disappointment', 'broke', 'broken', 'break', 'breaks', 'cracked', 'defective',
        'faulty', 'useless', 'waste', 'refund', 'return', 'returned', 'uncomfortable', 'flimsy', 'cheaply',
        'fragile', 'slow', 'laggy', 'lag', 'buggy', 'glitchy', 'crash', 'crashes', 'confusing', 'complicated',
        'difficult', 'hard', 'ugly', 'bulky', 'heavy', 'noisy', 'loud', 'overpriced', 'expensive', 'pricey',
        'rude', 'unhelpful', 'ignored', 'late', 'delayed', 'damaged', 'missing', 'wrong', 'tight', 'loose',
        'scratched', 'scratches', 'stopped', 'dies', 'died', 'dead', 'fails', 'failed', 'failure', 'problem',
        'problems', 'issue', 'issues', 'annoying', 'frustrating', 'frustrated', 'mediocre', 'underwhelming',
        'drains', 'drained', 'leaks', 'leaked', 'smells', 'stiff', 'itchy', 'peeling', 'peeled', 'fell',
        'falls', 'worse', 'regret', 'scam', 'garbage', 'junk', 'rattles', 'unreliable', 'dim', 'blurry',
    ];

    public const NEGATIONS = ['not', 'no', 'never', 'cannot', "can't", 'cant', "don't", 'dont', "doesn't",
        'doesnt', "didn't", 'didnt', "isn't", 'isnt', "wasn't", 'wasnt', "won't", 'wont', "wouldn't",
        'wouldnt', 'hardly', 'barely', 'without', "aren't", 'arent', "couldn't", 'couldnt', 'nothing', 'neither'];

    public const INTENSIFIERS = ['very', 'extremely', 'really', 'incredibly', 'super', 'absolutely',
        'totally', 'highly', 'so', 'quite', 'truly', 'insanely'];

    public const CONTRAST = ['but', 'however', 'although', 'though', 'yet', 'except'];

    public const STOPWORDS = [
        'a', 'about', 'above', 'after', 'again', 'all', 'also', 'am', 'an', 'and', 'any', 'are', 'as', 'at',
        'be', 'because', 'been', 'before', 'being', 'below', 'between', 'both', 'by', 'came', 'can', 'come',
        'could', 'did', 'do', 'does', 'doing', 'down', 'during', 'each', 'few', 'for', 'from', 'further',
        'get', 'got', 'had', 'has', 'have', 'having', 'he', 'her', 'here', 'hers', 'him', 'his', 'how', 'i',
        'if', 'in', 'into', 'is', 'it', 'its', 'itself', 'just', 'like', 'made', 'make', 'me', 'more', 'most',
        'my', 'myself', 'now', 'of', 'off', 'on', 'once', 'one', 'only', 'or', 'other', 'our', 'out', 'over',
        'own', 'same', 'she', 'should', 'since', 'some', 'still', 'such', 'than', 'that', 'the', 'their',
        'them', 'then', 'there', 'these', 'they', 'this', 'those', 'through', 'to', 'too', 'under', 'until',
        'up', 'use', 'used', 'using', 'was', 'we', 'were', 'what', 'when', 'where', 'which', 'while', 'who',
        'why', 'will', 'with', 'would', 'you', 'your', 'yours', 'bought', 'buy', 'item', 'product', 'thing',
        'things', 'much', 'many', 'even', 'ever', 'really', 'back', 'well', 'going', 'month', 'months', 'week',
        'weeks', 'day', 'days', 'year', 'years', 'time', 'first', 'second', 'after', 'am', 'pm', 'review',
        'stars', 'star', 'purchase', 'purchased', 'ordered', 'order', 'received', 'little', 'bit', 'lot',
        'daily', 'overall', 'experience', 'opinion', 'honest', 'compared', 'options', 'option', 'life', 'penny', 'pricier', 'walks', 'emails', 'email', 'getting', 'short', 'update', 'mixed', 'take', 'feelings', 'full', 'neat', 'quick', 'take', 'thought', 'think', 'feel', 'feels', 'felt', 'seems', 'seem', 'looks', 'sure', 'right', 'long', 'high', 'low', 'big', 'small', 'old', 'new', 'work', 'need', 'needed', 'want', 'wanted', 'know', 'maybe', 'definitely', 'actually', 'probably', 'especially', 'basically', 'honestly', 'every', 'around', 'another', 'quite', 'though', 'although', 'however', 'but', 'very', 'extremely',
        'something', 'anything', 'everything', 'been', 'ive', "i've", "it's", 'its', 'dont', "don't",
    ];

    /**
     * Predefined aspect catalogue. Terms are matched on whole tokens (or phrases).
     * Products are not forced into this set: unmatched frequent terms are surfaced
     * as "discovered" aspects by the extractor.
     */
    public const ASPECTS = [
        'quality_durability' => [
            'label' => 'Quality & durability',
            'terms' => ['quality', 'durable', 'durability', 'sturdy', 'flimsy', 'broke', 'broken', 'break', 'breaks',
                'cracked', 'crack', 'built', 'build', 'construction', 'material', 'materials', 'fell apart', 'wear',
                'worn', 'lasted', 'lasts', 'long-lasting', 'defective', 'faulty', 'cheaply', 'solid', 'fragile',
                'stopped working', 'peeling', 'peeled', 'scratched', 'scratches', 'rust', 'rusted', 'stitching'],
        ],
        'price_value' => [
            'label' => 'Price & value',
            'terms' => ['price', 'priced', 'value', 'worth', 'cost', 'expensive', 'cheap', 'affordable', 'bargain',
                'overpriced', 'pricey', 'money', 'deal', 'budget', 'refund'],
        ],
        'performance' => [
            'label' => 'Performance',
            'terms' => ['performance', 'fast', 'slow', 'speed', 'powerful', 'power', 'lag', 'laggy', 'responsive',
                'smooth', 'battery', 'charge', 'charging', 'charges', 'drains', 'runtime', 'works', 'working',
                'crash', 'crashes', 'sound', 'audio', 'bass', 'noise', 'noisy', 'quiet', 'signal', 'bluetooth', 'connection',
                'screen', 'display', 'camera'],
        ],
        'ease_of_use' => [
            'label' => 'Ease of use',
            'terms' => ['easy', 'simple', 'intuitive', 'setup', 'set up', 'install', 'installation', 'instructions',
                'manual', 'confusing', 'complicated', 'difficult', 'learning curve', 'user-friendly', 'controls',
                'app', 'interface', 'pairing'],
        ],
        'design_appearance' => [
            'label' => 'Design & appearance',
            'terms' => ['design', 'looks', 'look', 'looking', 'color', 'colour', 'style', 'stylish', 'sleek', 'beautiful',
                'ugly', 'appearance', 'finish', 'elegant', 'compact', 'size', 'bulky', 'lightweight', 'heavy',
                'weight', 'gorgeous'],
        ],
        'comfort_fit' => [
            'label' => 'Comfort & fit',
            'terms' => ['comfortable', 'comfort', 'comfy', 'uncomfortable', 'fit', 'fits', 'fitting', 'tight', 'loose',
                'snug', 'cushion', 'padding', 'ergonomic', 'soft', 'stiff', 'itchy', 'sizing', 'runs small',
                'runs large', 'true to size', 'earbuds', 'ears'],
        ],
        'delivery_packaging' => [
            'label' => 'Delivery & packaging',
            'terms' => ['delivery', 'delivered', 'shipping', 'shipped', 'arrived', 'arrive', 'package', 'packaging',
                'packaged', 'box', 'late', 'delayed', 'courier', 'damaged in transit', 'carrier'],
        ],
        'customer_support' => [
            'label' => 'Customer support',
            'terms' => ['support', 'customer service', 'service', 'warranty', 'replacement', 'replaced', 'seller',
                'representative', 'helpful', 'unhelpful', 'rude', 'responded', 'response', 'contacted', 'refund'],
        ],
    ];
}
