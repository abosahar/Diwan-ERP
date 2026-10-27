<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { PlusIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

defineProps({ plans: Array });
const remove = (p) => confirm(`حذف خطة «${p.name}»؟`) && router.delete(route('manufacturing.plans.destroy', p.id), { preserveScroll: true });
</script>

<template>
    <AppLayout>
        <Head title="خطط الجودة" />
        <PageHeader title="خطط الجودة" subtitle="بنود الفحص التي يُفحص بها المنتج عند الاستلام من الإنتاج أو من المورد" :crumbs="[{ label: 'التصنيع' }, { label: 'خطط الجودة' }]">
            <template #actions><Link v-if="$can('manufacturing.quality.create')" :href="route('manufacturing.plans.create')" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold inline-flex items-center gap-2"><PlusIcon class="w-4 h-4" /> خطة جديدة</Link></template>
        </PageHeader>
        <div class="bg-white border border-slate-200/80 rounded-2xl overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500"><tr><th class="text-start px-4 py-3">الخطة</th><th class="text-start px-4 py-3">المنتج</th><th class="text-start px-4 py-3">تُطبَّق على</th><th class="text-start px-4 py-3">البنود</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="p in plans" :key="p.id" :class="{ 'opacity-50': !p.is_active }"><td class="px-4 py-3 font-bold text-slate-800">{{ p.name }}</td><td class="px-4 py-3">{{ p.product?.name_ar || 'كل المنتجات' }}</td><td class="px-4 py-3">{{ p.applies_to === 'production' ? 'استلام الإنتاج' : 'استلام المشتريات' }}</td><td class="px-4 py-3">{{ (p.checks || []).length }}</td>
                        <td class="px-4 py-3 text-end whitespace-nowrap">
                            <Link v-if="$can('manufacturing.quality.edit')" :href="route('manufacturing.plans.edit', p.id)" class="inline-flex p-1.5 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-indigo-50"><PencilSquareIcon class="w-4 h-4" /></Link>
                            <button v-if="$can('manufacturing.quality.delete')" type="button" @click="remove(p)" class="inline-flex p-1.5 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50"><TrashIcon class="w-4 h-4" /></button></td></tr>
                    <tr v-if="!plans.length"><td colspan="5" class="px-4 py-10 text-center text-slate-400">لا توجد خطط. بدون خطة يُستلم الإنتاج دون فحص.</td></tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
