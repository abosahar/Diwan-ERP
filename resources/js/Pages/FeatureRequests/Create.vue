<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Field from '@/Components/UI/Field.vue';
import { useUnsavedGuard } from '@/composables/useUnsavedGuard';

defineProps({ kinds: Object });
const form = useForm({ kind: 'feature', area: '', title: '', description: '' });
useUnsavedGuard(form);
const input = 'w-full rounded-xl border-slate-200 text-xs';
</script>

<template>
    <AppLayout>
        <Head title="طلب جديد" />
        <PageHeader title="طلب ميزة أو تحسين" :crumbs="[{ label: 'التحديثات' }, { label: 'طلبات المميزات', href: route('feature-requests.index') }, { label: 'جديد' }]" />
        <form @submit.prevent="form.post(route('feature-requests.store'))" class="bg-white border border-slate-200 rounded-2xl p-5 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
            <Field label="نوع الطلب *" :error="form.errors.kind">
                <select v-model="form.kind" :class="input"><option v-for="(label, key) in kinds" :key="key" :value="key">{{ label }}</option></select>
            </Field>
            <Field label="الشاشة أو القسم" :error="form.errors.area" hint="مثل: فواتير المبيعات، الرواتب"><input v-model="form.area" :class="input" /></Field>
            <Field label="عنوان الطلب *" :error="form.errors.title" class="md:col-span-2"><input v-model="form.title" required maxlength="200" :class="input" /></Field>
            <Field label="الوصف *" :error="form.errors.description" hint="ما الذي تريده، ولماذا، وكيف تتخيل عمله؟" class="md:col-span-2"><textarea v-model="form.description" required rows="6" :class="input"></textarea></Field>
            <div class="md:col-span-2 flex gap-2 pt-2 border-t border-slate-100">
                <button type="submit" :disabled="form.processing" class="px-5 py-2 rounded-xl bg-indigo-600 text-white font-bold">إرسال الطلب</button>
                <Link :href="route('feature-requests.index')" class="px-5 py-2 rounded-xl border border-slate-200 font-bold text-slate-600">إلغاء</Link>
            </div>
        </form>
    </AppLayout>
</template>
