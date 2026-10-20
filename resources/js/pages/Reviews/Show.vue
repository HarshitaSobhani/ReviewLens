<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Badge from '@/components/Badge.vue';
import Card from '@/components/Card.vue';
import ConfidenceBadge from '@/components/ConfidenceBadge.vue';
import DemoBadge from '@/components/DemoBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import SentimentBadge from '@/components/SentimentBadge.vue';
import Stars from '@/components/Stars.vue';
import { methodLabel } from '@/lib/format';
import AppLayout from '@/layouts/AppLayout.vue';

defineProps<{
    review: { id: number; title: string | null; body: string; rating: number | null; date: string | null; verified: boolean | null; source: string | null; external_id: string | null; is_demo: boolean; import: { id: number; filename: string } | null };
    product: { id: number; name: string };
    labels: { analysis_id: number; sentiment: string; method: string; confidence: string; conflict: boolean; themes: { label: string; polarity: string; snippet: string | null }[] } | null;
    citedBy: { id: number; type: string; statement: string }[];
}>();
</script>

<template>
    <Head :title="`Review #${review.id}`" />
    <AppLayout>
        <PageHeader :title="`Review #${review.id}`" :subtitle="product.name">
            <template #crumbs><Link :href="`/products/${product.id}/reviews`" class="hover:underline">← Back to reviews</Link></template>
        </PageHeader>
        <div class="grid gap-6 lg:grid-cols-3">
            <Card class="lg:col-span-2">
                <div class="flex flex-wrap items-center gap-3"><Stars :rating="review.rating" /><DemoBadge v-if="review.is_demo" /></div>
                <h2 v-if="review.title" class="mt-3 text-lg font-semibold text-slate-900">{{ review.title }}</h2>
                <p class="mt-2 whitespace-pre-line text-slate-800">{{ review.body }}</p>
                <dl class="mt-5 grid grid-cols-2 gap-3 border-t border-slate-100 pt-4 text-sm">
                    <div><dt class="text-xs text-slate-500">Date</dt><dd>{{ review.date ?? 'Not provided' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Source</dt><dd>{{ review.source ?? 'Not provided' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Verified purchase</dt><dd>{{ review.verified === null ? 'Unknown' : review.verified ? 'Yes' : 'No' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Source ID</dt><dd>{{ review.external_id ?? '—' }}</dd></div>
                    <div class="col-span-2"><dt class="text-xs text-slate-500">Origin</dt><dd>{{ review.is_demo ? 'Synthetic demonstration text (not a real customer)' : 'Imported' }}<template v-if="review.import"> · <Link :href="`/imports/${review.import.id}`" class="text-brand-700 hover:underline">{{ review.import.filename }}</Link></template></dd></div>
                </dl>
            </Card>
            <div class="space-y-6">
                <Card title="Automated labels" subtitle="Inferred, not authoritative">
                    <template v-if="labels">
                        <div class="flex flex-wrap gap-2"><SentimentBadge :sentiment="labels.sentiment" /><ConfidenceBadge :confidence="labels.confidence" :method="labels.method" /></div>
                        <p class="mt-2 text-xs text-slate-500">Method: {{ methodLabel[labels.method] ?? labels.method }}. From <Link :href="`/analyses/${labels.analysis_id}`" class="text-brand-700 hover:underline">analysis #{{ labels.analysis_id }}</Link>.</p>
                        <Badge v-if="labels.conflict" tone="warn" class="mt-2">Star rating and text disagree</Badge>
                        <h3 class="mt-4 text-sm font-semibold text-slate-900">Themes</h3>
                        <p v-if="!labels.themes.length" class="text-sm text-slate-500">No themes detected.</p>
                        <ul v-else class="mt-1 space-y-2 text-sm">
                            <li v-for="t in labels.themes" :key="t.label"><span class="font-medium">{{ t.label }}</span> <SentimentBadge :sentiment="t.polarity" /><p v-if="t.snippet" class="text-xs text-slate-500">“{{ t.snippet }}”</p></li>
                        </ul>
                    </template>
                    <p v-else class="text-sm text-slate-500">No completed analysis includes this review yet.</p>
                </Card>
                <Card v-if="citedBy.length" title="Cited as evidence">
                    <ul class="space-y-2 text-sm"><li v-for="f in citedBy" :key="f.id"><Badge>{{ f.type }}</Badge> {{ f.statement }}</li></ul>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
