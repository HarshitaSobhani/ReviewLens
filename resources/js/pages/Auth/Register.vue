<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Btn from '@/components/Btn.vue';
import Field from '@/components/Field.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });
const submit = () => form.post('/register', { onFinish: () => form.reset('password', 'password_confirmation') });
const input = 'block w-full rounded-lg border-0 px-3 py-2 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-600';
</script>

<template>
    <Head title="Create account" />
    <PublicLayout>
        <div class="mx-auto max-w-sm px-4 py-16">
            <h1 class="text-2xl font-semibold text-slate-900">Create your workspace</h1>
            <p class="mt-1 text-sm text-slate-600">Already registered? <Link href="/login" class="font-medium text-brand-700 hover:underline">Log in</Link></p>
            <p class="mt-3 text-xs text-slate-500">Your products, reviews and analyses are private to your account.</p>
            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <Field label="Name" for="name" :error="form.errors.name"><input id="name" v-model="form.name" autocomplete="name" required :class="input" /></Field>
                <Field label="Email" for="email" :error="form.errors.email"><input id="email" v-model="form.email" type="email" autocomplete="email" required :class="input" /></Field>
                <Field label="Password" for="password" hint="At least 8 characters." :error="form.errors.password"><input id="password" v-model="form.password" type="password" autocomplete="new-password" required :class="input" /></Field>
                <Field label="Confirm password" for="pc"><input id="pc" v-model="form.password_confirmation" type="password" autocomplete="new-password" required :class="input" /></Field>
                <Btn type="submit" class="w-full" :disabled="form.processing">{{ form.processing ? 'Creating…' : 'Create account' }}</Btn>
            </form>
        </div>
    </PublicLayout>
</template>
