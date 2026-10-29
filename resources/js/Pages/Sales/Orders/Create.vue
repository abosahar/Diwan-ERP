<script setup>
import DatePicker from '@/Components/UI/DatePicker.vue';
import SelectCreate from '@/Components/UI/SelectCreate.vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Field from '@/Components/UI/Field.vue';
import PartyPicker from '@/Components/Documents/PartyPicker.vue';
import LineItemsEditor from '@/Components/Documents/LineItemsEditor.vue';

const props = defineProps({ warehouses: Array, suggestedNumber: String, branches: Array, defaultBranchId: Number });
const today = new Date().toISOString().substring(0, 10);
const form = useForm({ customer_id: null, branch_id: props.defaultBranchId || props.branches?.[0]?.id || '', warehouse_id: '', order_date: today, delivery_date: '', notes: '', items: [] });
const save = () => form.post(route('sales.orders.store'));
</script>

<template>
    <AppLayout>
        <Head title="أمر بيع جديد" />
        <PageHeader title="أمر بيع جديد" :subtitle="`الرقم المتوقع: ${suggestedNumber}`" :crumbs="[{ label: 'المبيعات' }, { label: 'أوامر البيع', href: route('sales.orders.index') }, { label: 'جديد' }]" />
        <div class="bg-white border border-slate-200 rounded-2xl p-5 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <Field label="العميل" :error="form.errors.customer_id" class="md:col-span-2"><PartyPicker v-model="form.customer_id" /></Field>
                <Field label="تاريخ الأمر" :error="form.errors.order_date"><DatePicker v-model="form.order_date" class="w-full rounded-xl border-slate-200 text-xs" /></Field>
                <Field label="تاريخ التسليم المتوقع" :error="form.errors.delivery_date"><DatePicker v-model="form.delivery_date" class="w-full rounded-xl border-slate-200 text-xs" /></Field>
                <Field label="المستودع"><SelectCreate v-model="form.warehouse_id" :options="warehouses" entity="warehouse" label="name_ar" placeholder="الافتراضي" /></Field>
                <Field label="ملاحظات" class="md:col-span-3"><input v-model="form.notes" class="w-full rounded-xl border-slate-200 text-xs" /></Field>
            </div>
            <LineItemsEditor v-model="form.items" :customer-id="form.customer_id" price-route="lookup.price" />
            <p v-for="(msg, key) in form.errors" :key="key" class="text-xs text-rose-600">{{ msg }}</p>
            <div class="flex justify-end"><button @click="save" :disabled="form.processing || !form.items.length || !form.customer_id" class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold disabled:opacity-40">حفظ أمر البيع</button></div>
        </div>
    </AppLayout>
</template>
