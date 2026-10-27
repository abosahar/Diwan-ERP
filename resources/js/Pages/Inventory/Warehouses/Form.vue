<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';

const props = defineProps({ warehouse: { type: Object, default: null }, branches: Array });

const editingWarehouse = props.warehouse?.id ? props.warehouse : null;

const form = useForm({
    branch_id: props.warehouse?.branch_id || props.branches?.[0]?.id || '',
    name_ar: props.warehouse?.name_ar || '',
    name_en: props.warehouse?.name_en || '',
    code: props.warehouse?.code || '',
    phone: props.warehouse?.phone || '',
    address: props.warehouse?.address || '',
    is_main: !!props.warehouse?.is_main,
});

const submitWarehouse = () => (editingWarehouse
    ? form.put(route('inventory.warehouses.update', editingWarehouse.id))
    : form.post(route('inventory.warehouses.store')));
</script>

<template>
    <AppLayout>
        <Head :title="props.warehouse?.id ? 'تعديل مستودع' : 'مستودع جديد'" />
        <PageHeader :title="props.warehouse?.id ? 'تعديل مستودع' : 'مستودع جديد'" :crumbs="[{ label: 'المخزون' }, { label: 'المستودعات', href: route('inventory.warehouses.index') }, { label: props.warehouse?.id ? 'تعديل' : 'جديد' }]" />

        <div class="bg-white border border-slate-200/80 rounded-2xl p-6">
            <form @submit.prevent="submitWarehouse" class="form-grid text-xs">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="col-span-2">
                                    <label class="block text-xs font-bold text-slate-700 mb-1">اسم المستودع (بالعربي) *</label>
                                    <input 
                                        v-model="form.name_ar" 
                                        type="text" 
                                        required
                                        placeholder="مثال: مستودع الرياض المركزي"
                                        class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600"
                                    />
                                    <p v-if="form.errors.name_ar" class="text-[11px] text-rose-600 mt-1">{{ form.errors.name_ar }}</p>
                                </div>
            
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">الاسم (بالإنجليزي)</label>
                                    <input 
                                        v-model="form.name_en" 
                                        type="text" 
                                        placeholder="Warehouse Name"
                                        class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600"
                                    />
                                </div>
            
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">كود المستودع *</label>
                                    <input 
                                        v-model="form.code" 
                                        type="text" 
                                        required
                                        placeholder="WH-01"
                                        class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600 uppercase font-mono"
                                    />
                                    <p v-if="form.errors.code" class="text-[11px] text-rose-600 mt-1">{{ form.errors.code }}</p>
                                </div>
            
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">الفرع المرتبط</label>
                                    <select 
                                        v-model="form.branch_id"
                                        class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600"
                                    >
                                        <option value="">-- اختياري --</option>
                                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name_ar }}</option>
                                    </select>
                                </div>
            
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">هاتف المستودع / المسؤول</label>
                                    <input 
                                        v-model="form.phone" 
                                        type="text" 
                                        placeholder="0500000000"
                                        class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600 font-mono"
                                    />
                                </div>
            
                                <div class="col-span-2">
                                    <label class="block text-xs font-bold text-slate-700 mb-1">العنوان أو الموقع</label>
                                    <input 
                                        v-model="form.address" 
                                        type="text" 
                                        placeholder="المنطقة الصناعية الثانية، الرياض"
                                        class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600"
                                    />
                                </div>
            
                                <div class="col-span-2 pt-2">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input 
                                            v-model="form.is_main" 
                                            type="checkbox" 
                                            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-600"
                                        />
                                        <span class="text-xs font-bold text-slate-700">تعيين كمستودع رئيسي للمنشأة</span>
                                    </label>
                                </div>
                            </div>
            
                            <div class="mt-6 flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                                <Link :href="route('inventory.warehouses.index')" class="px-4 py-2 border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 font-medium">إلغاء</Link>
                                <button 
                                    type="submit" 
                                    :disabled="form.processing"
                                    class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition disabled:opacity-50"
                                >
                                    {{ editingWarehouse ? 'حفظ التعديلات' : 'إضافة المستودع' }}
                                </button>
                            </div>
                        </form>
        </div>
    </AppLayout>
</template>
