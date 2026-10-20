<script setup lang="ts">
import Badge from './Badge.vue';

defineProps<{ status: string }>();
const tone = { completed: 'pos', failed: 'neg', running: 'brand', pending: 'mix' } as const;
const label = { completed: 'Completed', failed: 'Failed', running: 'In progress', pending: 'Queued' } as Record<string, string>;
</script>

<template>
    <Badge :tone="(tone as any)[status] ?? 'neutral'">
        <span v-if="status === 'running' || status === 'pending'" class="h-1.5 w-1.5 animate-pulse rounded-full bg-current" aria-hidden="true" />
        {{ label[status] ?? status }}
    </Badge>
</template>
