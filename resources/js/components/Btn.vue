<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = withDefaults(defineProps<{
    href?: string;
    variant?: 'primary' | 'secondary' | 'danger' | 'ghost';
    size?: 'sm' | 'md';
    type?: 'button' | 'submit';
    disabled?: boolean;
    external?: boolean; // plain <a> (downloads)
}>(), { variant: 'primary', size: 'md', type: 'button' });

const classes = computed(() => [
    'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-colors select-none',
    'disabled:opacity-50 disabled:cursor-not-allowed aria-disabled:opacity-50',
    props.size === 'sm' ? 'px-3 py-1.5 text-sm' : 'px-4 py-2 text-sm',
    {
        primary: 'bg-brand-600 text-white hover:bg-brand-700 shadow-sm',
        secondary: 'bg-white text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50',
        danger: 'bg-white text-rose-700 ring-1 ring-inset ring-rose-300 hover:bg-rose-50',
        ghost: 'text-slate-600 hover:bg-slate-100',
    }[props.variant],
]);
</script>

<template>
    <a v-if="href && external" :href="href" :class="classes"><slot /></a>
    <Link v-else-if="href" :href="href" :class="classes"><slot /></Link>
    <button v-else :type="type" :disabled="disabled" :class="classes"><slot /></button>
</template>
