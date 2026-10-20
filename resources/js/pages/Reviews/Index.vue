<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import Btn from '@/components/Btn.vue';
import Card from '@/components/Card.vue';
import ConfidenceBadge from '@/components/ConfidenceBadge.vue';
import DemoBadge from '@/components/DemoBadge.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import SentimentBadge from '@/components/SentimentBadge.vue';
import Stars from '@/components/Stars.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Paginated } from '@/types';

interface Row { id: number; title: string | null; body: string; rating: number | null; date: string | null; verified: boolean | null; source: string | null; is_demo: boolean; sentiment: string | null; confidence: string | null }
const props = defineProps<{
    product: { id: number; name: string }; reviews: Paginated<Row>;
    filters: Record<string, string | undefined>;
    analysis: { id: number; created_at: string } | null;
    themes: { key: string; label: string; mention_count: number }[];
}>();

const f = reactive({
    q: props.filters.q ?? '', rating: props.filters.rating ?? '', sentiment: props.filters.sentiment ?? '', theme: props.filters.theme ?? '',
    from: props.filters.from ?? '', to: props.filters.to ?? '', sort: props.filters.sort ?? 'date_desc',
});
function apply() {
    const params = Object.fromEntries(Object.entries(f).filter(([k, v]) => v !== '' && !(k === 'sort' && v === 'date_desc')));
    router.get(`/products/${props.product.id}/reviews`, params, { preserveState: true, replace: true });
}
function reset() {
    Object.assign(f, { q: '', rating: '', sentiment: '', theme: '', from: '', to: '', sort: 'date_desc' });
    apply();
}
const input = 'block w-full rounded-lg border-0 px-3 py-2 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-600';
const lbl = 'mb-1 block text-xs font-medium text-slate-600';
</script>

<template>
    <Head :title="`Reviews · ${product.name}`" />
    <AppLayout>
        <PageHeader title="Review explorer" :subtitle="product.name">
            <template #crumbs><Link :href="`/products/${product.id}`" class="hover:underline">{{ product.name }}</Link></template>
            <template #actions><Btn v-if="analysis" :href="`/analyses/${analysis.id}`" variant="secondary" size="sm">Open analysis #{{ analysis.id }}</Btn></template>
        </PageHeader>

        <Card class="mb-5">
            <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" role="search" @submit.prevent="apply">
                <div class="sm:col-span-2"><label for="q" :class="lbl">Search text</label><input id="q" v-model="f.q" type="search" maxlength="100" :class="input" placeholder="e.g. battery, refund" /></div>
                <div><label for="rating" :class="lbl">Star rating</label>
                    <select id="rating" v-model="f.rating" :class="input"><option value="">Any</option><option v-for="n in [5, 4, 3, 2, 1]" :key="n" :value="String(n)">{{ n }} stars</option></select></div>
                <div><label for="sort" :class="lbl">Sort</label>
                    <select id="sort" v-model="f.sort" :class="input"><option value="date_desc">Newest first</option><option value="date_asc">Oldest first</option><option value="rating_desc">Highest rating</option><option value="rating_asc">Lowest rating</option></select></div>
                <div><label for="sentiment" :class="lbl">Inferred sentiment</label>
                    <select id="sentiment" v-model="f.sentiment" :class="input" :disabled="!analysis"><option value="">Any</option><option value="positive">Positive</option><option value="negative">Negative</option><option value="mixed">Mixed</option><option value="neutral">Neutral</option></select></div>
                <div><label for="theme" :class="lbl">Theme</label>
                    <select id="theme" v-model="f.theme" :class="input" :disabled="!analysis"><option value="">Any</option><option v-for="t in themes" :key="t.key" :value="t.key">{{ t.label }} ({{ t.mention_count }})</option></select></div>
                <div><label for="from" :class="lbl">From date</label><input id="from" v-model="f.from" type="date" :class="input" /></div>
                <div><label for="to" :class="lbl">To date</label><input id="to" v-model="f.to" type="date" :class="input" /></div>
                <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4"><Btn type="submit">Apply filters</Btn><Btn variant="ghost" @click="reset">Reset</Btn>
                    <span v-if="!analysis" class="text-xs text-slate-500">Run an analysis to filter by sentiment and theme.</span></div>
            </form>
        </Card>

        <p class="mb-3 text-sm text-slate-600" aria-live="polite">{{ reviews.total }} {{ reviews.total === 1 ? 'review' : 'reviews' }}</p>
        <EmptyState v-if="!reviews.data.length" title="No reviews match" description="Adjust or reset the filters." />
        <ul v-else class="space-y-3">
            <li v-for="r in reviews.data" :key="r.id" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                    <Stars :rating="r.rating" />
                    <SentimentBadge :sentiment="r.sentiment" />
                    <ConfidenceBadge :confidence="r.confidence" />
                    <DemoBadge v-if="r.is_demo" />
                    <span class="text-xs text-slate-500">{{ r.date ?? 'No date' }}<template v-if="r.source"> · {{ r.source }}</template><template v-if="r.verified"> · Verified purchase</template></span>
                </div>
                <h3 v-if="r.title" class="mt-2 text-sm font-semibold text-slate-900">{{ r.title }}</h3>
                <p class="mt-1 line-clamp-4 text-sm text-slate-700">{{ r.body }}</p>
                <Link :href="`/reviews/${r.id}`" class="mt-2 inline-block text-xs font-medium text-brand-700 hover:underline">Review #{{ r.id }} details</Link>
            </li>
        </ul>
        <Pagination :page="reviews" />
    </AppLayout>
</template>
