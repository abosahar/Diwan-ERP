<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import StatusPill from '@/Components/Mfg/StatusPill.vue';
import { PlusIcon } from '@heroicons/vue/24/outline';

defineProps({ orders: Object });
const num = (v) => Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 4 });
</script>

<template>
    <AppLayout>
        <Head title="التصنيع لدى الغير" />
        <PageHeader title="التصنيع لدى الغير" subtitle="نرسل الخامات لمصنّع خارجي ونستلم منتجاً تاماً بتكلفة الخامات + أجر التصنيع" :crumbs="[{ label: 'التصنيع' }, { label: 'التصنيع لدى الغير' }]">
            <template #actions><Link v-if="$can('manufacturing.subcontracting.create')" :href="route('manufacturing.subcontracts.create')" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold inline-flex items-center gap-2"><PlusIcon class="w-4 h-4" /> أمر جديد</Link></template>
        </PageHeader>
        <div class="bg-white border border-slate-200/80 rounded-2xl overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500"><tr><th class="text-start px-4 py-3">الرقم</th><th class="text-start px-4 py-3">المورد</th><th class="text-start px-4 py-3">المنتج</th><th class="text-start px-4 py-3">الكمية</th><th class="text-start px-4 py-3">المستلم</th><th class="text-start px-4 py-3">الحالة</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="o in orders.data" :key="o.id" class="hover:bg-slate-50/60 cursor-pointer" @click="router.visit(route('manufacturing.subcontracts.show', o.id))">
                        <td class="px-4 py-3 font-mono font-bold text-indigo-700">{{ o.order_number }}</td><td class="px-4 py-3 font-bold">{{ o.supplier?.name }}</td><td class="px-4 py-3">{{ o.product?.name_ar }}</td><td class="px-4 py-3 font-mono">{{ num(o.quantity) }}</td><td class="px-4 py-3 font-mono">{{ num(o.received_quantity) }}</td><td class="px-4 py-3"><StatusPill :status="o.status" /></td>
                    </tr>
                    <tr v-if="!orders.data.length"><td colspan="6" class="px-4 py-10 text-center text-slate-400">لا أوامر.</td></tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="orders.links" class="mt-4" />
    </AppLayout>
</template>
