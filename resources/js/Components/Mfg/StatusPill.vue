<script setup>
import { computed } from 'vue';

const props = defineProps({ status: String });

const map = {
    draft: ['مسودة', 'bg-slate-100 text-slate-600'],
    released: ['مُفرَج عنه', 'bg-sky-50 text-sky-700'],
    in_progress: ['قيد التنفيذ', 'bg-amber-50 text-amber-700'],
    completed: ['مكتمل', 'bg-emerald-50 text-emerald-700'],
    closed: ['مغلق', 'bg-slate-200 text-slate-700'],
    cancelled: ['ملغى', 'bg-rose-50 text-rose-600'],
    sent: ['أُرسلت المواد', 'bg-sky-50 text-sky-700'],
    partially_received: ['استلام جزئي', 'bg-amber-50 text-amber-700'],
    pending: ['بانتظار الفحص', 'bg-amber-50 text-amber-700'],
    pass: ['مطابق', 'bg-emerald-50 text-emerald-700'],
    fail: ['غير مطابق', 'bg-rose-50 text-rose-600'],
    partial: ['مطابق جزئياً', 'bg-amber-50 text-amber-700'],
    open: ['مفتوحة', 'bg-sky-50 text-sky-700'],
    closed_period: ['مغلقة', 'bg-slate-200 text-slate-700'],
    posted: ['مرحّل', 'bg-emerald-50 text-emerald-700'],
    pending_qc: ['بانتظار الجودة', 'bg-amber-50 text-amber-700'],
    rejected: ['مرفوض', 'bg-rose-50 text-rose-600'],
    new: ['جديد', 'bg-sky-50 text-sky-700'],
    converted: ['حُوّل', 'bg-emerald-50 text-emerald-700'],
    dismissed: ['مُهمَل', 'bg-slate-100 text-slate-500'],
    done: ['منجز', 'bg-emerald-50 text-emerald-700'],
};
const item = computed(() => map[props.status] || [props.status, 'bg-slate-100 text-slate-600']);
</script>

<template>
    <span :class="item[1]" class="inline-block px-2.5 py-0.5 rounded-full text-3xs font-bold whitespace-nowrap">{{ item[0] }}</span>
</template>
