<?php

namespace App\Http\Controllers;

use App\Models\AnalysisRun;
use App\Services\Analysis\ComparisonBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ComparisonController extends Controller
{
    public function index(Request $request, ComparisonBuilder $builder): Response
    {
        $data = $request->validate(['analyses' => 'nullable|array|max:5', 'analyses.*' => 'integer']);
        $user = $request->user();

        // Only the latest completed analysis per product is offered for selection.
        $options = $user->analyses()->with('product:id,name')->where('status', AnalysisRun::STATUS_COMPLETED)
            ->latest('id')->get()->unique('product_id')->values()
            ->map(fn ($a) => [
                'id' => $a->id, 'product_name' => $a->product->name, 'review_count' => $a->review_count,
                'created_at' => $a->created_at->toIso8601String(),
            ]);

        $selected = array_values(array_unique($data['analyses'] ?? []));
        $comparison = null;
        $error = null;
        if (count($selected) >= 2) {
            $runs = $user->analyses()->with('product:id,name')->whereIn('id', $selected)
                ->where('status', AnalysisRun::STATUS_COMPLETED)->get();
            if ($runs->count() !== count($selected)) {
                $error = 'One or more selected analyses are unavailable.';
            } else {
                $comparison = $builder->build($runs->sortBy(fn ($r) => array_search($r->id, $selected))->values());
            }
        } elseif (count($selected) === 1) {
            $error = 'Select at least two analyses to compare.';
        }

        return Inertia::render('Compare/Index', [
            'options' => $options, 'selected' => $selected, 'comparison' => $comparison, 'error' => $error,
        ]);
    }
}
