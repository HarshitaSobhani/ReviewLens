<script setup lang="ts">
defineProps<{ label: string; for?: string; error?: string; hint?: string }>();
</script>

<template>
    <div>
        <label :for="$props.for" class="mb-1 block text-sm font-medium text-slate-700">{{ label }}</label>
        <slot />
        <p v-if="hint && !error" class="mt-1 text-xs text-slate-500">{{ hint }}</p>
        <p v-if="error" class="mt-1 text-sm text-rose-700" role="alert">{{ error }}</p>
    </div>
</template>
