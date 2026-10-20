<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center">
        <div class="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-brand-50 text-brand-600" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="6" /><path d="M20 20l-4-4" /></svg>
        </div>
        <h3 class="text-sm font-semibold text-slate-900">{{ title }}</h3>
        <p v-if="description" class="mx-auto mt-1 max-w-md text-sm text-slate-500">{{ description }}</p>
        <div class="mt-4 flex flex-wrap items-center justify-center gap-2"><slot /></div>
    </div>
</template>
