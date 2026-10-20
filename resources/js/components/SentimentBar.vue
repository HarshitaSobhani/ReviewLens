<script setup lang="ts">
import { computed } from 'vue';
import { pct, sentimentLabel } from '@/lib/format';

const props = defineProps<{ distribution: Record<string, number>; compact?: boolean }>();
const order = ['positive', 'mixed', 'neutral', 'negative'];
const colors: Record<string, string> = { positive: 'bg-teal-600', mixed: 'bg-amber-500', neutral: 'bg-slate-400', negative: 'bg-rose-600' };
const total = computed(() => order.reduce((a, k) => a + (props.distribution?.[k] ?? 0), 0));
const summary = computed(() => order.map((k) => `${sentimentLabel[k]} ${props.distribution?.[k] ?? 0}`).join(', '));
</script>

<template>
    <div>
        <div class="flex h-3 w-full overflow-hidden rounded-full bg-slate-100" role="img" :aria-label="`Inferred sentiment of ${total} reviews: ${summary}`">
            <div v-for="k in order" :key="k" :class="colors[k]" :style="{ width: pct(distribution?.[k] ?? 0, total) + '%' }" />
        </div>
        <ul v-if="!compact" class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
            <li v-for="k in order" :key="k" class="flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-sm" :class="colors[k]" aria-hidden="true" />
                {{ sentimentLabel[k] }} <span class="tabular-nums font-medium text-slate-900">{{ distribution?.[k] ?? 0 }}</span>
                <span class="text-slate-400">({{ pct(distribution?.[k] ?? 0, total) }}%)</span>
            </li>
        </ul>
    </div>
</template>
