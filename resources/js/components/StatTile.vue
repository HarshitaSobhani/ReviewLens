<script setup lang="ts">
defineProps<{ label: string; value: string | number; hint?: string }>();
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ label }}</dt>
        <dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">{{ value }}</dd>
        <p v-if="hint" class="mt-0.5 text-xs text-slate-500">{{ hint }}</p>
    </div>
</template>
