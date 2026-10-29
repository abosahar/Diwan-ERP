<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { date } from '@/utils/format';
import { PencilSquareIcon, PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ promotions: Array, types: Object, documents: Object, filters: Object });
const setStatus = (e) => router.get(route('sales.promotions.index'), { status: e.target.value }, { preserveState: true, replace: true });
const remove = (p) => { if (confirm(`حذف العرض «${p.name}»؟`)) router.delete(route('sales.promotions.destroy', p.id)); };
const summary = (p) => {
    if (p.type === 'bxgy') return `اشترِ ${p.buy_qty} واحصل على ${p.get_qty} مجانًا`;
    const unit = p.type === 'percent' ? '%' : ' للوحدة';
    return p.tiers?.length ? p.tiers.map((t) => `من ${t.min_qty}: ${t.value}${unit}`).join(' · ') : `${p.value}${unit}`;
};
</script>

<template>
    <AppLayout>
        <Head title="العروض والخصومات" />
        <PageHeader title="العروض والخصومات الترويجية" subtitle="تُطبَّق تلقائيًا على الفواتير وعروض الأسعار وأوامر البيع" :crumbs="[{ label: 'المبيعات' }, { label: 'العروض والخصومات' }]">
            <template #actions>
                <Link v-if="$can('sales.promotions.create')" :href="route('sales.promotions.create')" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold"><PlusIcon class="w-4 h-4" /> عرض جديد</Link>
            </template>
        </PageHeader>
        <div class="bg-white border border-slate-200 rounded-2xl p-3 mb-4 text-xs">
            <select :value="filters.status" @change="setStatus" class="rounded-xl border-slate-200 text-xs"><option value="active">النشطة</option><option value="inactive">المعطلة</option><option value="all">الكل</option></select>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500"><tr><th class="px-4 py-2 text-start">العرض</th><th class="px-4 py-2 text-start">النوع والقيمة</th><th class="px-4 py-2 text-start">يشمل</th><th class="px-4 py-2 text-start">المستندات</th><th class="px-4 py-2 text-start">الفترة</th><th class="px-4 py-2 text-start">الحالة</th><th class="px-4 py-2 w-20"></th></tr></thead>
                <tbody>
                    <tr v-for="p in promotions" :key="p.id" class="border-t border-slate-100">
                        <td class="px-4 py-2 font-bold">{{ p.name }}</td>
                        <td class="px-4 py-2"><span class="text-slate-500">{{ types[p.type] }}</span><br /><b class="font-mono">{{ summary(p) }}</b></td>
                        <td class="px-4 py-2">{{ p.product || p.category || 'كل الأصناف' }}</td>
                        <td class="px-4 py-2 text-slate-500">{{ (p.documents || []).map((d) => documents[d]).join('، ') }}</td>
                        <td class="px-4 py-2 font-mono text-slate-500">{{ p.valid_from ? date(p.valid_from) : '—' }} → {{ p.valid_to ? date(p.valid_to) : '—' }}</td>
                        <td class="px-4 py-2"><span :class="p.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'" class="px-2 py-0.5 rounded-full border text-[11px] font-bold">{{ p.is_active ? 'نشط' : 'معطل' }}</span></td>
                        <td class="px-4 py-2 text-end whitespace-nowrap">
                            <Link v-if="$can('sales.promotions.edit')" :href="route('sales.promotions.edit', p.id)" class="text-slate-400 hover:text-indigo-600 me-2"><PencilSquareIcon class="w-4 h-4 inline" /></Link>
                            <button v-if="$can('sales.promotions.delete')" @click="remove(p)" class="text-rose-500 font-bold">حذف</button>
                        </td>
                    </tr>
                    <tr v-if="!promotions.length"><td colspan="7" class="py-10 text-center text-slate-400">لا توجد عروض.</td></tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
