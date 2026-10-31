<script setup>
import { useUnsavedGuard } from '@/composables/useUnsavedGuard';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';

const createForm = useForm({
    name: '',
    mode: 'freeform',
    template_type: 'invoice',
    primary_color: '#1e293b',
});
useUnsavedGuard(createForm);
// Free-form templates exist for every printable document kind.
const kinds = { invoice: 'فاتورة مبيعات', quotation: 'عرض سعر', sales_order: 'أمر بيع', credit_note: 'إشعار دائن / مرتجع', debit_note: 'إشعار مدين', receipt: 'سند قبض', payment: 'سند صرف لمورد' };
const setMode = (mode) => { createForm.mode = mode; createForm.template_type = mode === 'freeform' ? 'invoice' : 'invoice_a4'; };

const submitCreate = () => createForm.post(route('templates.store'));
</script>

<template>
    <AppLayout>
        <Head title="قالب طباعة جديد" />
        <PageHeader title="قالب طباعة جديد" :crumbs="[{ label: 'الإدارة' }, { label: 'قوالب الطباعة', href: route('templates.index') }, { label: 'جديد' }]" />

        <div class="bg-white border border-slate-200/80 rounded-2xl p-6">
            <form @submit.prevent="submitCreate" class="form-grid text-xs">
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">اسم القالب</label>
                                <input 
                                    v-model="createForm.name" 
                                    type="text" 
                                    required 
                                    placeholder="مثال: قالب الفاتورة الهندسية الحديثة" 
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl"
                                />
                            </div>
            
                            <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="طريقة التصميم">
                                <button type="button" role="radio" :aria-checked="createForm.mode === 'freeform'" @click="setMode('freeform')" :class="['p-3 rounded-xl border text-start', createForm.mode === 'freeform' ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200']">
                                    <span class="block font-black text-slate-800">تصميم حر</span>
                                    <span class="block text-slate-500 mt-1">ضع أي عنصر في أي مكان بالسحب والإفلات</span>
                                </button>
                                <button type="button" role="radio" :aria-checked="createForm.mode === 'quick'" @click="setMode('quick')" :class="['p-3 rounded-xl border text-start', createForm.mode === 'quick' ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200']">
                                    <span class="block font-black text-slate-800">تصميم سريع</span>
                                    <span class="block text-slate-500 mt-1">أقسام جاهزة تختار ما يظهر منها</span>
                                </button>
                            </div>
            
                            <div v-if="createForm.mode === 'freeform'">
                                <label class="block font-medium text-slate-700 mb-1" for="ff-kind">المستند</label>
                                <select id="ff-kind" v-model="createForm.template_type" class="w-full py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800">
                                    <option v-for="(l, k) in kinds" :key="k" :value="k">{{ l }}</option>
                                </select>
                                <p v-if="createForm.errors.template_type" class="text-rose-600 mt-1">{{ createForm.errors.template_type }}</p>
                                <p class="text-slate-400 mt-1">يبدأ بتصميم جاهز يمكنك تغيير كل شيء فيه.</p>
                            </div>
            
                            <div v-else>
                                <label class="block font-medium text-slate-700 mb-1">نوع المستند</label>
                                <select 
                                    v-model="createForm.template_type" 
                                    class="w-full py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800"
                                >
                                    <option value="invoice_a4">فاتورة ضريبية قياسية (A4 ZATCA)</option>
                                    <option value="invoice_thermal_80mm">إيصال حراري كاشير (80mm POS)</option>
                                    <option value="quotation">عرض أسعار رسمي (Quotation A4)</option>
                                    <option value="voucher">سندات مالية (Voucher)</option>
                                </select>
                            </div>
            
                            <div v-if="createForm.mode === 'quick'">
                                <label class="block font-medium text-slate-700 mb-1">اللون الرئيسي المميز</label>
                                <div class="flex items-center gap-3">
                                    <input 
                                        v-model="createForm.primary_color" 
                                        type="color" 
                                        class="w-10 h-10 rounded-xl border border-slate-200 cursor-pointer p-1"
                                    />
                                    <input 
                                        v-model="createForm.primary_color" 
                                        type="text" 
                                        class="w-32 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-mono text-xs"
                                    />
                                </div>
                            </div>
            
                            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                                <Link :href="route('templates.index')" class="px-4 py-2 border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 font-medium">إلغاء</Link>
                                <button 
                                    type="submit" 
                                    :disabled="createForm.processing"
                                    class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold flex items-center gap-1.5 disabled:opacity-50"
                                >
                                    <CheckIcon class="w-4 h-4 stroke-2" />
                                    <span>إنشاء وفتح المصمم</span>
                                </button>
                            </div>
                        </form>
        </div>
    </AppLayout>
</template>
