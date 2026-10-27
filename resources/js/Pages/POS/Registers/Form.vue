<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Field from '@/Components/UI/Field.vue';

const props = defineProps({ register: Object, warehouses: Array, boxes: Array });

const editing = !!props.register?.id;
const form = useForm({
    name: props.register?.name || '',
    code: props.register?.code || '',
    warehouse_id: props.register?.warehouse_id || props.warehouses?.[0]?.id || '',
    bank_account_id: props.register?.bank_account_id || '',
    is_active: props.register?.is_active ?? true,
});

const submit = () => (editing ? form.put(route('pos.registers.update', props.register.id)) : form.post(route('pos.registers.store')));
const input = 'w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600';
</script>

<template>
    <AppLayout>
        <Head :title="editing ? 'تعديل جهاز' : 'إضافة جهاز'" />
        <PageHeader :title="editing ? 'تعديل جهاز كاشير' : 'إضافة جهاز كاشير'" :crumbs="[{ label: 'نقاط البيع' }, { label: 'الأجهزة', href: route('pos.registers.index') }, { label: editing ? 'تعديل' : 'إضافة' }]" />

        <form @submit.prevent="submit" class="bg-white border border-slate-200/80 rounded-2xl p-6 space-y-5">
            <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
                <Field label="اسم الجهاز *" :error="form.errors.name"><input v-model="form.name" :class="input" placeholder="كاشير 1" required /></Field>
                <Field label="الرمز" :error="form.errors.code"><input v-model="form.code" :class="input" class="font-mono" placeholder="POS-01" /></Field>
                <Field label="المستودع (يُخصم منه المخزون) *" :error="form.errors.warehouse_id">
                    <select v-model="form.warehouse_id" :class="input" required>
                        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name_ar }}</option>
                    </select>
                </Field>
                <Field label="صندوق النقد" hint="الصندوق الذي تدخله مبيعات النقد" :error="form.errors.bank_account_id">
                    <select v-model="form.bank_account_id" :class="input">
                        <option value="">— الصندوق الافتراضي —</option>
                        <option v-for="b in boxes" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </Field>
            </div>
            <label class="inline-flex items-center gap-2 text-xs"><input v-model="form.is_active" type="checkbox" class="rounded" /> الجهاز يعمل</label>
            <div class="flex gap-2 pt-2 border-t border-slate-100">
                <button type="submit" :disabled="form.processing" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold disabled:opacity-50">حفظ</button>
                <Link :href="route('pos.registers.index')" class="px-6 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600">إلغاء</Link>
            </div>
        </form>
    </AppLayout>
</template>
