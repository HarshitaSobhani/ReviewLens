<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import Btn from '@/components/Btn.vue';
import DemoBadge from '@/components/DemoBadge.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import SentimentBar from '@/components/SentimentBar.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { formatDate } from '@/lib/format';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AnalysisSummary, Paginated } from '@/types';

defineProps<{ analyses: Paginated<AnalysisSummary>; filters: { status?: string } }>();
const setStatus = (e: Event) => {
    const v = (e.target as HTMLSelectElement).value;
    router.get('/analyses', v ? { status: v } : {}, { preserveState: true });
};
</script>

<template>
    <Head title="Saved analyses" />
    <AppLayout>
        <PageHeader title="Saved analyses" subtitle="Reopen, compare, re-run or delete past results.">
            <template #actions>
                <label class="sr-only" for="status">Filter by status</label>
                <select id="status" :value="filters.status ?? ''" class="rounded-lg border-0 px-3 py-2 text-sm ring-1 ring-inset ring-slate-300" @change="setStatus">
                    <option value="">All statuses</option><option value="completed">Completed</option><option value="running">In progress</option><option value="pending">Queued</option><option value="failed">Failed</option>
                </select>
                <Btn href="/compare" variant="secondary">Compare</Btn>
            </template>
        </PageHeader>

        <EmptyState v-if="!analyses.data.length" title="No analyses" description="Run an analysis from a product page once reviews are imported.">
            <Btn href="/products">Go to products</Btn>
        </EmptyState>
        <div v-else class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <ul class="divide-y divide-slate-100">
                <li v-for="a in analyses.data" :key="a.id" class="grid items-center gap-3 px-5 py-3 sm:grid-cols-12">
                    <div class="sm:col-span-4">
                        <Link :href="`/analyses/${a.id}`" class="font-medium text-slate-900 hover:text-brand-700">{{ a.product_name }} <span class="text-slate-400">#{{ a.id }}</span></Link>
                        <p class="text-xs text-slate-500">{{ formatDate(a.created_at) }} · {{ a.review_count }} reviews <DemoBadge v-if="a.includes_demo" /></p>
                    </div>
                    <div class="sm:col-span-5"><SentimentBar v-if="a.sentiment_distribution && a.status === 'completed'" :distribution="a.sentiment_distribution" compact />
                        <p v-else-if="a.error" class="text-xs text-rose-700">{{ a.error }}</p></div>
                    <div class="flex items-center justify-between gap-2 sm:col-span-3 sm:justify-end"><span v-if="a.average_rating" class="text-sm tabular-nums text-slate-600">{{ a.average_rating }} ★ avg</span><StatusBadge :status="a.status" /></div>
                </li>
            </ul>
        </div>
        <Pagination :page="analyses" />
    </AppLayout>
</template>
