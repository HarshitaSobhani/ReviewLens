<?php

namespace App\Http\Controllers;

use App\Services\Demo\DemoDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DemoController extends Controller
{
    public function store(Request $request, DemoDataService $demo): RedirectResponse
    {
        $demo->seedFor($request->user());

        return redirect()->route('dashboard')->with('success', 'Demo products added. Their reviews are synthetic sample text, not real customer feedback.');
    }
}
