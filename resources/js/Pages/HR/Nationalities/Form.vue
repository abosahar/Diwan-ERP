<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Field from '@/Components/UI/Field.vue';
import { useUnsavedGuard } from '@/composables/useUnsavedGuard';

const props = defineProps({ nationality: Object });
const editing = props.nationality?.id;
const form = useForm({
    code: props.nationality?.code || '',
    name_ar: props.nationality?.name_ar || '',
    name_en: props.nationality?.name_en || '',
    is_active: props.nationality ? !!props.nationality.is_active : true,
});
useUnsavedGuard(form);
const submit = () => (editing ? form.put(route('hr.nationalities.update', editing)) : form.post(route('hr.nationalities.store')));
const input = 'w-full rounded-xl border-slate-200 text-xs';
</script>

<template>
    <AppLayout>
        <Head :title="editing ? 'تعديل جنسية' : 'جنسية جديدة'" />
        <PageHeader :title="editing ? 'تعديل جنسية' : 'جنسية جديدة'" :crumbs="[{ label: 'الموارد البشرية' }, { label: 'الجنسيات', href: route('hr.nationalities.index') }, { label: editing ? 'تعديل' : 'جديدة' }]" />
        <form @submit.prevent="submit" class="bg-white border border-slate-200 rounded-2xl p-5 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
            <Field label="الجنسية بالعربي *" :error="form.errors.name_ar"><input v-model="form.name_ar" required :class="input" placeholder="مصري" /></Field>
            <Field label="بالإنجليزي" :error="form.errors.name_en"><input v-model="form.name_en" :class="input" placeholder="Egyptian" /></Field>
            <Field label="رمز الدولة ISO2 *" :error="form.errors.code" hint="حرفان مثل EG"><input v-model="form.code" required maxlength="2" :class="input" class="uppercase font-mono" /></Field>
            <label class="flex items-center gap-2 font-bold text-slate-700 pt-6"><input type="checkbox" v-model="form.is_active" class="rounded" /> نشطة (تظهر في قوائم الاختيار)</label>
            <div class="md:col-span-2 flex gap-2 pt-2 border-t border-slate-100">
                <button type="submit" :disabled="form.processing" class="px-5 py-2 rounded-xl bg-indigo-600 text-white font-bold">{{ editing ? 'حفظ' : 'إضافة' }}</button>
                <Link :href="route('hr.nationalities.index')" class="px-5 py-2 rounded-xl border border-slate-200 font-bold text-slate-600">إلغاء</Link>
            </div>
        </form>
    </AppLayout>
</template>
