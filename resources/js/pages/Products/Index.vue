<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Btn from '@/components/Btn.vue';
import Card from '@/components/Card.vue';
import DemoBadge from '@/components/DemoBadge.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Paginated, ProductSummary } from '@/types';

const props = defineProps<{ products: Paginated<ProductSummary>; filters: { q?: string; category?: string }; categories: string[] }>();
const q = ref(props.filters.q ?? '');
const category = ref(props.filters.category ?? '');
let timer: ReturnType<typeof setTimeout>;
function apply() {
    router.get('/products', { q: q.value || undefined, category: category.value || undefined }, { preserveState: true, replace: true });
}
watch(q, () => { clearTimeout(timer); timer = setTimeout(apply, 300); });
watch(category, apply);
const input = 'rounded-lg border-0 px-3 py-2 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-600';
</script>

<template>
    <Head title="Products" />
    <AppLayout>
        <PageHeader title="Products" subtitle="Each product has its own reviews and saved analyses.">
            <template #actions><Btn href="/products/create">New product</Btn></template>
        </PageHeader>

        <form class="mb-4 flex flex-wrap gap-3" role="search" @submit.prevent="apply">
            <label class="sr-only" for="q">Search products</label>
            <input id="q" v-model="q" type="search" placeholder="Search name or brand" :class="[input, 'w-full sm:w-72']" />
            <label class="sr-only" for="cat">Category</label>
            <select id="cat" v-model="category" :class="input"><option value="">All categories</option><option v-for="c in categories" :key="c" :value="c">{{ c }}</option></select>
        </form>

        <EmptyState v-if="!products.data.length" :title="filters.q || filters.category ? 'No products match' : 'No products yet'"
            :description="filters.q || filters.category ? 'Try a different search or clear the filters.' : 'Create a product to start importing reviews.'">
            <Btn v-if="!filters.q && !filters.category" href="/products/create">Create a product</Btn>
        </EmptyState>

        <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="p in products.data" :key="p.id">
                <Card class="h-full">
                    <div class="flex items-start gap-3">
                        <img v-if="p.image_url" :src="p.image_url" alt="" class="h-14 w-14 shrink-0 rounded-lg object-cover" loading="lazy" referrerpolicy="no-referrer" />
                        <div class="min-w-0">
                            <Link :href="`/products/${p.id}`" class="block truncate font-semibold text-slate-900 hover:text-brand-700">{{ p.name }}</Link>
                            <p class="text-sm text-slate-500">{{ [p.brand, p.category].filter(Boolean).join(' · ') || 'Uncategorised' }}</p>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center justify-between text-sm text-slate-600">
                        <span>{{ p.reviews_count }} reviews</span>
                        <span v-if="p.price">${{ p.price }}</span>
                        <DemoBadge v-if="p.is_demo" />
                    </div>
                </Card>
            </li>
        </ul>
        <Pagination :page="products" />
    </AppLayout>
</template>
