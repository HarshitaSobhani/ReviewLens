<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Badge from '@/components/Badge.vue';
import Btn from '@/components/Btn.vue';
import Card from '@/components/Card.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import RatingBars from '@/components/RatingBars.vue';
import SentimentBar from '@/components/SentimentBar.vue';
import ThemeBar from '@/components/ThemeBar.vue';
import { formatDate } from '@/lib/format';
import AppLayout from '@/layouts/AppLayout.vue';

interface Item {
    analysis_id: number; product_id: number; product_name: string; review_count: number; average_rating: number | null; rated_count: number;
    rating_distribution: Record<number, number>; sentiment_distribution: Record<string, number>;
    top_positive: { key: string; label: string; count: number; of: number }[];
    top_negative: { key: string; label: string; count: number; of: number }[];
    summary: { type: string; statement: string; strength: string; evidence_count: number }[];
    includes_demo: boolean; engine: string;
}
interface Comparison {
    items: Item[]; verdict: string; warnings: string[]; differences: string[];
    themes: { key: string; label: string; cells: Record<number, { mentions: number; positive: number; negative: number; neutral: number; share_of_reviews: number }> }[];
}
const props = defineProps<{
    options: { id: number; product_name: string; review_count: number; created_at: string }[];
    selected: number[]; comparison: Comparison | null; error: string | null;
}>();
const chosen = ref<number[]>([...props.selected]);
function compare() {
    router.get('/compare', { analyses: chosen.value }, { preserveState: true });
}
const cols = (n: number) => (n <= 2 ? 'md:grid-cols-2' : n === 3 ? 'md:grid-cols-3' : 'md:grid-cols-2 xl:grid-cols-4');
</script>

<template>
    <Head title="Compare products" />
    <AppLayout>
        <PageHeader title="Compare products" subtitle="Side-by-side evidence from each product's latest completed analysis. Descriptive only." />

        <EmptyState v-if="options.length < 2" title="You need at least two analysed products" description="Complete analyses for two or more products, or add the demo products from the dashboard.">
            <Btn href="/products">Go to products</Btn>
        </EmptyState>

        <template v-else>
            <Card title="Choose 2–5 analyses">
                <form @submit.prevent="compare">
                    <fieldset>
                        <legend class="sr-only">Analyses to compare</legend>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <label v-for="o in options" :key="o.id" class="flex cursor-pointer items-start gap-2 rounded-lg border border-slate-200 p-3 text-sm hover:bg-slate-50 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                <input v-model="chosen" type="checkbox" :value="o.id" :disabled="!chosen.includes(o.id) && chosen.length >= 5" class="mt-0.5 rounded border-slate-300 text-brand-600" />
                                <span><span class="font-medium text-slate-900">{{ o.product_name }}</span><br /><span class="text-xs text-slate-500">{{ o.review_count }} reviews · {{ formatDate(o.created_at) }}</span></span>
                            </label>
                        </div>
                    </fieldset>
                    <Btn type="submit" class="mt-4" :disabled="chosen.length < 2">Compare {{ chosen.length || '' }} selected</Btn>
                </form>
            </Card>
            <p v-if="error" class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-900 ring-1 ring-rose-200" role="alert">{{ error }}</p>
        </template>

        <template v-if="comparison">
            <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="note">
                <p class="font-semibold">Read this first</p>
                <p class="mt-1">{{ comparison.verdict }}</p>
                <ul v-if="comparison.warnings.length" class="mt-2 list-disc space-y-1 pl-5"><li v-for="w in comparison.warnings" :key="w">{{ w }}</li></ul>
            </div>

            <div class="mt-6 grid gap-4" :class="cols(comparison.items.length)">
                <Card v-for="it in comparison.items" :key="it.analysis_id" :title="it.product_name" :subtitle="`${it.review_count} reviews · ${it.engine}`">
                    <template #actions><Link :href="`/analyses/${it.analysis_id}`" class="text-xs font-medium text-brand-700 hover:underline">Open analysis</Link></template>
                    <p class="text-2xl font-semibold tabular-nums">{{ it.average_rating ?? '—' }} <span class="text-sm font-normal text-slate-500">★ avg ({{ it.rated_count }} rated)</span></p>
                    <h3 class="mb-1 mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Star ratings</h3>
                    <RatingBars :distribution="it.rating_distribution" />
                    <h3 class="mb-1 mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Inferred sentiment</h3>
                    <SentimentBar :distribution="it.sentiment_distribution" />
                    <h3 class="mb-1 mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Common positive themes</h3>
                    <p v-if="!it.top_positive.length" class="text-sm text-slate-500">None with two or more positive mentions.</p>
                    <ul class="space-y-0.5 text-sm"><li v-for="t in it.top_positive" :key="t.key">{{ t.label }} <span class="text-slate-500">{{ t.count }}/{{ t.of }}</span></li></ul>
                    <h3 class="mb-1 mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Common complaints</h3>
                    <p v-if="!it.top_negative.length" class="text-sm text-slate-500">None with two or more negative mentions.</p>
                    <ul class="space-y-0.5 text-sm"><li v-for="t in it.top_negative" :key="t.key">{{ t.label }} <span class="text-slate-500">{{ t.count }}/{{ t.of }}</span></li></ul>
                    <h3 class="mb-1 mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Evidence-backed summary</h3>
                    <ul class="space-y-2 text-sm">
                        <li v-for="(s, i) in it.summary.slice(0, 5)" :key="i"><Badge>{{ s.type }}</Badge> {{ s.statement }} <span class="text-xs text-slate-500">({{ s.evidence_count }} linked)</span></li>
                    </ul>
                </Card>
            </div>

            <Card v-if="comparison.differences.length" class="mt-6" title="Differences in recurring complaints" subtitle="Where negative share differs by 20+ points">
                <ul class="list-disc space-y-1 pl-5 text-sm text-slate-700"><li v-for="d in comparison.differences" :key="d">{{ d }}</li></ul>
            </Card>

            <Card class="mt-6" title="Theme overview" subtitle="Mentions (and positive/negative split) per product" :padded="false">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[32rem] text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase text-slate-500"><tr><th class="px-5 py-2">Theme</th><th v-for="it in comparison.items" :key="it.analysis_id" class="px-3 py-2">{{ it.product_name }}</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="row in comparison.themes" :key="row.key">
                                <th scope="row" class="px-5 py-2 font-medium text-slate-800">{{ row.label }}</th>
                                <td v-for="it in comparison.items" :key="it.analysis_id" class="px-3 py-2">
                                    <template v-if="row.cells[it.analysis_id]">
                                        <ThemeBar :positive="row.cells[it.analysis_id].positive" :negative="row.cells[it.analysis_id].negative" :neutral="row.cells[it.analysis_id].neutral" />
                                        <span class="text-xs tabular-nums text-slate-500">{{ row.cells[it.analysis_id].mentions }} · +{{ row.cells[it.analysis_id].positive }}/−{{ row.cells[it.analysis_id].negative }}</span>
                                    </template>
                                    <span v-else class="text-xs text-slate-400">not detected</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </template>
    </AppLayout>
</template>
