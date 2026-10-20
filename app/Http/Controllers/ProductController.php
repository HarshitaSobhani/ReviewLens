<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public static function escapeLike(string $v): string
    {
        return preg_replace('/([!%_])/', '!$1', $v);
    }

    public function index(Request $request): Response
    {
        $request->validate(['q' => 'nullable|string|max:100', 'category' => 'nullable|string|max:80']);
        $user = $request->user();

        $products = $user->products()
            ->withCount('reviews')
            ->when($request->filled('q'), function ($q) use ($request) {
                $like = '%'.self::escapeLike($request->string('q')).'%';
                $q->where(fn ($q) => $q->whereRaw("name like ? escape '!'", [$like])->orWhereRaw("brand like ? escape '!'", [$like]));
            })
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->latest('id')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Product $p) => $this->summary($p));

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => $request->only('q', 'category'),
            'categories' => $user->products()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Products/Form', ['product' => null]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = $request->user()->products()->create($request->validated());

        return redirect()->route('products.show', $product)->with('success', 'Product created. Import reviews to start analyzing.');
    }

    public function show(Request $request, Product $product): Response
    {
        $this->authorize('view', $product);

        $imports = $product->imports()->where('status', 'completed')->latest('id')->limit(10)->get()
            ->map(fn ($i) => [
                'id' => $i->id, 'filename' => $i->original_filename, 'accepted' => $i->accepted_rows,
                'rejected' => $i->rejected_rows, 'duplicates' => $i->duplicate_rows, 'total' => $i->total_rows,
                'is_demo' => $i->is_demo, 'completed_at' => $i->completed_at?->toIso8601String(),
            ]);

        $rating = $product->reviews()->selectRaw('count(*) as c, avg(rating) as a, sum(case when is_demo then 1 else 0 end) as d')->first();
        $analyses = $product->analyses()->latest('id')->limit(10)->get()->map(fn ($a) => AnalysisController::summary($a));

        return Inertia::render('Products/Show', [
            'product' => $this->summary($product) + ['description' => $product->description],
            'reviewStats' => [
                'count' => (int) $rating->c,
                'average_rating' => $rating->a !== null ? round((float) $rating->a, 2) : null,
                'demo_count' => (int) $rating->d,
            ],
            'imports' => $imports,
            'analyses' => $analyses,
            'hasActiveAnalysis' => $product->analyses()->whereIn('status', ['pending', 'running'])->exists(),
            'aiConfigured' => (string) config('reviewlens.ai.api_key') !== '',
        ]);
    }

    public function edit(Product $product): Response
    {
        $this->authorize('update', $product);

        return Inertia::render('Products/Form', ['product' => $this->summary($product) + ['description' => $product->description]]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);
        $product->update($request->validated());

        return redirect()->route('products.show', $product)->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);
        $imports = $product->imports()->whereNotNull('stored_path')->get();
        foreach ($imports as $i) {
            app(\App\Services\Import\CsvImportService::class)->discardFile($i);
        }
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product and all its reviews and analyses were deleted.');
    }

    private function summary(Product $p): array
    {
        return [
            'id' => $p->id, 'name' => $p->name, 'category' => $p->category, 'brand' => $p->brand,
            'price' => $p->price, 'image_url' => $p->image_url, 'is_demo' => $p->is_demo,
            'reviews_count' => $p->reviews_count ?? $p->reviews()->count(),
        ];
    }
}
