<script setup lang="ts">
defineProps<{ title: string; subtitle?: string }>();
</script>

<template>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            <div v-if="$slots.crumbs" class="mb-1 text-sm text-slate-500"><slot name="crumbs" /></div>
            <h1 class="truncate text-2xl font-semibold tracking-tight text-slate-900">{{ title }}</h1>
            <p v-if="subtitle" class="mt-1 text-sm text-slate-600">{{ subtitle }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2"><slot name="actions" /></div>
    </div>
</template>
