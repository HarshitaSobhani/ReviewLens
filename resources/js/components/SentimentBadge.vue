<script setup lang="ts">
import { sentimentLabel } from '@/lib/format';
import Badge from './Badge.vue';

const props = defineProps<{ sentiment: string | null | undefined }>();
const tone = (s?: string | null) => ({ positive: 'pos', negative: 'neg', mixed: 'mix' } as Record<string, 'pos' | 'neg' | 'mix'>)[s ?? ''] ?? 'neutral';
</script>

<template>
    <Badge v-if="props.sentiment" :tone="tone(props.sentiment)" :title="'Sentiment inferred from review text, not the star rating'">
        {{ sentimentLabel[props.sentiment] ?? props.sentiment }}
    </Badge>
</template>
