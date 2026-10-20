<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function landing(): Response
    {
        return Inertia::render('Landing', [
            'limits' => [
                'max_rows' => config('reviewlens.import.max_rows'),
                'max_file_kb' => config('reviewlens.import.max_file_kb'),
            ],
        ]);
    }
}
