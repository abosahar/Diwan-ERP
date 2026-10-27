<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { PlusIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

defineProps({ boms: Array });
const money = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
const remove = (b) => confirm(`حذف قائمة المواد «${b.name}»؟`) && router.delete(route('manufacturing.boms.destroy', b.id), { preserveScroll: true });
</script>

<template>
    <AppLayout>
        <Head title="قوائم المواد" />
        <PageHeader title="قوائم المواد (BOM)" subtitle="مكونات كل منتج وإصداراتها، مع تكلفة المواد للوحدة" :crumbs="[{ label: 'التصنيع' }, { label: 'قوائم المواد' }]">
            <template #actions>
                <Link v-if="$can('manufacturing.boms.create')" :href="route('manufacturing.boms.create')" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold inline-flex items-center gap-2"><PlusIcon class="w-4 h-4" /> قائمة جديدة</Link>
            </template>
        </PageHeader>
        <div class="bg-white border border-slate-200/80 rounded-2xl overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500"><tr><th class="text-start px-4 py-3">المنتج</th><th class="text-start px-4 py-3">القائمة</th><th class="text-start px-4 py-3">الإصدار</th><th class="text-start px-4 py-3">المكونات</th><th class="text-start px-4 py-3">كمية الناتج</th><th class="text-start px-4 py-3">تكلفة مواد الوحدة</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="b in boms" :key="b.id" class="hover:bg-slate-50/60" :class="{ 'opacity-50': !b.is_active }">
                        <td class="px-4 py-3 font-bold text-slate-800">{{ b.product }}</td><td class="px-4 py-3">{{ b.name }}<span v-if="b.is_default" class="ms-2 text-3xs bg-emerald-50 text-emerald-700 rounded-full px-2 py-0.5 font-bold">الافتراضية</span></td>
                        <td class="px-4 py-3 font-mono">{{ b.version }}</td><td class="px-4 py-3">{{ b.items_count }}</td><td class="px-4 py-3 font-mono">{{ b.output_quantity }}</td><td class="px-4 py-3 font-mono">{{ money(b.unit_cost) }}</td>
                        <td class="px-4 py-3 text-end whitespace-nowrap">
                            <Link v-if="$can('manufacturing.boms.edit')" :href="route('manufacturing.boms.edit', b.id)" class="inline-flex p-1.5 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-indigo-50"><PencilSquareIcon class="w-4 h-4" /></Link>
                            <button v-if="$can('manufacturing.boms.delete')" type="button" @click="remove(b)" class="inline-flex p-1.5 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50"><TrashIcon class="w-4 h-4" /></button>
                        </td>
                    </tr>
                    <tr v-if="!boms.length"><td colspan="7" class="px-4 py-10 text-center text-slate-400">لا توجد قوائم مواد بعد.</td></tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
