<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { amount } from '@/utils/format';

const props = defineProps({ rows: Array, filters: Object });
const f = reactive({ year: props.filters.year, month: props.filters.month });
const months = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
const years = Array.from({ length: 6 }, (_, i) => new Date().getFullYear() - i);
const apply = () => router.get(route('sales.commissions.index'), { ...f }, { preserveState: true, replace: true });
const post = (r) => {
    if (confirm(`ترحيل عمولة ${r.name} (${amount(r.total)}) إلى مسير رواتب ${months[f.month - 1]} ${f.year}؟`)) {
        router.post(route('sales.commissions.post'), { user_id: r.user_id, year: f.year, month: f.month }, { preserveScroll: true });
    }
};
</script>

<template>
    <AppLayout>
        <Head title="عمولات المبيعات" />
        <PageHeader title="عمولات المبيعات" subtitle="صافي مبيعات كل مندوب بعد المرتجعات، ثم العمولة وفق خطته" :crumbs="[{ label: 'المبيعات' }, { label: 'عمولات المبيعات' }]">
            <template #actions>
                <Link v-if="$can('sales.commissions.view')" :href="route('sales.commission-plans.index')" class="px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700">خطط العمولات</Link>
            </template>
        </PageHeader>
        <div class="bg-white border border-slate-200 rounded-2xl p-3 mb-4 flex gap-2 text-xs">
            <select v-model.number="f.month" @change="apply" class="rounded-xl border-slate-200 text-xs"><option v-for="(m, i) in months" :key="i" :value="i + 1">{{ m }}</option></select>
            <select v-model.number="f.year" @change="apply" class="rounded-xl border-slate-200 text-xs"><option v-for="y in years" :key="y" :value="y">{{ y }}</option></select>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500"><tr><th class="px-4 py-2 text-start">المندوب</th><th class="px-4 py-2 text-start">الخطة</th><th class="px-4 py-2 text-end">المبيعات</th><th class="px-4 py-2 text-end">المرتجعات</th><th class="px-4 py-2 text-end">الصافي</th><th class="px-4 py-2 text-end">العمولة</th><th class="px-4 py-2 text-end">مكافأة الهدف</th><th class="px-4 py-2 text-end">الإجمالي</th><th class="px-4 py-2"></th></tr></thead>
                <tbody>
                    <tr v-for="r in rows" :key="r.user_id" class="border-t border-slate-100" data-commission-row>
                        <td class="px-4 py-2 font-bold">{{ r.name }}</td>
                        <td class="px-4 py-2 text-slate-500">{{ r.plan || '—' }}</td>
                        <td class="px-4 py-2 text-end font-mono">{{ amount(r.sales) }}</td>
                        <td class="px-4 py-2 text-end font-mono text-rose-600">{{ amount(r.returns) }}</td>
                        <td class="px-4 py-2 text-end font-mono font-bold">{{ amount(r.net) }}</td>
                        <td class="px-4 py-2 text-end font-mono">{{ amount(r.commission) }}</td>
                        <td class="px-4 py-2 text-end font-mono text-emerald-600">{{ amount(r.bonus) }}</td>
                        <td class="px-4 py-2 text-end font-mono font-black text-indigo-700">{{ amount(r.total) }}</td>
                        <td class="px-4 py-2 text-end whitespace-nowrap">
                            <span v-if="r.posted" class="text-emerald-600 font-bold">✓ رُحّلت للرواتب</span>
                            <button v-else-if="r.total > 0 && $can('sales.commissions.post')" @click="post(r)" class="text-indigo-600 font-bold" :title="r.employee_id ? '' : 'المستخدم غير مرتبط بموظف'">ترحيل للرواتب</button>
                        </td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="9" class="py-10 text-center text-slate-400">لا مبيعات لمندوبين في هذا الشهر.</td></tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
