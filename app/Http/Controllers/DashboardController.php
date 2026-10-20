<?php

namespace App\Http\Controllers;

use App\Models\AnalysisRun;
use App\Models\Review;
use App\Models\Theme;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $completed = $user->analyses()->where('status', AnalysisRun::STATUS_COMPLETED);

        $recent = $user->analyses()->with('product:id,name')->latest('id')->limit(6)->get()
            ->map(fn ($a) => AnalysisController::summary($a));

        $analyzedProducts = $user->analyses()->where('status', AnalysisRun::STATUS_COMPLETED)->distinct()->count('product_id');

        $recentProducts = $user->products()->withCount('reviews')->whereHas('analyses', fn ($q) => $q->where('status', 'completed'))
            ->latest('updated_at')->limit(5)->get(['id', 'name', 'category', 'brand', 'is_demo'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'category' => $p->category, 'brand' => $p->brand,
                'reviews_count' => $p->reviews_count, 'is_demo' => $p->is_demo]);

        // Themes across the latest completed analysis of each product.
        $latestIds = (clone $completed)->selectRaw('max(id) as id')->groupBy('product_id')->pluck('id');
        // 'reviews_processed' counts each product's latest completed analysis once (reanalysis is not double counted).
        $themes = Theme::query()->whereIn('analysis_run_id', $latestIds)
            ->selectRaw('label, sum(mention_count) as mentions, sum(positive_count) as positive, sum(negative_count) as negative, count(*) as products')
            ->groupBy('label')->orderByDesc('mentions')->limit(8)->get()
            ->map(fn ($t) => ['label' => $t->label, 'mentions' => (int) $t->mentions, 'positive' => (int) $t->positive,
                'negative' => (int) $t->negative, 'products' => (int) $t->products]);

        return Inertia::render('Dashboard', [
            'stats' => [
                'analyses' => $user->analyses()->count(),
                'completed' => (clone $completed)->count(),
                'products' => $user->products()->count(),
                'products_analyzed' => $analyzedProducts,
                'reviews' => Review::where('user_id', $user->id)->count(),
                'reviews_processed' => (int) AnalysisRun::whereIn('id', $latestIds)->sum('review_count'),
            ],
            'recentAnalyses' => $recent,
            'recentProducts' => $recentProducts,
            'themes' => $themes,
            'hasDemo' => $user->products()->where('is_demo', true)->exists(),
        ]);
    }
}
