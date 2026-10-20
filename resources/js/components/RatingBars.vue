<script setup lang="ts">
import { computed } from 'vue';
import { pct } from '@/lib/format';

const props = defineProps<{ distribution: Record<string | number, number> }>();
const total = computed(() => [1, 2, 3, 4, 5].reduce((a, k) => a + (props.distribution?.[k] ?? 0), 0));
</script>

<template>
    <div role="group" aria-label="Star rating distribution">
        <div v-for="star in [5, 4, 3, 2, 1]" :key="star" class="flex items-center gap-2 py-0.5 text-sm">
            <span class="w-9 shrink-0 tabular-nums text-slate-600">{{ star }} ★</span>
            <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-brand-500" :style="{ width: pct(distribution?.[star] ?? 0, total) + '%' }" />
            </div>
            <span class="w-14 shrink-0 text-right tabular-nums text-slate-600">{{ distribution?.[star] ?? 0 }} <span class="text-slate-400">· {{ pct(distribution?.[star] ?? 0, total) }}%</span></span>
        </div>
        <p v-if="!total" class="text-sm text-slate-500">No star ratings in this dataset.</p>
    </div>
</template>
