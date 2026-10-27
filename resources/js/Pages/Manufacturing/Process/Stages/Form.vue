<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Field from '@/Components/UI/Field.vue';

const props = defineProps({ stage: Object, products: Array, stages: Array, warehouses: Array, workCenters: Array });
const editing = !!props.stage?.id;
const s = props.stage;
const form = useForm({
    code: s.code || '', name: s.name || '', sequence: s.sequence ?? 1, product_id: s.product_id || '', previous_stage_id: s.previous_stage_id || '',
    warehouse_id: s.warehouse_id || props.warehouses?.[0]?.id || '', work_center_id: s.work_center_id || '', is_active: s.is_active ?? true,
});
const submit = () => (editing ? form.put(route('manufacturing.stages.update', s.id)) : form.post(route('manufacturing.stages.store')));
const input = 'w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600';
</script>

<template>
    <AppLayout>
        <Head :title="editing ? 'تعديل مرحلة' : 'مرحلة جديدة'" />
        <PageHeader :title="editing ? 'تعديل مرحلة' : 'مرحلة تصنيع جديدة'" :crumbs="[{ label: 'التصنيع' }, { label: 'المراحل', href: route('manufacturing.stages.index') }, { label: editing ? 'تعديل' : 'جديدة' }]" />
        <form @submit.prevent="submit" class="bg-white border border-slate-200/80 rounded-2xl p-6 space-y-5">
            <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
                <Field label="الرمز *" :error="form.errors.code"><input v-model="form.code" :class="input" class="font-mono" required /></Field>
                <Field label="الاسم *" :error="form.errors.name"><input v-model="form.name" :class="input" required /></Field>
                <Field label="التسلسل *"><input v-model="form.sequence" type="number" :class="input" /></Field>
                <Field label="ناتج المرحلة *" hint="للمرحلة الأخيرة: المنتج التام الذي يدخل المخزون" :error="form.errors.product_id"><select v-model="form.product_id" :class="input" required><option value="">— اختر —</option><option v-for="p in products" :key="p.id" :value="p.id">{{ p.name_ar }}</option></select></Field>
                <Field label="تستلم من مرحلة" hint="تنتقل تكلفة المرحلة السابقة إليها"><select v-model="form.previous_stage_id" :class="input"><option value="">— أول مرحلة —</option><option v-for="x in stages" :key="x.id" :value="x.id">{{ x.code }} — {{ x.name }}</option></select></Field>
                <Field label="المستودع *" :error="form.errors.warehouse_id"><select v-model="form.warehouse_id" :class="input" required><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name_ar }}</option></select></Field>
                <Field label="مركز العمل"><select v-model="form.work_center_id" :class="input"><option value="">—</option><option v-for="w in workCenters" :key="w.id" :value="w.id">{{ w.code }} — {{ w.name }}</option></select></Field>
            </div>
            <label class="inline-flex items-center gap-2 text-xs"><input v-model="form.is_active" type="checkbox" class="rounded" /> نشطة</label>
            <div class="flex gap-2 pt-2 border-t border-slate-100">
                <button type="submit" :disabled="form.processing" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold disabled:opacity-50">حفظ</button>
                <Link :href="route('manufacturing.stages.index')" class="px-6 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600">إلغاء</Link>
            </div>
        </form>
    </AppLayout>
</template>
