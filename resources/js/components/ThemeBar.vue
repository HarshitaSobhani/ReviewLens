<script setup lang="ts">
import { pct } from '@/lib/format';

const props = defineProps<{ positive: number; negative: number; neutral: number }>();
const total = props.positive + props.negative + props.neutral;
</script>

<template>
    <div class="flex h-2.5 w-full overflow-hidden rounded-full bg-slate-100" role="img"
        :aria-label="`${positive} positive, ${negative} negative, ${neutral} neutral or mixed mentions`">
        <div class="bg-teal-600" :style="{ width: pct(positive, total) + '%' }" />
        <div class="bg-slate-300" :style="{ width: pct(neutral, total) + '%' }" />
        <div class="bg-rose-600" :style="{ width: pct(negative, total) + '%' }" />
    </div>
</template>
