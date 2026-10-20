<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted } from 'vue';
import Badge from '@/components/Badge.vue';
import Btn from '@/components/Btn.vue';
import Card from '@/components/Card.vue';
import ConfirmButton from '@/components/ConfirmButton.vue';
import DemoBadge from '@/components/DemoBadge.vue';
import EvidenceList from '@/components/EvidenceList.vue';
import PageHeader from '@/components/PageHeader.vue';
import RatingBars from '@/components/RatingBars.vue';
import SentimentBadge from '@/components/SentimentBadge.vue';
import SentimentBar from '@/components/SentimentBar.vue';
import Stars from '@/components/Stars.vue';
import StatTile from '@/components/StatTile.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import ThemeBar from '@/components/ThemeBar.vue';
import { formatDate } from '@/lib/format';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AnalysisSummary, Finding, ThemeRow } from '@/types';

interface Detail extends AnalysisSummary {
    product: { id: number; name: string; category: string | null; brand: string | null };
    config: Record<string, unknown> | null;
    provenance: { pipeline_version: string; engine: string; sentiment_method: string; aspect_method: string; ai: { requested: boolean; used: boolean; provider: string | null; fallback_reason: string | null }; includes_demo_data: boolean; import_ids: number[] } | null;
    stats: { rated_count: number; rating_distribution: Record<number, number>; confidence_distribution: Record<string, number>; rating_text_conflicts: number; sources: string[]; date_range: string[] | null; verified_known: number; verified_count: number } | null;
    pipeline_version: string; dataset_hash: string | null; skipped_count: number; previous_run_id: number | null; started_at: string | null;
}
interface Example { review_id: number; confidence: string; method: string; title: string | null; body: string; rating: number | null; date: string | null; is_demo: boolean }

const props = defineProps<{ analysis: Detail; themes?: ThemeRow[]; findings?: Finding[]; examples?: { positive: Example[]; negative: Example[] } }>();

const inProgress = computed(() => ['pending', 'running'].includes(props.analysis.status));
let timer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    timer = setInterval(() => {
        if (inProgress.value) router.reload({ only: ['analysis', 'themes', 'findings', 'examples'] });
    }, 3000);
});
onBeforeUnmount(() => clearInterval(timer));

const groups = computed(() => {
    const f = props.findings ?? [];
    return [
        { key: 'overview', title: 'Overview', items: f.filter((x) => x.type === 'overview') },
        { key: 'advantage', title: 'Frequently praised', items: f.filter((x) => x.type === 'advantage') },
        { key: 'complaint', title: 'Frequent complaints', items: f.filter((x) => x.type === 'complaint') },
        { key: 'mixed', title: 'Divided opinions', items: f.filter((x) => x.type === 'mixed') },
        { key: 'caveat', title: 'Caveats and data quality', items: f.filter((x) => x.type === 'caveat') },
    ].filter((g) => g.items.length);
});
const strengthTone = { strong: 'brand', moderate: 'neutral', weak: 'warn' } as const;
const typeTone: Record<string, string> = { advantage: 'border-l-teal-600', complaint: 'border-l-rose-600', mixed: 'border-l-amber-500', caveat: 'border-l-slate-400', overview: 'border-l-brand-600' };
</script>

<template>
    <Head :title="`Analysis #${analysis.id}`" />
    <AppLayout>
        <PageHeader :title="`${analysis.product.name}`" :subtitle="`Analysis #${analysis.id} · ${formatDate(analysis.created_at)}`">
            <template #crumbs><Link :href="`/products/${analysis.product.id}`" class="hover:underline">← {{ analysis.product.name }}</Link></template>
            <template #actions>
                <StatusBadge :status="analysis.status" />
                <DemoBadge v-if="analysis.includes_demo" />
                <template v-if="analysis.status === 'completed'">
                    <Btn :href="`/products/${analysis.product.id}/reviews`" variant="secondary" size="sm">Explore reviews</Btn>
                    <Btn size="sm" variant="secondary" @click="router.post(`/analyses/${analysis.id}/reanalyze`)">Re-analyse</Btn>
                </template>
                <ConfirmButton label="Delete" title="Delete this analysis?" message="Only this saved result is deleted. Your imported reviews are kept." @confirm="router.delete(`/analyses/${analysis.id}`)" />
            </template>
        </PageHeader>

        <!-- In progress -->
        <Card v-if="inProgress" title="Analysis in progress">
            <p class="text-sm text-slate-600" role="status">
                {{ analysis.status === 'pending' ? 'Queued. Waiting for a queue worker to pick it up.' : 'Running the analysis pipeline…' }}
                This page refreshes automatically; results are only shown once the run has fully finished.
            </p>
            <p v-if="analysis.status === 'pending'" class="mt-2 text-xs text-slate-500">Local setup: run <code class="rounded bg-slate-100 px-1">php artisan queue:work</code> (or use <code class="rounded bg-slate-100 px-1">composer dev</code>).</p>
        </Card>

        <!-- Failed -->
        <Card v-else-if="analysis.status === 'failed'" title="Analysis failed">
            <p class="text-sm text-rose-800" role="alert">{{ analysis.error ?? 'The analysis did not finish.' }}</p>
            <div class="mt-4 flex gap-2"><Btn size="sm" @click="router.post(`/analyses/${analysis.id}/reanalyze`)">Try again</Btn><Btn :href="`/products/${analysis.product.id}`" variant="secondary" size="sm">Back to product</Btn></div>
        </Card>

        <!-- Completed -->
        <template v-else>
            <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatTile label="Reviews analysed" :value="analysis.review_count" :hint="analysis.skipped_count ? `${analysis.skipped_count} skipped` : undefined" />
                <StatTile label="Average star rating" :value="analysis.average_rating ?? '—'" :hint="`from ${analysis.stats?.rated_count ?? 0} rated · customer-provided`" />
                <StatTile label="Rating vs text conflicts" :value="analysis.stats?.rating_text_conflicts ?? 0" hint="Stars and text disagree" />
                <StatTile label="Low-confidence labels" :value="analysis.stats?.confidence_distribution?.low ?? 0" hint="Qualitative, not probabilities" />
            </dl>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <Card title="Inferred sentiment" subtitle="Read from review text (with star rating as a fallback)">
                    <SentimentBar v-if="analysis.sentiment_distribution" :distribution="analysis.sentiment_distribution" />
                </Card>
                <Card title="Star rating distribution" subtitle="Ratings as given by customers">
                    <RatingBars :distribution="analysis.stats?.rating_distribution ?? {}" />
                </Card>
            </div>

            <section class="mt-8" aria-labelledby="findings-h">
                <h2 id="findings-h" class="text-lg font-semibold text-slate-900">Findings and evidence</h2>
                <p class="mb-4 text-sm text-slate-600">Every statement below links to the imported reviews it is based on. Open the excerpts to verify.</p>
                <div v-for="g in groups" :key="g.key" class="mb-6">
                    <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">{{ g.title }}</h3>
                    <ul class="space-y-3">
                        <li v-for="f in g.items" :key="f.id" class="rounded-xl border border-l-4 border-slate-200 bg-white p-4 shadow-sm" :class="typeTone[f.type]">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <p class="text-slate-900">{{ f.statement }}</p>
                                <div class="flex shrink-0 gap-1.5">
                                    <Badge v-if="f.type !== 'caveat'" :tone="strengthTone[f.strength]">{{ f.strength }} evidence</Badge>
                                    <Badge v-if="f.origin === 'model'" tone="brand">AI-generated</Badge>
                                    <Badge v-else tone="neutral">heuristic</Badge>
                                </div>
                            </div>
                            <details v-if="f.evidence.length" class="mt-3">
                                <summary class="cursor-pointer text-sm font-medium text-brand-700">Show {{ f.evidence.length }} supporting {{ f.evidence.length === 1 ? 'review' : 'reviews' }}</summary>
                                <div class="mt-2"><EvidenceList :evidence="f.evidence" /></div>
                            </details>
                        </li>
                    </ul>
                </div>
            </section>

            <Card class="mt-2" title="Themes" subtitle="Aspects mentioned in reviews, with polarity of the sentence mentioning them" :padded="false">
                <p v-if="!themes?.length" class="p-5 text-sm text-slate-500">No theme was mentioned in at least two reviews.</p>
                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="t in themes" :key="t.id" class="grid gap-2 px-5 py-3 sm:grid-cols-12 sm:items-center">
                        <div class="sm:col-span-4">
                            <Link :href="`/products/${analysis.product.id}/reviews?theme=${t.key}`" class="font-medium text-slate-900 hover:text-brand-700">{{ t.label }}</Link>
                            <Badge v-if="t.is_discovered" tone="warn" class="ml-1.5" title="Found by word frequency; may not be a real product aspect">discovered</Badge>
                            <p v-if="t.top_terms?.length" class="text-xs text-slate-500">Terms: {{ t.top_terms.join(', ') }}</p>
                        </div>
                        <div class="sm:col-span-5"><ThemeBar :positive="t.positive" :negative="t.negative" :neutral="t.neutral" /></div>
                        <div class="text-sm tabular-nums text-slate-600 sm:col-span-3 sm:text-right">{{ t.mention_count }} mentions · <span class="text-teal-700">+{{ t.positive }}</span> / <span class="text-rose-700">−{{ t.negative }}</span></div>
                        <ul v-if="t.limitations?.length" class="text-xs text-slate-500 sm:col-span-12"><li v-for="l in t.limitations" :key="l">• {{ l }}</li></ul>
                    </li>
                </ul>
            </Card>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <Card v-for="(list, key) in { positive: examples?.positive ?? [], negative: examples?.negative ?? [] }" :key="key" :title="key === 'positive' ? 'Most positive reviews' : 'Most negative reviews'" subtitle="Strongest text signal, shown in full where short">
                    <p v-if="!list.length" class="text-sm text-slate-500">None found.</p>
                    <ul class="space-y-3">
                        <li v-for="e in list" :key="e.review_id" class="text-sm">
                            <div class="mb-1 flex flex-wrap items-center gap-2"><SentimentBadge :sentiment="key" /><Stars :rating="e.rating" /><DemoBadge v-if="e.is_demo" /></div>
                            <p class="text-slate-800">“{{ e.body }}”</p>
                            <Link :href="`/reviews/${e.review_id}`" class="text-xs font-medium text-brand-700 hover:underline">Review #{{ e.review_id }}</Link>
                        </li>
                    </ul>
                </Card>
            </div>

            <Card class="mt-6" title="Provenance and configuration" subtitle="What produced this result">
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs text-slate-500">Engine</dt><dd>{{ analysis.provenance?.engine }} <span v-if="analysis.provenance?.ai.requested && !analysis.provenance.ai.used" class="text-amber-800">(model requested; fell back: {{ analysis.provenance.ai.fallback_reason }})</span></dd></div>
                    <div><dt class="text-xs text-slate-500">Pipeline version</dt><dd>{{ analysis.pipeline_version }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Sentiment method</dt><dd>{{ analysis.provenance?.sentiment_method }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Theme method</dt><dd>{{ analysis.provenance?.aspect_method }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Sources</dt><dd>{{ analysis.stats?.sources?.join(', ') || 'Not specified' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Review dates</dt><dd>{{ analysis.stats?.date_range ? analysis.stats.date_range.join(' → ') : 'Not provided' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Imports used</dt><dd><template v-for="(id, i) in analysis.provenance?.import_ids ?? []" :key="id"><Link :href="`/imports/${id}`" class="text-brand-700 hover:underline">#{{ id }}</Link><span v-if="i < (analysis.provenance?.import_ids.length ?? 0) - 1">, </span></template><template v-if="!analysis.provenance?.import_ids.length">—</template></dd></div>
                    <div><dt class="text-xs text-slate-500">Dataset fingerprint</dt><dd class="break-all font-mono text-xs">{{ analysis.dataset_hash }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Configuration</dt><dd class="font-mono text-xs">{{ JSON.stringify(analysis.config) }}</dd></div>
                    <div v-if="analysis.previous_run_id"><dt class="text-xs text-slate-500">Re-analysis of</dt><dd><Link :href="`/analyses/${analysis.previous_run_id}`" class="text-brand-700 hover:underline">#{{ analysis.previous_run_id }}</Link></dd></div>
                </dl>
                <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                    <span class="mr-1 self-center text-sm text-slate-600">Export:</span>
                    <Btn external :href="`/analyses/${analysis.id}/export/summary`" variant="secondary" size="sm">Summary CSV</Btn>
                    <Btn external :href="`/analyses/${analysis.id}/export/themes`" variant="secondary" size="sm">Themes CSV</Btn>
                    <Btn external :href="`/analyses/${analysis.id}/export/reviews`" variant="secondary" size="sm">Reviews CSV</Btn>
                    <Btn external :href="`/analyses/${analysis.id}/export/report`" variant="secondary" size="sm">HTML report</Btn>
                </div>
            </Card>
        </template>
    </AppLayout>
</template>
