<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { PlusIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

defineProps({ centers: Array });
const types = { machine: 'آلة', line: 'خط إنتاج', labour: 'عمالة يدوية', workshop: 'ورشة' };
const remove = (c) => confirm(`حذف مركز العمل «${c.name}»؟`) && router.delete(route('manufacturing.work-centers.destroy', c.id), { preserveScroll: true });
</script>

<template>
    <AppLayout>
        <Head title="مراكز العمل" />
        <PageHeader title="مراكز العمل" subtitle="الآلات وخطوط الإنتاج والورش، بطاقتها وتكلفة ساعتها" :crumbs="[{ label: 'التصنيع' }, { label: 'مراكز العمل' }]">
            <template #actions>
                <Link v-if="$can('manufacturing.work_centers.create')" :href="route('manufacturing.work-centers.create')" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold inline-flex items-center gap-2"><PlusIcon class="w-4 h-4" /> مركز عمل جديد</Link>
            </template>
        </PageHeader>
        <div class="bg-white border border-slate-200/80 rounded-2xl overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500"><tr><th class="text-start px-4 py-3">الرمز</th><th class="text-start px-4 py-3">الاسم</th><th class="text-start px-4 py-3">النوع</th><th class="text-start px-4 py-3">الطاقة (ساعة/يوم)</th><th class="text-start px-4 py-3">الكفاءة</th><th class="text-start px-4 py-3">أجور/ساعة</th><th class="text-start px-4 py-3">مصروفات/ساعة</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="c in centers" :key="c.id" class="hover:bg-slate-50/60" :class="{ 'opacity-50': !c.is_active }">
                        <td class="px-4 py-3 font-mono font-bold">{{ c.code }}</td><td class="px-4 py-3 font-bold text-slate-800">{{ c.name }}<span v-if="c.equipment" class="block text-3xs text-slate-400">معدة: {{ c.equipment.name }}</span></td>
                        <td class="px-4 py-3">{{ types[c.type] }}</td><td class="px-4 py-3 font-mono">{{ c.capacity_hours_per_day }}</td><td class="px-4 py-3 font-mono">{{ c.efficiency }}%</td>
                        <td class="px-4 py-3 font-mono">{{ c.labour_rate_per_hour }}</td><td class="px-4 py-3 font-mono">{{ c.overhead_rate_per_hour }}</td>
                        <td class="px-4 py-3 text-end whitespace-nowrap">
                            <Link v-if="$can('manufacturing.work_centers.edit')" :href="route('manufacturing.work-centers.edit', c.id)" class="inline-flex p-1.5 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-indigo-50"><PencilSquareIcon class="w-4 h-4" /></Link>
                            <button v-if="$can('manufacturing.work_centers.delete')" type="button" @click="remove(c)" class="inline-flex p-1.5 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50"><TrashIcon class="w-4 h-4" /></button>
                        </td>
                    </tr>
                    <tr v-if="!centers.length"><td colspan="8" class="px-4 py-10 text-center text-slate-400">لا توجد مراكز عمل بعد.</td></tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
