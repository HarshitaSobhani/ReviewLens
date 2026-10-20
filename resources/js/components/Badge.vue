<script setup lang="ts">
withDefaults(defineProps<{ tone?: 'neutral' | 'brand' | 'pos' | 'neg' | 'mix' | 'warn' }>(), { tone: 'neutral' });
const tones = {
    neutral: 'bg-slate-100 text-slate-700 ring-slate-200',
    brand: 'bg-brand-50 text-brand-700 ring-brand-200',
    pos: 'bg-teal-50 text-teal-800 ring-teal-200',
    neg: 'bg-rose-50 text-rose-800 ring-rose-200',
    mix: 'bg-amber-50 text-amber-800 ring-amber-200',
    warn: 'bg-amber-50 text-amber-900 ring-amber-300',
};
</script>

<template>
    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="tones[tone]"><slot /></span>
</template>
