<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Btn from '@/components/Btn.vue';
import Card from '@/components/Card.vue';
import ConfirmButton from '@/components/ConfirmButton.vue';
import DemoBadge from '@/components/DemoBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import SentimentBar from '@/components/SentimentBar.vue';
import StatTile from '@/components/StatTile.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { formatDate } from '@/lib/format';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AnalysisSummary, ProductSummary } from '@/types';

const props = defineProps<{
    product: ProductSummary;
    reviewStats: { count: number; average_rating: number | null; demo_count: number };
    imports: { id: number; filename: string; accepted: number; rejected: number; duplicates: number; total: number; is_demo: boolean; completed_at: string | null }[];
    analyses: AnalysisSummary[];
    hasActiveAnalysis: boolean;
    aiConfigured: boolean;
}>();

const form = useForm({ engine: 'baseline' });
const run = () => form.post(`/products/${props.product.id}/analyses`);
const input = 'rounded-lg border-0 px-3 py-2 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-600';
</script>

<template>
    <Head :title="product.name" />
    <AppLayout>
        <PageHeader :title="product.name" :subtitle="[product.brand, product.category, product.price ? '$' + product.price : null].filter(Boolean).join(' · ')">
            <template #crumbs><Link href="/products" class="hover:underline">Products</Link></template>
            <template #actions>
                <Btn :href="`/products/${product.id}/edit`" variant="secondary" size="sm">Edit</Btn>
                <ConfirmButton label="Delete" title="Delete this product?" message="This permanently deletes the product, all its imported reviews and every saved analysis." @confirm="router.delete(`/products/${product.id}`)" />
            </template>
        </PageHeader>

        <p v-if="product.description" class="-mt-3 mb-5 max-w-3xl text-sm text-slate-600">{{ product.description }}</p>
        <p v-if="product.is_demo" class="mb-5 rounded-lg bg-amber-50 px-4 py-2 text-sm text-amber-900 ring-1 ring-amber-200">This is a demonstration product. Its reviews are synthetic sample text, not real customer feedback.</p>

        <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <StatTile label="Reviews" :value="reviewStats.count" />
            <StatTile label="Average star rating" :value="reviewStats.average_rating ?? '—'" hint="Customer-provided, unprocessed" />
            <StatTile label="Demo reviews" :value="reviewStats.demo_count" />
        </dl>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <Card title="Reviews" subtitle="Import from CSV, then explore the raw text.">
                <div class="flex flex-wrap gap-2">
                    <Btn :href="`/products/${product.id}/imports/create`">Import CSV</Btn>
                    <Btn :href="`/products/${product.id}/reviews`" variant="secondary" :disabled="!reviewStats.count">Explore reviews</Btn>
                </div>
                <h3 class="mb-2 mt-5 text-sm font-semibold text-slate-900">Imports</h3>
                <p v-if="!imports.length" class="text-sm text-slate-500">No imports yet.</p>
                <ul v-else class="divide-y divide-slate-100 text-sm">
                    <li v-for="i in imports" :key="i.id" class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <div>
                            <Link :href="`/imports/${i.id}`" class="font-medium text-slate-800 hover:text-brand-700">{{ i.filename }}</Link>
                            <p class="text-xs text-slate-500">{{ formatDate(i.completed_at) }} · {{ i.accepted }} imported · {{ i.duplicates }} duplicates · {{ i.rejected }} rejected</p>
                        </div>
                        <DemoBadge v-if="i.is_demo" />
                    </li>
                </ul>
            </Card>

            <Card title="Analysis" subtitle="Runs in the background; results appear when finished.">
                <form class="flex flex-wrap items-end gap-3" @submit.prevent="run">
                    <div v-if="aiConfigured">
                        <label for="engine" class="mb-1 block text-xs font-medium text-slate-600">Engine</label>
                        <select id="engine" v-model="form.engine" :class="input">
                            <option value="baseline">Baseline (word lists, local)</option>
                            <option value="model">Model-backed (falls back to baseline)</option>
                        </select>
                    </div>
                    <Btn type="submit" :disabled="form.processing || hasActiveAnalysis || !reviewStats.count">{{ hasActiveAnalysis ? 'Analysis in progress…' : 'Run analysis' }}</Btn>
                </form>
                <p v-if="!reviewStats.count" class="mt-2 text-sm text-slate-500">Import reviews first.</p>
                <p v-if="!aiConfigured" class="mt-2 text-xs text-slate-500">Model-backed analysis is not configured on this server; the local baseline engine is used.</p>

                <h3 class="mb-2 mt-5 text-sm font-semibold text-slate-900">Saved analyses</h3>
                <p v-if="!analyses.length" class="text-sm text-slate-500">No analyses yet.</p>
                <ul v-else class="divide-y divide-slate-100 text-sm">
                    <li v-for="a in analyses" :key="a.id" class="py-2">
                        <div class="flex items-center justify-between gap-2">
                            <Link :href="`/analyses/${a.id}`" class="font-medium text-slate-800 hover:text-brand-700">Analysis #{{ a.id }} · {{ formatDate(a.created_at) }}</Link>
                            <StatusBadge :status="a.status" />
                        </div>
                        <div class="mt-1.5"><SentimentBar v-if="a.sentiment_distribution && a.status === 'completed'" :distribution="a.sentiment_distribution" compact /></div>
                        <p v-if="a.error" class="mt-1 text-xs text-rose-700">{{ a.error }}</p>
                    </li>
                </ul>
            </Card>
        </div>
    </AppLayout>
</template>
