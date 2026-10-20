<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Badge from '@/components/Badge.vue';
import Btn from '@/components/Btn.vue';
import Card from '@/components/Card.vue';
import ConfirmButton from '@/components/ConfirmButton.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatTile from '@/components/StatTile.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';

interface Preview {
    total: number; accepted: number; rejected: number; duplicates: number;
    errors: { row: number; message: string }[];
    sample: { row: number; status: string; message: string | null; title: string | null; body: string; rating: number | null; date: string | null }[];
}
const props = defineProps<{
    importJob: {
        id: number; filename: string; status: string; headers: string[] | null; mapping: Record<string, string | null> | null;
        source_name: string | null; total: number; accepted: number; rejected: number; duplicates: number;
        row_errors: { row: number; message: string }[]; is_demo: boolean; error: string | null;
    };
    product: { id: number; name: string };
    preview: Preview | null;
    previewError: string | null;
    fields: string[];
}>();

const form = useForm({ mapping: { ...(props.importJob.mapping ?? {}) } as Record<string, string | null>, source_name: props.importJob.source_name ?? '' });
const isPreview = computed(() => props.importJob.status === 'previewed');
const fieldLabel = (f: string) => ({ body: 'Review text *', external_id: 'Source ID', verified: 'Verified purchase' } as Record<string, string>)[f] ?? f.charAt(0).toUpperCase() + f.slice(1);
const remap = () => form.put(`/imports/${props.importJob.id}/mapping`, { preserveScroll: true });
const confirm = () => form.post(`/imports/${props.importJob.id}/confirm`);
const input = 'block w-full rounded-lg border-0 px-3 py-2 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-600';
const tone = (s: string) => (s === 'accepted' ? 'pos' : s === 'duplicate' ? 'mix' : 'neg');
</script>

<template>
    <Head title="Import" />
    <AppLayout>
        <PageHeader :title="isPreview ? 'Preview import' : 'Import summary'" :subtitle="importJob.filename">
            <template #crumbs><Link :href="`/products/${product.id}`" class="hover:underline">{{ product.name }}</Link></template>
            <template #actions>
                <StatusBadge :status="importJob.status === 'previewed' ? 'pending' : importJob.status" />
            </template>
        </PageHeader>

        <div v-if="previewError || importJob.error" role="alert" class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ previewError || importJob.error }}</div>

        <!-- Preview / mapping -->
        <template v-if="isPreview && preview">
            <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatTile label="Rows in file" :value="preview.total" />
                <StatTile label="Will import" :value="preview.accepted" />
                <StatTile label="Duplicates (skipped)" :value="preview.duplicates" />
                <StatTile label="Rejected" :value="preview.rejected" />
            </dl>

            <div class="mt-6 grid gap-6 lg:grid-cols-3">
                <Card title="Column mapping" class="lg:col-span-1">
                    <form class="space-y-3" @submit.prevent="remap">
                        <div v-for="f in fields" :key="f">
                            <label :for="`m-${f}`" class="mb-1 block text-xs font-medium text-slate-600">{{ fieldLabel(f) }}</label>
                            <select :id="`m-${f}`" v-model="form.mapping[f]" :class="input">
                                <option :value="null">— not in file —</option>
                                <option v-for="h in importJob.headers ?? []" :key="h" :value="h">{{ h }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="src" class="mb-1 block text-xs font-medium text-slate-600">Default source name</label>
                            <input id="src" v-model="form.source_name" maxlength="100" :class="input" />
                        </div>
                        <p v-if="form.errors.mapping" class="text-sm text-rose-700" role="alert">{{ form.errors.mapping }}</p>
                        <Btn type="submit" variant="secondary" :disabled="form.processing">Update preview</Btn>
                    </form>
                </Card>

                <div class="space-y-6 lg:col-span-2">
                    <Card title="First rows" :padded="false">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500"><tr><th class="px-4 py-2">Row</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Review</th><th class="px-4 py-2">Rating</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="r in preview.sample" :key="r.row" class="align-top">
                                        <td class="px-4 py-2 tabular-nums">{{ r.row }}</td>
                                        <td class="px-4 py-2"><Badge :tone="tone(r.status)">{{ r.status }}</Badge><p v-if="r.message" class="mt-1 text-xs text-rose-700">{{ r.message }}</p></td>
                                        <td class="max-w-md px-4 py-2 text-slate-700">{{ r.body }}</td>
                                        <td class="px-4 py-2 tabular-nums">{{ r.rating ?? '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </Card>
                    <Card v-if="preview.errors.length" :title="`Row problems (${preview.rejected})`" subtitle="These rows will be skipped.">
                        <ul class="max-h-56 space-y-1 overflow-y-auto text-sm"><li v-for="e in preview.errors" :key="e.row"><span class="font-medium tabular-nums">Row {{ e.row }}:</span> {{ e.message }}</li></ul>
                    </Card>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-2">
                <Btn :disabled="form.processing || preview.accepted === 0" @click="confirm">Import {{ preview.accepted }} reviews</Btn>
                <ConfirmButton label="Discard upload" title="Discard this upload?" message="The uploaded file is deleted and nothing is imported." confirm-label="Discard" size="md" @confirm="router.delete(`/imports/${importJob.id}`)" />
            </div>
            <p v-if="preview.accepted === 0" class="mt-2 text-sm text-slate-600">Nothing importable yet. Check the column mapping above.</p>
        </template>

        <div v-else-if="isPreview" class="flex gap-2">
            <ConfirmButton label="Discard upload" title="Discard this upload?" message="The uploaded file is deleted." confirm-label="Discard" size="md" @confirm="router.delete(`/imports/${importJob.id}`)" />
        </div>

        <!-- Completed summary -->
        <template v-else>
            <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatTile label="Rows in file" :value="importJob.total" />
                <StatTile label="Imported" :value="importJob.accepted" />
                <StatTile label="Duplicates skipped" :value="importJob.duplicates" />
                <StatTile label="Rejected" :value="importJob.rejected" />
            </dl>
            <Card v-if="importJob.row_errors.length" class="mt-6" title="Rejected rows">
                <ul class="max-h-64 space-y-1 overflow-y-auto text-sm"><li v-for="e in importJob.row_errors" :key="e.row"><span class="font-medium tabular-nums">Row {{ e.row }}:</span> {{ e.message }}</li></ul>
            </Card>
            <div class="mt-6 flex flex-wrap gap-2">
                <Btn :href="`/products/${product.id}`">Back to product</Btn>
                <Btn :href="`/products/${product.id}/reviews`" variant="secondary">Explore reviews</Btn>
                <ConfirmButton v-if="importJob.status === 'completed'" label="Delete imported reviews" title="Delete this import's reviews?" message="Reviews added by this import are permanently removed, and so are this product's saved analyses, because they would cite deleted reviews." size="md" @confirm="router.delete(`/imports/${importJob.id}`)" />
            </div>
        </template>
    </AppLayout>
</template>
