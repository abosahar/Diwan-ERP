<script setup>
import RowActions from '@/Components/UI/RowActions.vue';
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ groups: Array, total: Number, active: Number, filters: Object });
const f = reactive({ search: props.filters.search || '', status: props.filters.status || 'active' });
const apply = () => router.get(route('sales.customer-groups.index'), { search: f.search || undefined, status: f.status }, { preserveState: true, replace: true });
</script>

<template>
    <AppLayout>
        <Head title="مجموعات العملاء" />
        <PageHeader title="مجموعات العملاء" :subtitle="`${total} مجموعة (${active} نشطة)`" :crumbs="[{ label: 'المبيعات' }, { label: 'مجموعات العملاء' }]">
            <template #actions>
                <Link v-if="$can('sales.customer_groups.create')" :href="route('sales.customer-groups.create')" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold"><PlusIcon class="w-4 h-4" /> إضافة مجموعة جديدة</Link>
            </template>
        </PageHeader>

        <div class="bg-white border border-slate-200 rounded-2xl p-3 mb-4 flex flex-wrap gap-2 text-xs">
            <input v-model="f.search" @input="apply" placeholder="بحث باسم المجموعة أو رمزها..." class="flex-1 min-w-[12rem] rounded-xl border-slate-200 text-xs" />
            <select v-model="f.status" @change="apply" class="rounded-xl border-slate-200 text-xs"><option value="active">نشطة فقط</option><option value="inactive">معطلة فقط</option><option value="all">الكل</option></select>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500"><tr>
                    <th class="px-4 py-2 text-start">رمز المجموعة</th><th class="px-4 py-2 text-start">اسم المجموعة</th><th class="px-4 py-2 text-start">الحساب المحاسبي المرتبط</th>
                    <th class="px-4 py-2 text-start">عدد العملاء</th><th class="px-4 py-2 text-start">الوصف</th><th class="px-4 py-2 text-start">الحالة</th><th class="px-4 py-2 w-16"></th>
                </tr></thead>
                <tbody>
                    <tr v-for="g in groups" :key="g.id" class="border-t border-slate-100">
                        <td class="px-4 py-2 font-mono font-bold text-indigo-600">{{ g.code }}</td>
                        <td class="px-4 py-2 font-bold">{{ g.name }}</td>
                        <td class="px-4 py-2"><template v-if="g.account"><span class="font-mono font-bold text-indigo-600">{{ g.account.code }}</span> <span class="text-slate-500">{{ g.account.name_ar }}</span></template><span v-else class="text-slate-400">الذمم العامة</span></td>
                        <td class="px-4 py-2"><span class="px-2 py-0.5 rounded-full bg-violet-50 text-violet-700 border border-violet-100 font-bold">{{ g.customers_count }} عميل ({{ g.active_customers_count }} نشط)</span></td>
                        <td class="px-4 py-2 text-slate-500">{{ g.description }}</td>
                        <td class="px-4 py-2"><span :class="g.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'" class="px-2 py-0.5 rounded-full border text-[11px] font-bold">{{ g.is_active ? 'نشطة' : 'معطلة' }}</span></td>
                        <td class="px-4 py-2 text-end"><RowActions :edit="$can('sales.customer_groups.edit') ? route('sales.customer-groups.edit', g.id) : null" :remove="$can('sales.customer_groups.delete') && !g.customers_count ? route('sales.customer-groups.destroy', g.id) : null" :confirm="`حذف المجموعة ${g.name}؟`" /></td>
                    </tr>
                    <tr v-if="!groups.length"><td colspan="7" class="py-10 text-center text-slate-400">لا توجد مجموعات.</td></tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
