<script setup lang="ts">
defineProps<{ rating: number | null | undefined }>();
</script>

<template>
    <span v-if="rating" class="inline-flex items-center gap-0.5 text-amber-500" :aria-label="`${rating} out of 5 stars (customer rating)`" role="img">
        <svg v-for="i in 5" :key="i" viewBox="0 0 20 20" class="h-4 w-4" :fill="i <= rating ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.5">
            <path d="M10 1.8l2.4 5 5.4.7-4 3.7 1 5.4L10 14l-4.8 2.6 1-5.4-4-3.7 5.4-.7z" />
        </svg>
    </span>
    <span v-else class="text-xs text-slate-400">No rating</span>
</template>
