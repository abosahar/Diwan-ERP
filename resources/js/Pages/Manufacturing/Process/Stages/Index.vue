<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { PlusIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

defineProps({ stages: Array });
const remove = (s) => confirm(`حذف المرحلة «${s.name}»؟`) && router.delete(route('manufacturing.stages.destroy', s.id), { preserveScroll: true });
</script>

<template>
    <AppLayout>
        <Head title="مراحل التصنيع المستمر" />
        <PageHeader title="مراحل التصنيع المستمر" subtitle="خط الإنتاج المتصل: تُحسب تكلفة كل مرحلة بالوحدات المعادلة" :crumbs="[{ label: 'التصنيع' }, { label: 'المراحل' }]">
            <template #actions>
                <Link v-if="$can('manufacturing.process.create')" :href="route('manufacturing.stages.create')" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold inline-flex items-center gap-2"><PlusIcon class="w-4 h-4" /> مرحلة جديدة</Link>
            </template>
        </PageHeader>
        <div class="bg-white border border-slate-200/80 rounded-2xl overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500"><tr><th class="text-start px-4 py-3">التسلسل</th><th class="text-start px-4 py-3">الرمز</th><th class="text-start px-4 py-3">المرحلة</th><th class="text-start px-4 py-3">ناتج المرحلة</th><th class="text-start px-4 py-3">تستلم من</th><th class="text-start px-4 py-3">المستودع</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="s in stages" :key="s.id" class="hover:bg-slate-50/60" :class="{ 'opacity-50': !s.is_active }">
                        <td class="px-4 py-3 font-mono">{{ s.sequence }}</td><td class="px-4 py-3 font-mono font-bold">{{ s.code }}</td><td class="px-4 py-3 font-bold text-slate-800">{{ s.name }}</td><td class="px-4 py-3">{{ s.product?.name_ar }}</td><td class="px-4 py-3">{{ s.previous?.name || '—' }}</td><td class="px-4 py-3">{{ s.warehouse?.name_ar }}</td>
                        <td class="px-4 py-3 text-end whitespace-nowrap">
                            <Link v-if="$can('manufacturing.process.edit')" :href="route('manufacturing.stages.edit', s.id)" class="inline-flex p-1.5 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-indigo-50"><PencilSquareIcon class="w-4 h-4" /></Link>
                            <button v-if="$can('manufacturing.process.edit')" type="button" @click="remove(s)" class="inline-flex p-1.5 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50"><TrashIcon class="w-4 h-4" /></button>
                        </td>
                    </tr>
                    <tr v-if="!stages.length"><td colspan="7" class="px-4 py-10 text-center text-slate-400">لا توجد مراحل بعد.</td></tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
