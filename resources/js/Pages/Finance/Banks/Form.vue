<script setup>
import { useUnsavedGuard } from '@/composables/useUnsavedGuard';
import SelectCreate from '@/Components/UI/SelectCreate.vue';

import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';

const props = defineProps({ chartAccounts: Array, bank: { type: Object, default: null } });
const editing = props.bank;

const form = useForm({
    name: '',
    type: 'bank',
    bank_name: '',
    account_number: '',
    iban: '',
    currency: usePage().props.tenant?.currency || 'SAR',
    opening_balance: '0.00',
    account_id: props.chartAccounts?.[0]?.id || '',
});
// Editing: names and bank details only (the balance and ledger account follow the postings).
if (editing) {
    Object.assign(form, { name: editing.name, type: editing.type, bank_name: editing.bank_name || '', account_number: editing.account_number || '', iban: editing.iban || '', currency: editing.currency, opening_balance: editing.opening_balance, account_id: editing.account_id || '' });
    form.defaults();
}
useUnsavedGuard(form);

const submitAccount = () => (editing ? form.put(route('finance.banks.update', editing.id)) : form.post(route('finance.banks.store')));
</script>

<template>
    <AppLayout>
        <Head :title="editing ? `تعديل ${editing.name}` : 'حساب بنك أو صندوق جديد'" />
        <PageHeader :title="editing ? `تعديل ${editing.name}` : 'حساب بنك أو صندوق جديد'" :crumbs="[{ label: 'المالية' }, { label: 'البنوك والصناديق', href: route('finance.banks.index') }, { label: editing ? 'تعديل' : 'جديد' }]" />

        <div class="bg-white border border-slate-200/80 rounded-2xl p-6">
            <form @submit.prevent="submitAccount" class="form-grid text-xs">
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">اسم الحساب / الخزينة <span class="text-rose-500">*</span></label>
                                <input 
                                    v-model="form.name" 
                                    type="text" 
                                    required 
                                    placeholder="مثال: حساب مصرف الراجحي الرئيسي أو خزينة الرياض"
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-indigo-500 focus:border-indigo-500" 
                                />
                            </div>
            
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block font-medium text-slate-700 mb-1">النوع <span class="text-rose-500">*</span></label>
                                    <select 
                                        v-model="form.type" 
                                        :disabled="!!editing"
                                        class="w-full py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-indigo-500 focus:border-indigo-500"
                                    >
                                        <option value="bank">حساب بنكي</option>
                                        <option value="cash">خزينة نقدية (درج كاش)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-medium text-slate-700 mb-1">الرصيد الافتتاحي (﷼) <span class="text-rose-500">*</span></label>
                                    <input 
                                        v-model.number="form.opening_balance" 
                                        :disabled="!!editing"
                                        type="text" inputmode="decimal" data-num 
                                        step="0.01" 
                                        required 
                                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-mono focus:ring-indigo-500 focus:border-indigo-500 text-end" 
                                    />
                                </div>
                            </div>
            
                            <!-- If Bank -->
                            <div v-if="form.type === 'bank'" class="space-y-3 p-3 bg-sky-50/50 rounded-xl border border-sky-100">
                                <div>
                                    <label class="block font-medium text-slate-700 mb-1">اسم البنك</label>
                                    <input 
                                        v-model="form.bank_name" 
                                        type="text" 
                                        placeholder="مثال: مصرف الراجحي، بنك الرياض، الأهلي..."
                                        class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl focus:ring-indigo-500 focus:border-indigo-500" 
                                    />
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block font-medium text-slate-700 mb-1">رقم الحساب</label>
                                        <input 
                                            v-model="form.account_number" 
                                            type="text" 
                                            placeholder="رقم الحساب المصرفي"
                                            class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-mono focus:ring-indigo-500 focus:border-indigo-500" 
                                        />
                                    </div>
                                    <div>
                                        <label class="block font-medium text-slate-700 mb-1">رقم الآيبان (IBAN)</label>
                                        <input 
                                            v-model="form.iban" 
                                            type="text" 
                                            placeholder="SA......................"
                                            class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-mono focus:ring-indigo-500 focus:border-indigo-500" 
                                        />
                                    </div>
                                </div>
                            </div>
            
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">ربط مع حساب بدليل الحسابات (اختياري)</label>
                                <SelectCreate v-model="form.account_id" :options="chartAccounts" entity="account" :label="(acc) => `${acc.code} - ${acc.name_ar}`" placeholder="بدون ربط مباشر" />
                            </div>
            
                            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                                <Link :href="route('finance.banks.index')" class="px-4 py-2 border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 font-medium">إلغاء</Link>
                                <button 
                                    type="submit" 
                                    :disabled="form.processing"
                                    class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold shadow-xs hover:shadow-indigo-500/20 disabled:opacity-50"
                                >
                                    إضافة الحساب
                                </button>
                            </div>
                        </form>
        </div>
    </AppLayout>
</template>
