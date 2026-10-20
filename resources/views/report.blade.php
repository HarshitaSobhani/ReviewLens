<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ReviewLens report: {{ $run->product->name }}</title>
<style>
body{font-family:system-ui,sans-serif;max-width:860px;margin:2rem auto;padding:0 1rem;color:#0f172a;line-height:1.5}
h1{font-size:1.6rem}h2{margin-top:2rem;font-size:1.2rem;border-bottom:1px solid #e2e8f0;padding-bottom:.3rem}
table{border-collapse:collapse;width:100%;font-size:.9rem}th,td{border:1px solid #e2e8f0;padding:.4rem .6rem;text-align:left}
.tag{display:inline-block;font-size:.75rem;background:#eef2ff;color:#3730a3;border-radius:4px;padding:0 .4rem}
blockquote{margin:.4rem 0 .4rem 1rem;color:#334155;border-left:3px solid #c7d2fe;padding-left:.7rem;font-size:.9rem}
.note{background:#fffbeb;border:1px solid #fde68a;padding:.6rem .8rem;border-radius:6px;font-size:.9rem}
</style>
</head>
<body>
<h1>{{ $run->product->name }}: review analysis</h1>
<p>Analysis #{{ $run->id }} · {{ $run->review_count }} reviews · pipeline {{ $run->pipeline_version }} ·
engine: {{ $run->provenance['engine'] ?? 'baseline' }} · completed {{ $run->finished_at?->toDateTimeString() }} UTC</p>
@if($run->provenance['includes_demo_data'] ?? false)
<p class="note"><strong>Demonstration data.</strong> This analysis includes synthetic reviews, not genuine customer feedback.</p>
@endif
<p class="note">Sentiment is inferred from review text (and star ratings where present) by a transparent word-list method unless stated otherwise. Confidence labels are qualitative, not probabilities.</p>

<h2>Statistics</h2>
@php $s = $run->stats; @endphp
<table>
<tr><th>Average star rating</th><td>{{ $s['average_rating'] ?? 'n/a' }} ({{ $s['rated_count'] ?? 0 }} rated)</td></tr>
@foreach(($s['sentiment_distribution'] ?? []) as $k => $v)<tr><th>{{ ucfirst($k) }} (inferred)</th><td>{{ $v }}</td></tr>@endforeach
@foreach(($s['rating_distribution'] ?? []) as $k => $v)<tr><th>{{ $k }}-star reviews</th><td>{{ $v }}</td></tr>@endforeach
</table>

<h2>Findings</h2>
@foreach($findings as $f)
<p><span class="tag">{{ $f->type }}</span> <span class="tag">{{ $f->strength }}</span> {{ $f->statement }}</p>
@foreach($f->evidence as $e)
<blockquote>Review #{{ $e->review_id }}{{ $e->review?->rating ? ' · '.$e->review->rating.' stars' : '' }}{{ $e->review?->is_demo ? ' · demo data' : '' }}: “{{ $e->excerpt }}”</blockquote>
@endforeach
@endforeach

<h2>Themes</h2>
<table><tr><th>Theme</th><th>Mentions</th><th>Positive</th><th>Negative</th><th>Neutral/mixed</th></tr>
@foreach($themes as $t)<tr><td>{{ $t->label }}{{ $t->is_discovered ? ' (discovered)' : '' }}</td><td>{{ $t->mention_count }}</td><td>{{ $t->positive_count }}</td><td>{{ $t->negative_count }}</td><td>{{ $t->neutral_count }}</td></tr>@endforeach
</table>
<p style="font-size:.8rem;color:#64748b;margin-top:2rem">Dataset fingerprint: {{ $run->dataset_hash }}</p>
</body>
</html>
