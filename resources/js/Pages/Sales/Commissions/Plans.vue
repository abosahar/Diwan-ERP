<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { amount } from '@/utils/format';
import { PencilSquareIcon, PlusIcon } from '@heroicons/vue/24/outline';

defineProps({ plans: Array, types: Object });
const remove = (p) => { if (confirm(`حذف الخطة «${p.name}»؟ يعود مندوبوها إلى نسبتهم الخاصة.`)) router.delete(route('sales.commission-plans.destroy', p.id)); };
const summary = (p) => (p.type === 'flat' ? `${p.rate}% من صافي المبيعات` : (p.tiers || []).map((t) => `من ${amount(t.from)}: ${t.rate}%`).join(' · '));
</script>

<template>
    <AppLayout>
        <Head title="خطط العمولات" />
        <PageHeader title="خطط العمولات" :crumbs="[{ label: 'المبيعات' }, { label: 'عمولات المبيعات', href: route('sales.commissions.index') }, { label: 'الخطط' }]">
            <template #actions>
                <Link v-if="$can('sales.commissions.create')" :href="route('sales.commission-plans.create')" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold"><PlusIcon class="w-4 h-4" /> خطة جديدة</Link>
            </template>
        </PageHeader>
        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500"><tr><th class="px-4 py-2 text-start">الخطة</th><th class="px-4 py-2 text-start">القاعدة</th><th class="px-4 py-2 text-start">الهدف والمكافأة</th><th class="px-4 py-2 text-start">المناديب</th><th class="px-4 py-2 text-start">الحالة</th><th class="px-4 py-2 w-24"></th></tr></thead>
                <tbody>
                    <tr v-for="p in plans" :key="p.id" class="border-t border-slate-100">
                        <td class="px-4 py-2 font-bold">{{ p.name }}</td>
                        <td class="px-4 py-2"><span class="text-slate-500">{{ types[p.type] }}</span><br /><b class="font-mono">{{ summary(p) }}</b></td>
                        <td class="px-4 py-2 font-mono">{{ p.target_amount ? `${amount(p.target_amount)} ← ${amount(p.bonus_amount)}` : '—' }}</td>
                        <td class="px-4 py-2">{{ p.users_count }}</td>
                        <td class="px-4 py-2"><span :class="p.is_active ? 'text-emerald-600' : 'text-slate-400'" class="font-bold">{{ p.is_active ? 'نشطة' : 'معطلة' }}</span></td>
                        <td class="px-4 py-2 text-end whitespace-nowrap">
                            <Link v-if="$can('sales.commissions.edit')" :href="route('sales.commission-plans.edit', p.id)" class="text-slate-400 hover:text-indigo-600 me-2"><PencilSquareIcon class="w-4 h-4 inline" /></Link>
                            <button v-if="$can('sales.commissions.delete')" @click="remove(p)" class="text-rose-500 font-bold">حذف</button>
                        </td>
                    </tr>
                    <tr v-if="!plans.length"><td colspan="6" class="py-10 text-center text-slate-400">لا توجد خطط.</td></tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
