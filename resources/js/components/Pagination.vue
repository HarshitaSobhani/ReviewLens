<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Paginated } from '@/types';

defineProps<{ page: Paginated<unknown> }>();

// Laravel labels contain HTML entities (e.g. &laquo;); render them as plain text, never as HTML.
const label = (l: string) => l.replace(/&laquo;/g, '«').replace(/&raquo;/g, '»').replace(/<[^>]*>/g, '');
</script>

<template>
    <nav v-if="page.last_page > 1" class="flex flex-wrap items-center justify-between gap-2 pt-4 text-sm" aria-label="Pagination">
        <p class="text-slate-600">Showing {{ page.from }}–{{ page.to }} of {{ page.total }}</p>
        <ul class="flex flex-wrap gap-1">
            <li v-for="(l, i) in page.links" :key="i">
                <span v-if="!l.url" class="inline-block rounded-md px-3 py-1.5 text-slate-400">{{ label(l.label) }}</span>
                <Link v-else :href="l.url" preserve-scroll
                    class="inline-block rounded-md px-3 py-1.5 ring-1 ring-inset"
                    :class="l.active ? 'bg-brand-600 text-white ring-brand-600' : 'bg-white text-slate-700 ring-slate-300 hover:bg-slate-50'"
                    :aria-current="l.active ? 'page' : undefined">{{ label(l.label) }}</Link>
            </li>
        </ul>
    </nav>
</template>
