<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';

const props = defineProps({ roles: Array, mapping: Object, accounts: Array, dimensionPolicy: Object });
const dimForm = useForm({ cost_center: props.dimensionPolicy?.cost_center || 'optional', project: props.dimensionPolicy?.project || 'optional' });
const saveDimensions = () => dimForm.put(route('accounting.mappings.dimensions'), { preserveScroll: true });
const modes = [
    { value: 'optional', label: 'اختياري', hint: 'يستطيع المستخدم إدخاله لكنه غير ملزم بذلك.' },
    { value: 'required', label: 'إجباري', hint: 'يجب تحديده في كل سطر توزيع قبل الترحيل.' },
    { value: 'pnl', label: 'أرباح وخسائر', hint: 'يصبح إجباريًا فقط عندما يكون الحساب المختار من حسابات قائمة الأرباح والخسائر (إيرادات / مصروفات).' },
];
const form = useForm({ mapping: { ...props.mapping } });

const typeLabels = { asset: 'الأصول', liability: 'الخصوم', equity: 'حقوق الملكية', revenue: 'الإيرادات', expense: 'المصروفات' };
const grouped = computed(() => Object.keys(typeLabels).map((type) => ({ type, label: typeLabels[type], roles: props.roles.filter((r) => r.type === type) })));
const accountsFor = (type) => props.accounts.filter((a) => a.type === type);
const save = () => form.put(route('accounting.mappings.update'), { preserveScroll: true });
</script>

<template>
    <AppLayout>
        <Head title="ربط الحسابات" />
        <PageHeader title="ربط الحسابات الآلي" subtitle="الحسابات التي يرحّل عليها النظام قيود الفواتير والسندات والرواتب والمخزون تلقائياً" :crumbs="[{ label: 'المحاسبة' }, { label: 'ربط الحسابات' }]">
            <template #actions>
                <button v-if="$can('accounting.mappings.edit')" @click="save" :disabled="form.processing" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold">{{ $t('ui.save') }}</button>
            </template>
        </PageHeader>

        <div class="bg-white border border-slate-200 rounded-2xl p-4 mb-4" data-dimension-policy>
            <div class="flex items-start justify-between gap-3 mb-3">
                <div>
                    <h3 class="font-black text-sm text-slate-800">سياسة مراكز التكلفة والمشاريع</h3>
                    <p class="text-xs text-slate-500 mt-1">مركز التكلفة والمشروع أبعاد تحليلية تبيّن أين صُرف المبلغ ولأي مشروع، لا حسابات محاسبية. تُطبَّق السياسة على <b>سطور التوزيع</b> التي يختارها المستخدم (القيود اليدوية وسندات الصرف والقبض)، ولا تُلزم سطر البنك أو الصندوق الآلي ولا الأرصدة الافتتاحية ولا مستندات النظام. العمليات المرحَّلة سابقًا لا تتأثر بتغيير السياسة.</p>
                </div>
                <button v-if="$can('accounting.mappings.edit')" @click="saveDimensions" :disabled="dimForm.processing" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold shrink-0">حفظ السياسة</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div v-for="d in [{ key: 'cost_center', label: 'مركز التكلفة' }, { key: 'project', label: 'المشروع' }]" :key="d.key">
                    <label class="block font-bold text-slate-700 mb-1">{{ d.label }} في العمليات</label>
                    <select v-model="dimForm[d.key]" :disabled="!$can('accounting.mappings.edit')" class="w-full rounded-xl border-slate-200 text-xs">
                        <option v-for="m in modes" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                    <p class="text-slate-500 mt-1">{{ modes.find((m) => m.value === dimForm[d.key])?.hint }}</p>
                    <p v-if="dimForm.errors[d.key]" class="text-rose-600">{{ dimForm.errors[d.key] }}</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-3">توصية: مركز التكلفة «أرباح وخسائر» والمشروع «اختياري» في البداية، ثم «أرباح وخسائر» للمشروع إن صارت متابعة ربحية المشاريع إلزامية. وخيار «يتطلب مركز تكلفة» على الحساب نفسه يبقى إلزاميًا دائمًا.</p>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <div v-for="group in grouped" :key="group.type" class="bg-white border border-slate-200 rounded-2xl p-4">
                <h3 class="font-black text-sm text-slate-800 mb-3">{{ group.label }}</h3>
                <div class="space-y-2">
                    <div v-for="role in group.roles" :key="role.key" class="grid grid-cols-5 items-center gap-2 text-xs">
                        <span class="col-span-2 font-semibold text-slate-700">{{ role.label }}</span>
                        <select v-model="form.mapping[role.key]" :disabled="!$can('accounting.mappings.edit')" class="col-span-3 rounded-xl border-slate-200 text-xs">
                            <option :value="null">— غير مربوط —</option>
                            <option v-for="a in accountsFor(group.type)" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name_ar }}</option>
                        </select>
                        <p v-if="form.errors[`mapping.${role.key}`]" class="col-span-5 text-rose-600">{{ form.errors[`mapping.${role.key}`] }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
