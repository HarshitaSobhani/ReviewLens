<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import Btn from '@/components/Btn.vue';
import Card from '@/components/Card.vue';
import DemoBadge from '@/components/DemoBadge.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import SentimentBar from '@/components/SentimentBar.vue';
import StatTile from '@/components/StatTile.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import ThemeBar from '@/components/ThemeBar.vue';
import { formatDate, formatNumber } from '@/lib/format';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AnalysisSummary } from '@/types';

defineProps<{
    stats: { analyses: number; completed: number; products: number; products_analyzed: number; reviews: number; reviews_processed: number };
    recentAnalyses: AnalysisSummary[];
    recentProducts: { id: number; name: string; category: string | null; brand: string | null; reviews_count: number; is_demo: boolean }[];
    themes: { label: string; mentions: number; positive: number; negative: number; products: number }[];
    hasDemo: boolean;
}>();
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout>
        <PageHeader title="Dashboard" subtitle="Everything below is computed from your stored data.">
            <template #actions>
                <Btn href="/products/create">New product</Btn>
            </template>
        </PageHeader>

        <EmptyState v-if="stats.products === 0" title="Welcome to ReviewLens" description="Create a product and upload a CSV of reviews, or add clearly labelled synthetic demo products to explore the features first.">
            <Btn href="/products/create">Create a product</Btn>
            <Btn variant="secondary" @click="router.post('/demo')">Add demo products</Btn>
        </EmptyState>

        <template v-else>
            <dl class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <StatTile label="Analyses" :value="formatNumber(stats.analyses)" :hint="`${stats.completed} completed`" />
                <StatTile label="Products" :value="formatNumber(stats.products)" />
                <StatTile label="Products analysed" :value="formatNumber(stats.products_analyzed)" />
                <StatTile label="Reviews stored" :value="formatNumber(stats.reviews)" />
                <StatTile label="Reviews processed" :value="formatNumber(stats.reviews_processed)" hint="Latest analysis per product" />
            </dl>

            <div class="mt-6 grid gap-6 lg:grid-cols-3">
                <Card title="Recent analyses" class="lg:col-span-2" :padded="false">
                    <template #actions><Btn href="/analyses" variant="ghost" size="sm">View all</Btn></template>
                    <EmptyState v-if="!recentAnalyses.length" title="No analyses yet" description="Open a product with imported reviews and choose “Run analysis”." />
                    <ul v-else class="divide-y divide-slate-100">
                        <li v-for="a in recentAnalyses" :key="a.id" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <Link :href="`/analyses/${a.id}`" class="font-medium text-slate-900 hover:text-brand-700">{{ a.product_name }}</Link>
                                <p class="text-xs text-slate-500">{{ formatDate(a.created_at) }} · {{ a.review_count }} reviews <DemoBadge v-if="a.includes_demo" /></p>
                            </div>
                            <div class="flex w-full items-center gap-3 sm:w-1/2">
                                <div class="flex-1"><SentimentBar v-if="a.sentiment_distribution && a.status === 'completed'" :distribution="a.sentiment_distribution" compact /></div>
                                <StatusBadge :status="a.status" />
                            </div>
                        </li>
                    </ul>
                </Card>

                <Card title="Recently analysed products" :padded="false">
                    <EmptyState v-if="!recentProducts.length" title="Nothing analysed yet" />
                    <ul v-else class="divide-y divide-slate-100">
                        <li v-for="p in recentProducts" :key="p.id" class="px-5 py-3">
                            <Link :href="`/products/${p.id}`" class="font-medium text-slate-900 hover:text-brand-700">{{ p.name }}</Link>
                            <p class="text-xs text-slate-500">{{ [p.brand, p.category].filter(Boolean).join(' · ') }} · {{ p.reviews_count }} reviews <DemoBadge v-if="p.is_demo" /></p>
                        </li>
                    </ul>
                </Card>
            </div>

            <Card class="mt-6" title="Common themes" subtitle="Across the latest completed analysis of each product">
                <p v-if="!themes.length" class="text-sm text-slate-500">Themes appear after you complete an analysis.</p>
                <ul v-else class="grid gap-4 sm:grid-cols-2">
                    <li v-for="t in themes" :key="t.label">
                        <div class="mb-1 flex justify-between text-sm"><span class="font-medium text-slate-800">{{ t.label }}</span>
                            <span class="tabular-nums text-slate-500">{{ t.mentions }} mentions · {{ t.products }} {{ t.products === 1 ? 'product' : 'products' }}</span></div>
                        <ThemeBar :positive="t.positive" :negative="t.negative" :neutral="Math.max(0, t.mentions - t.positive - t.negative)" />
                    </li>
                </ul>
            </Card>

            <div v-if="!hasDemo" class="mt-6 text-sm text-slate-600">
                Want to explore with sample data? <button type="button" class="font-medium text-brand-700 underline" @click="router.post('/demo')">Add synthetic demo products</button> (clearly labelled, no real customer text).
            </div>
        </template>
    </AppLayout>
</template>
