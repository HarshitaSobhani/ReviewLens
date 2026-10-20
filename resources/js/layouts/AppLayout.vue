<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Logo from '@/components/Logo.vue';
import type { SharedProps } from '@/types';

const page = usePage<SharedProps>();
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash);
const open = ref(false);
const nav = [
    { href: '/dashboard', label: 'Dashboard', match: '/dashboard' },
    { href: '/products', label: 'Products', match: '/products' },
    { href: '/analyses', label: 'Analyses', match: '/analyses' },
    { href: '/compare', label: 'Compare', match: '/compare' },
];
const active = (m: string) => page.url === m || page.url.startsWith(m + '/') || page.url.startsWith(m + '?');
watch(() => page.url, () => (open.value = false));
const logout = () => router.post('/logout');
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-3 focus:top-3 focus:z-50 focus:rounded-md focus:bg-white focus:px-3 focus:py-2 focus:shadow">Skip to content</a>
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur">
            <div class="mx-auto flex h-14 max-w-6xl items-center justify-between gap-4 px-4">
                <div class="flex items-center gap-6">
                    <Link href="/dashboard" aria-label="ReviewLens home"><Logo /></Link>
                    <nav class="hidden items-center gap-1 md:flex" aria-label="Primary">
                        <Link v-for="n in nav" :key="n.href" :href="n.href"
                            class="rounded-md px-3 py-1.5 text-sm font-medium"
                            :class="active(n.match) ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-100'"
                            :aria-current="active(n.match) ? 'page' : undefined">{{ n.label }}</Link>
                    </nav>
                </div>
                <div class="flex items-center gap-3">
                    <span class="hidden text-sm text-slate-600 sm:inline">{{ user?.name }}</span>
                    <button type="button" class="hidden rounded-md px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100 md:inline" @click="logout">Log out</button>
                    <button type="button" class="rounded-md p-2 text-slate-600 hover:bg-slate-100 md:hidden" :aria-expanded="open" aria-controls="mobile-nav" aria-label="Toggle menu" @click="open = !open">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path v-if="!open" d="M4 7h16M4 12h16M4 17h16" /><path v-else d="M6 6l12 12M18 6L6 18" /></svg>
                    </button>
                </div>
            </div>
            <nav v-show="open" id="mobile-nav" class="border-t border-slate-200 bg-white px-4 py-2 md:hidden" aria-label="Mobile">
                <Link v-for="n in nav" :key="n.href" :href="n.href" class="block rounded-md px-3 py-2 text-sm font-medium"
                    :class="active(n.match) ? 'bg-brand-50 text-brand-700' : 'text-slate-700'">{{ n.label }}</Link>
                <button type="button" class="block w-full rounded-md px-3 py-2 text-left text-sm font-medium text-slate-700" @click="logout">Log out ({{ user?.name }})</button>
            </nav>
        </header>

        <main id="main" class="mx-auto max-w-6xl px-4 py-8">
            <div v-if="flash.success" role="status" class="mb-4 rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">{{ flash.success }}</div>
            <div v-if="flash.error" role="alert" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ flash.error }}</div>
            <slot />
        </main>

        <footer class="mx-auto max-w-6xl px-4 pb-10 text-xs text-slate-500">
            Sentiment and themes are inferred automatically and can be wrong. Always check the linked reviews.
        </footer>
    </div>
</template>
