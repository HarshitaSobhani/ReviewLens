<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Btn from '@/components/Btn.vue';
import Logo from '@/components/Logo.vue';
import type { SharedProps } from '@/types';

const page = usePage<SharedProps>();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <div class="min-h-screen bg-white">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-3 focus:top-3 focus:z-50 focus:rounded-md focus:bg-white focus:px-3 focus:py-2 focus:shadow">Skip to content</a>
        <header class="border-b border-slate-200">
            <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-4">
                <Link href="/" aria-label="ReviewLens home"><Logo /></Link>
                <nav class="flex items-center gap-2" aria-label="Account">
                    <template v-if="user"><Btn href="/dashboard" size="sm">Open dashboard</Btn></template>
                    <template v-else>
                        <Btn href="/login" variant="ghost" size="sm">Log in</Btn>
                        <Btn href="/register" size="sm">Create account</Btn>
                    </template>
                </nav>
            </div>
        </header>
        <main id="main"><slot /></main>
        <footer class="border-t border-slate-200 py-8 text-center text-xs text-slate-500">
            ReviewLens analyses reviews you provide. Results are automated inferences, not verified facts.
        </footer>
    </div>
</template>
