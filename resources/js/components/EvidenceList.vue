<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Evidence } from '@/types';
import DemoBadge from './DemoBadge.vue';
import Stars from './Stars.vue';

defineProps<{ evidence: Evidence[] }>();
</script>

<template>
    <ul class="space-y-2">
        <li v-for="e in evidence" :key="e.review_id" class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
            <blockquote class="text-slate-800">“{{ e.excerpt }}”</blockquote>
            <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                <Link :href="`/reviews/${e.review_id}`" class="font-medium text-brand-700 hover:underline">Review #{{ e.review_id }}</Link>
                <Stars :rating="e.rating" />
                <span v-if="e.date">{{ e.date }}</span>
                <span v-if="e.source">{{ e.source }}</span>
                <DemoBadge v-if="e.is_demo" />
            </div>
        </li>
    </ul>
</template>
