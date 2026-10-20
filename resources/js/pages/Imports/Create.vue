<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Btn from '@/components/Btn.vue';
import Card from '@/components/Card.vue';
import Field from '@/components/Field.vue';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    product: { id: number; name: string };
    limits: { max_rows: number; max_file_kb: number; min_chars: number };
    aliases: Record<string, string[]>;
}>();
const form = useForm<{ file: File | null; source_name: string }>({ file: null, source_name: '' });
const submit = () => form.post(`/products/${props.product.id}/imports`, { forceFormData: true });
const input = 'block w-full rounded-lg border-0 px-3 py-2 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-600';
const example = 'review_text,rating,date,verified_purchase,source,id\n"Battery lasts all week and the sound is great",5,2025-03-01,yes,My shop export,A-1';
</script>

<template>
    <Head title="Import reviews" />
    <AppLayout>
        <PageHeader title="Import reviews" :subtitle="`Into ${product.name}`">
            <template #crumbs><Link :href="`/products/${product.id}`" class="hover:underline">{{ product.name }}</Link></template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-5">
            <Card title="Upload a CSV" class="lg:col-span-2">
                <form class="space-y-4" @submit.prevent="submit">
                    <Field label="CSV file" for="file" :error="form.errors.file" :hint="`Max ${Math.round(limits.max_file_kb / 1024)} MB and ${limits.max_rows.toLocaleString()} rows.`">
                        <input id="file" type="file" accept=".csv,text/csv" required class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:font-medium file:text-brand-700 hover:file:bg-brand-100"
                            @change="form.file = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                    </Field>
                    <Field label="Source name (optional)" for="source" hint="Used when the file has no source column, e.g. “Store export, March 2025”." :error="form.errors.source_name">
                        <input id="source" v-model="form.source_name" maxlength="100" :class="input" />
                    </Field>
                    <Btn type="submit" :disabled="form.processing || !form.file">{{ form.processing ? 'Uploading…' : 'Upload and preview' }}</Btn>
                    <p class="text-xs text-slate-500">Nothing is saved until you confirm on the preview screen.</p>
                </form>
            </Card>

            <Card title="CSV format" class="lg:col-span-3">
                <p class="text-sm text-slate-600">The first row must contain headers. Comma, semicolon, tab or pipe delimiters and UTF-8 are supported. Only review text is required (at least {{ limits.min_chars }} characters). You can re-map columns on the preview screen.</p>
                <table class="mt-4 w-full text-left text-sm">
                    <caption class="sr-only">Accepted CSV headers</caption>
                    <thead><tr class="border-b border-slate-200 text-xs uppercase text-slate-500"><th class="py-1.5 pr-3">Field</th><th class="py-1.5">Accepted headers (auto-detected)</th></tr></thead>
                    <tbody>
                        <tr v-for="(names, field) in aliases" :key="field" class="border-b border-slate-100 align-top">
                            <th scope="row" class="py-1.5 pr-3 font-medium text-slate-800">{{ field === 'body' ? 'review text *' : field.replace('_', ' ') }}</th>
                            <td class="py-1.5 text-slate-600">{{ names.join(', ') }}</td>
                        </tr>
                    </tbody>
                </table>
                <p class="mt-3 text-xs text-slate-500">Ratings may be 1–5, “4/5” or “8/10” (scaled to stars). Dates like 2025-03-01. Verified: yes/no/true/false/1/0. Duplicates are skipped by source ID or by identical text (ignoring case and punctuation).</p>
                <pre class="mt-3 overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100"><code>{{ example }}</code></pre>
            </Card>
        </div>
    </AppLayout>
</template>
