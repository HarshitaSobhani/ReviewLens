<?php

return [
    'pipeline_version' => '1.0.0',

    'import' => [
        'max_file_kb' => (int) env('REVIEWLENS_MAX_UPLOAD_KB', 5120),
        'max_rows' => (int) env('REVIEWLENS_MAX_ROWS', 5000),
        'max_review_chars' => 5000,
        'min_review_chars' => 10,
        'preview_rows' => 10,
        'max_row_errors_stored' => 200,
    ],

    'analysis' => [
        'min_reviews' => 1,
        'max_reviews' => (int) env('REVIEWLENS_MAX_ANALYSIS_REVIEWS', 5000),
        'min_theme_mentions' => 2,
        'discover_min_reviews' => 3,
        'max_evidence_per_finding' => 3,
    ],

    // Optional model-backed adapter. Inactive unless a key is configured.
    'ai' => [
        'provider' => env('REVIEWLENS_AI_PROVIDER', 'anthropic'),
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('REVIEWLENS_AI_MODEL', 'claude-sonnet-5-5'),
        'base_url' => env('REVIEWLENS_AI_BASE_URL', 'https://api.anthropic.com'),
        'timeout' => (int) env('REVIEWLENS_AI_TIMEOUT', 30),
        'max_reviews' => (int) env('REVIEWLENS_AI_MAX_REVIEWS', 60),
    ],

    'limits' => [
        'imports_per_minute' => 10,
        'analyses_per_minute' => 6,
        'exports_per_minute' => 20,
    ],
];
