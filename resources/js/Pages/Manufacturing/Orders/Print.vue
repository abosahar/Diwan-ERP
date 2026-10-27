<script setup>
import { onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({ order: Object, company: Object });
const num = (v) => Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 4 });
onMounted(() => setTimeout(() => window.print(), 300));
</script>

<template>
    <div class="p-8 max-w-4xl mx-auto text-sm" dir="rtl">
        <Head :title="`بطاقة تشغيل ${order.order_number}`" />
        <div class="flex justify-between items-start border-b-2 border-slate-800 pb-4 mb-4">
            <div><h1 class="text-xl font-black">بطاقة تشغيل</h1><p class="text-slate-500">{{ company?.name }}</p></div>
            <div class="text-end"><p class="font-mono font-black text-lg">{{ order.order_number }}</p><p class="text-xs text-slate-500">بداية: {{ String(order.planned_start || '').slice(0, 10) }} — نهاية: {{ String(order.planned_end || '').slice(0, 10) || '—' }}</p></div>
        </div>
        <p class="mb-4"><b>المنتج:</b> {{ order.product?.name_ar }} ({{ order.product?.sku }}) &nbsp; <b>الكمية:</b> <span class="font-mono">{{ num(order.quantity) }}</span> &nbsp; <b>المستودع:</b> {{ order.warehouse?.name_ar }}</p>
        <h2 class="font-black mb-2">المواد المطلوبة</h2>
        <table class="w-full border border-slate-300 mb-6 text-xs"><thead class="bg-slate-100"><tr><th class="border p-2 text-start">المادة</th><th class="border p-2">المطلوب</th><th class="border p-2">المصروف</th><th class="border p-2">توقيع أمين المستودع</th></tr></thead>
            <tbody><tr v-for="m in order.materials" :key="m.id"><td class="border p-2">{{ m.product?.name_ar }}</td><td class="border p-2 text-center font-mono">{{ num(m.planned_quantity) }}</td><td class="border p-2 text-center font-mono">&nbsp;</td><td class="border p-2">&nbsp;</td></tr></tbody></table>
        <h2 class="font-black mb-2">العمليات</h2>
        <table class="w-full border border-slate-300 text-xs"><thead class="bg-slate-100"><tr><th class="border p-2">التسلسل</th><th class="border p-2 text-start">العملية</th><th class="border p-2 text-start">مركز العمل</th><th class="border p-2">دقائق مخططة</th><th class="border p-2">الكمية المنجزة</th><th class="border p-2">التوقيع</th></tr></thead>
            <tbody><tr v-for="o in order.operations" :key="o.id"><td class="border p-2 text-center font-mono">{{ o.seq }}</td><td class="border p-2">{{ o.name }}</td><td class="border p-2">{{ o.work_center?.name }}</td><td class="border p-2 text-center font-mono">{{ num(o.planned_minutes) }}</td><td class="border p-2">&nbsp;</td><td class="border p-2">&nbsp;</td></tr></tbody></table>
        <p class="mt-8 text-xs text-slate-500">ملاحظات: {{ order.notes || '................................................' }}</p>
    </div>
</template>
