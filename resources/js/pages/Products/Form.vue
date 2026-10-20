<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Btn from '@/components/Btn.vue';
import Card from '@/components/Card.vue';
import Field from '@/components/Field.vue';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ProductSummary } from '@/types';

const props = defineProps<{ product: ProductSummary | null }>();
const form = useForm({
    name: props.product?.name ?? '', category: props.product?.category ?? '', brand: props.product?.brand ?? '',
    price: props.product?.price ?? '', description: props.product?.description ?? '', image_url: props.product?.image_url ?? '',
});
const submit = () => (props.product ? form.put(`/products/${props.product.id}`) : form.post('/products'));
const input = 'block w-full rounded-lg border-0 px-3 py-2 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-600';
</script>

<template>
    <Head :title="product ? 'Edit product' : 'New product'" />
    <AppLayout>
        <PageHeader :title="product ? 'Edit product' : 'New product'" />
        <Card class="max-w-2xl">
            <form class="space-y-4" @submit.prevent="submit">
                <Field label="Name" for="name" :error="form.errors.name"><input id="name" v-model="form.name" required maxlength="150" :class="input" /></Field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <Field label="Brand" for="brand" :error="form.errors.brand"><input id="brand" v-model="form.brand" maxlength="80" :class="input" /></Field>
                    <Field label="Category" for="category" :error="form.errors.category"><input id="category" v-model="form.category" maxlength="80" :class="input" /></Field>
                </div>
                <Field label="Price (optional)" for="price" :error="form.errors.price"><input id="price" v-model="form.price" type="number" min="0" step="0.01" inputmode="decimal" :class="input" /></Field>
                <Field label="Image URL (optional)" for="image" hint="https:// link to an image you may use." :error="form.errors.image_url"><input id="image" v-model="form.image_url" type="url" :class="input" /></Field>
                <Field label="Description (optional)" for="desc" :error="form.errors.description"><textarea id="desc" v-model="form.description" rows="3" maxlength="2000" :class="input" /></Field>
                <div class="flex gap-2">
                    <Btn type="submit" :disabled="form.processing">{{ product ? 'Save changes' : 'Create product' }}</Btn>
                    <Btn :href="product ? `/products/${product.id}` : '/products'" variant="secondary">Cancel</Btn>
                </div>
            </form>
        </Card>
    </AppLayout>
</template>
