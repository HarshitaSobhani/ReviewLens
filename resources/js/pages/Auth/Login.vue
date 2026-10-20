<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Btn from '@/components/Btn.vue';
import Field from '@/components/Field.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
const input = 'block w-full rounded-lg border-0 px-3 py-2 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-600';
</script>

<template>
    <Head title="Log in" />
    <PublicLayout>
        <div class="mx-auto max-w-sm px-4 py-16">
            <h1 class="text-2xl font-semibold text-slate-900">Log in</h1>
            <p class="mt-1 text-sm text-slate-600">New here? <Link href="/register" class="font-medium text-brand-700 hover:underline">Create an account</Link></p>
            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <Field label="Email" for="email" :error="form.errors.email"><input id="email" v-model="form.email" type="email" autocomplete="email" required :class="input" /></Field>
                <Field label="Password" for="password" :error="form.errors.password"><input id="password" v-model="form.password" type="password" autocomplete="current-password" required :class="input" /></Field>
                <label class="flex items-center gap-2 text-sm text-slate-700"><input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-brand-600" /> Remember me</label>
                <Btn type="submit" class="w-full" :disabled="form.processing">{{ form.processing ? 'Signing in…' : 'Log in' }}</Btn>
            </form>
        </div>
    </PublicLayout>
</template>
