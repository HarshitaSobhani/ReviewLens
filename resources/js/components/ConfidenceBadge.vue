<script setup lang="ts">
import Badge from './Badge.vue';

defineProps<{ confidence: string | null | undefined; method?: string | null }>();
const hint = 'Qualitative confidence label, not a calibrated probability.';
</script>

<template>
    <Badge v-if="confidence" :tone="confidence === 'low' ? 'warn' : 'neutral'" :title="hint">
        {{ confidence }} confidence{{ method === 'rating' ? ' · from stars' : '' }}
    </Badge>
</template>
