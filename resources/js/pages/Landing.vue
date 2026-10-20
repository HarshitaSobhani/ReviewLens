<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Badge from '@/components/Badge.vue';
import Btn from '@/components/Btn.vue';
import SentimentBar from '@/components/SentimentBar.vue';
import ThemeBar from '@/components/ThemeBar.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

defineProps<{ limits: { max_rows: number; max_file_kb: number } }>();

// Illustrative numbers for the landing page only. They are NOT computed from any data.
const sample = {
    sentiment: { positive: 58, mixed: 12, neutral: 8, negative: 22 },
    themes: [
        { label: 'Battery & performance', positive: 31, negative: 6, neutral: 3 },
        { label: 'Comfort & fit', positive: 18, negative: 14, neutral: 2 },
        { label: 'Quality & durability', positive: 12, negative: 17, neutral: 1 },
    ],
};
const features = [
    ['Evidence for every claim', 'Each advantage, complaint and caveat links to the actual reviews behind it, with verbatim excerpts.'],
    ['Themes, not just stars', 'Durability, price, comfort, delivery, support and product-specific topics, with positive and negative counts.'],
    ['Honest about uncertainty', 'Qualitative confidence labels, rating-vs-text conflicts, and “divided opinion” findings instead of forced verdicts.'],
    ['Side-by-side comparison', 'Compare products with volume and source warnings. ReviewLens never declares a winner from sentiment alone.'],
    ['Explore the raw reviews', 'Search, filter by rating, sentiment, date or theme, and open any review with its source.'],
    ['Saved, reproducible analyses', 'Every run stores its configuration, pipeline version and dataset fingerprint. Re-run without losing old results.'],
];
const steps = ['Create a product', 'Upload a CSV of reviews', 'Preview, map columns, import', 'Run the analysis', 'Read findings and open the evidence'];
</script>

<template>
    <Head title="Review intelligence you can verify" />
    <PublicLayout>
        <section class="bg-gradient-to-b from-brand-50 to-white">
            <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 lg:grid-cols-2 lg:py-20">
                <div>
                    <Badge tone="brand">Review intelligence for shoppers</Badge>
                    <h1 class="mt-4 text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl">Turn hundreds of reviews into conclusions you can check.</h1>
                    <p class="mt-4 text-lg text-slate-600">Upload customer reviews, see recurring benefits and complaints, and click through to the original text behind every statement.</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <Btn href="/register">Start analysing</Btn>
                        <Btn href="/login" variant="secondary">Log in to open the demo</Btn>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">Works without any API key. After sign-up, one click adds clearly labelled synthetic demo products.</p>
                </div>

                <figure class="rounded-2xl border border-slate-200 bg-white p-5 shadow-lg" aria-labelledby="ex-cap">
                    <div class="mb-3 flex items-center justify-between">
                        <figcaption id="ex-cap" class="text-sm font-semibold text-slate-900">Example analysis</figcaption>
                        <Badge tone="warn">Illustrative data, not real reviews</Badge>
                    </div>
                    <p class="mb-1 text-xs font-medium uppercase tracking-wide text-slate-500">Inferred sentiment</p>
                    <SentimentBar :distribution="sample.sentiment" />
                    <p class="mb-2 mt-5 text-xs font-medium uppercase tracking-wide text-slate-500">Themes (positive / neutral / negative mentions)</p>
                    <ul class="space-y-3">
                        <li v-for="t in sample.themes" :key="t.label">
                            <div class="mb-1 flex justify-between text-sm"><span>{{ t.label }}</span><span class="tabular-nums text-slate-500">+{{ t.positive }} / −{{ t.negative }}</span></div>
                            <ThemeBar :positive="t.positive" :negative="t.negative" :neutral="t.neutral" />
                        </li>
                    </ul>
                    <blockquote class="mt-5 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">“Comfort is divided: 18 positive vs 14 negative mentions. No conclusion is drawn.”<footer class="mt-1 text-xs text-slate-500">Example finding wording. Real findings link to real reviews.</footer></blockquote>
                </figure>
            </div>
        </section>

        <section class="mx-auto max-w-6xl px-4 py-14" aria-labelledby="features">
            <h2 id="features" class="text-2xl font-semibold text-slate-900">What you get</h2>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="f in features" :key="f[0]" class="rounded-xl border border-slate-200 p-5">
                    <h3 class="font-semibold text-slate-900">{{ f[0] }}</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ f[1] }}</p>
                </div>
            </div>
        </section>

        <section class="bg-slate-50 py-14" aria-labelledby="flow">
            <div class="mx-auto max-w-6xl px-4">
                <h2 id="flow" class="text-2xl font-semibold text-slate-900">A typical workflow</h2>
                <ol class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <li v-for="(s, i) in steps" :key="s" class="rounded-xl border border-slate-200 bg-white p-4">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white">{{ i + 1 }}</span>
                        <p class="mt-2 text-sm font-medium text-slate-900">{{ s }}</p>
                    </li>
                </ol>
            </div>
        </section>

        <section class="mx-auto max-w-6xl px-4 py-14" aria-labelledby="limits">
            <h2 id="limits" class="text-2xl font-semibold text-slate-900">Data sources and limitations</h2>
            <div class="mt-4 grid gap-6 md:grid-cols-2">
                <div>
                    <h3 class="font-semibold text-slate-900">Where reviews come from</h3>
                    <p class="mt-1 text-sm text-slate-600">CSV upload is the supported way to bring reviews in (up to {{ limits.max_rows.toLocaleString() }} rows and {{ Math.round(limits.max_file_kb / 1024) }} MB per file). ReviewLens does not scrape retailer pages or bypass logins. Export reviews you are entitled to use, or use data from an official API, then upload them.</p>
                </div>
                <div>
                    <h3 class="font-semibold text-slate-900">What the analysis can and cannot do</h3>
                    <ul class="mt-1 list-disc space-y-1 pl-5 text-sm text-slate-600">
                        <li>The default engine uses transparent word lists and your star ratings. It is not a trained model.</li>
                        <li>It can miss sarcasm, context and non-English text.</li>
                        <li>Reviews are self-selected; averages do not equal product quality.</li>
                        <li>An optional AI model adapter exists only when a key is configured by the server operator.</li>
                    </ul>
                </div>
            </div>
            <div class="mt-8"><Btn href="/register">Create a free workspace</Btn></div>
        </section>
    </PublicLayout>
</template>
