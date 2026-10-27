<script setup>
import { computed, reactive } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatusPill from '@/Components/Mfg/StatusPill.vue';
import Field from '@/Components/UI/Field.vue';

const props = defineProps({ inspection: Object, source: String });
const i = computed(() => props.inspection);
const checks = computed(() => i.value.plan?.checks || []);
const num = (v) => Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 4 });
const values = reactive({});
const form = useForm({ accepted: i.value.inspected_quantity, rejected: 0, rework: 0, disposition: 'accept', values: {}, notes: '' });
const sum = computed(() => Number(form.accepted || 0) + Number(form.rejected || 0) + Number(form.rework || 0));
const submit = () => { form.values = { ...values }; form.post(route('manufacturing.inspections.complete', i.value.id)); };
const input = 'w-full text-xs rounded-xl border-slate-200';
const card = 'bg-white border border-slate-200/80 rounded-2xl p-5';
</script>

<template>
    <AppLayout>
        <Head :title="`فحص ${i.inspection_number}`" />
        <PageHeader :title="`فحص ${i.inspection_number}`" :subtitle="i.product?.name_ar" :crumbs="[{ label: 'التصنيع' }, { label: 'فحوصات الجودة', href: route('manufacturing.inspections.index') }, { label: i.inspection_number }]">
            <template #actions><StatusPill :status="i.result" /></template>
        </PageHeader>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div :class="card"><p class="text-3xs font-bold text-slate-400">المفحوص</p><p class="text-xl font-black font-mono">{{ num(i.inspected_quantity) }}</p></div>
            <div :class="card"><p class="text-3xs font-bold text-slate-400">مقبول</p><p class="text-xl font-black font-mono text-emerald-600">{{ num(i.accepted_quantity) }}</p></div>
            <div :class="card"><p class="text-3xs font-bold text-slate-400">مرفوض</p><p class="text-xl font-black font-mono text-rose-600">{{ num(i.rejected_quantity) }}</p></div>
            <div :class="card"><p class="text-3xs font-bold text-slate-400">إعادة تشغيل</p><p class="text-xl font-black font-mono">{{ num(i.rework_quantity) }}</p></div>
        </div>

        <div v-if="i.result !== 'pending'" :class="card">
            <h2 class="font-black text-slate-800 text-sm mb-3">نتائج البنود</h2>
            <p v-if="!(i.results || []).length" class="text-xs text-slate-400">لم تُسجَّل بنود.</p>
            <table v-else class="w-full text-xs"><tbody><tr v-for="(r, k) in i.results" :key="k" class="border-t border-slate-100"><td class="p-2 font-bold">{{ r.name }}</td><td class="p-2 font-mono">{{ r.value }}</td><td class="p-2"><span :class="r.passed ? 'text-emerald-600' : 'text-rose-600'" class="font-bold">{{ r.passed ? 'مطابق' : 'غير مطابق' }}</span></td></tr></tbody></table>
            <p v-if="i.notes" class="text-xs text-slate-500 mt-3">{{ i.notes }}</p>
        </div>

        <form v-else-if="$can('manufacturing.quality.manage')" @submit.prevent="submit" :class="[card, 'space-y-5']">
            <div v-if="checks.length">
                <h2 class="font-black text-slate-800 text-sm mb-3">بنود الفحص ({{ i.plan.name }})</h2>
                <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-3">
                    <Field v-for="(c, k) in checks" :key="k" :label="c.type === 'numeric' ? `${c.name} (${c.min ?? '—'} → ${c.max ?? '—'})` : c.name">
                        <input v-if="c.type === 'numeric'" v-model="values[k]" type="number" step="any" :class="input" />
                        <select v-else v-model="values[k]" :class="input"><option value="">—</option><option value="pass">سليم</option><option value="fail">معيب</option></select>
                    </Field>
                </div>
            </div>
            <div class="grid md:grid-cols-4 gap-3">
                <Field label="مقبول" :error="form.errors.accepted"><input v-model="form.accepted" type="number" step="0.0001" :class="input" /></Field>
                <Field label="مرفوض"><input v-model="form.rejected" type="number" step="0.0001" :class="input" /></Field>
                <Field label="إعادة تشغيل"><input v-model="form.rework" type="number" step="0.0001" :class="input" /></Field>
                <Field label="تصرف المرفوض"><select v-model="form.disposition" :class="input"><option value="accept">لا يوجد مرفوض</option><option value="scrap">إتلاف (يُحمَّل خسارة)</option><option value="rework">إعادة تشغيل</option><option value="return">إرجاع للمورد</option></select></Field>
            </div>
            <p class="text-xs" :class="Math.abs(sum - Number(i.inspected_quantity)) < 0.0001 ? 'text-slate-500' : 'text-rose-600'">المجموع {{ num(sum) }} من {{ num(i.inspected_quantity) }} مفحوصة.</p>
            <Field label="ملاحظات"><textarea v-model="form.notes" rows="2" :class="input"></textarea></Field>
            <p v-if="source === 'ProductionReceipt'" class="text-xs text-indigo-700 bg-indigo-50 rounded-xl p-3">فحص استلام إنتاج: يدخل المقبول المخزون بتكلفته ويُسجَّل الإتلاف خسارة، وما أُعيد تشغيله يبقى تحت التشغيل.</p>
            <button type="submit" :disabled="form.processing || Math.abs(sum - Number(i.inspected_quantity)) > 0.0001" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold disabled:opacity-40">اعتماد نتيجة الفحص</button>
        </form>
    </AppLayout>
</template>
