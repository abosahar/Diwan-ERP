<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { PlusIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

defineProps({ registers: Array });

const remove = (r) => {
    if (confirm(`حذف الجهاز «${r.name}»؟`)) router.delete(route('pos.registers.destroy', r.id), { preserveScroll: true });
};
</script>

<template>
    <AppLayout>
        <Head title="أجهزة الكاشير" />
        <PageHeader title="أجهزة الكاشير" subtitle="الأجهزة التي تُفتح عليها جلسات نقاط البيع" :crumbs="[{ label: 'نقاط البيع' }, { label: 'الأجهزة' }]">
            <template #actions>
                <Link v-if="$can('pos.registers.create')" :href="route('pos.registers.create')" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold inline-flex items-center gap-2">
                    <PlusIcon class="w-4 h-4" /> إضافة جهاز
                </Link>
            </template>
        </PageHeader>

        <div class="bg-white border border-slate-200/80 rounded-2xl overflow-hidden">
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="text-start px-4 py-3">الجهاز</th>
                        <th class="text-start px-4 py-3">الرمز</th>
                        <th class="text-start px-4 py-3">المستودع</th>
                        <th class="text-start px-4 py-3">الجلسات</th>
                        <th class="text-start px-4 py-3">الحالة</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="r in registers" :key="r.id" class="hover:bg-slate-50/60">
                        <td class="px-4 py-3 font-bold text-slate-800">{{ r.name }}</td>
                        <td class="px-4 py-3 font-mono text-slate-500">{{ r.code || '—' }}</td>
                        <td class="px-4 py-3">{{ r.warehouse?.name_ar || '—' }}</td>
                        <td class="px-4 py-3">{{ r.sessions_count }}</td>
                        <td class="px-4 py-3">
                            <span :class="r.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'" class="px-2 py-0.5 rounded-full font-bold">{{ r.is_active ? 'يعمل' : 'موقوف' }}</span>
                        </td>
                        <td class="px-4 py-3 text-end whitespace-nowrap">
                            <Link v-if="$can('pos.registers.edit')" :href="route('pos.registers.edit', r.id)" class="inline-flex p-1.5 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-indigo-50"><PencilSquareIcon class="w-4 h-4" /></Link>
                            <button v-if="$can('pos.registers.delete')" type="button" @click="remove(r)" class="inline-flex p-1.5 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50"><TrashIcon class="w-4 h-4" /></button>
                        </td>
                    </tr>
                    <tr v-if="!registers.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">لا توجد أجهزة بعد. أضف جهازاً ليتمكن الكاشير من فتح جلسة.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
