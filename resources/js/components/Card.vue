<script setup lang="ts">
defineProps<{ title?: string; subtitle?: string; padded?: boolean }>();
</script>

<template>
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <header v-if="title || $slots.actions" class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 px-5 py-4">
            <div>
                <h2 class="text-base font-semibold text-slate-900">{{ title }}</h2>
                <p v-if="subtitle" class="mt-0.5 text-sm text-slate-500">{{ subtitle }}</p>
            </div>
            <div v-if="$slots.actions" class="flex items-center gap-2"><slot name="actions" /></div>
        </header>
        <div :class="padded === false ? '' : 'p-5'"><slot /></div>
    </section>
</template>
