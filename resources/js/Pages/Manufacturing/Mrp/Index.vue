<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatusPill from '@/Components/Mfg/StatusPill.vue';

const props = defineProps({ run: Object, suggestions: Array, runs: Array, warehouseId: Number });
const form = useForm({ horizon_days: props.run?.horizon_days || 30 });
const selected = ref([]);
const purchases = computed(() => props.suggestions.filter((s) => s.type === 'purchase'));
const manufacture = computed(() => props.suggestions.filter((s) => s.type === 'manufacture'));
const num = (v) => Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 4 });
const toggleAll = () => (selected.value = selected.value.length ? [] : purchases.value.filter((s) => s.status === 'new').map((s) => s.id));
const convertPurchases = () => router.post(route('manufacturing.mrp.convert-purchase'), { ids: selected.value });
const produce = (s) => router.post(route('manufacturing.mrp.convert-production', s.id), { warehouse_id: props.warehouseId });
const dismiss = (s) => router.post(route('manufacturing.mrp.dismiss', s.id), {}, { preserveScroll: true });
const card = 'bg-white border border-slate-200/80 rounded-2xl p-5';
</script>

<template>
    <AppLayout>
        <Head title="تخطيط الاحتياجات" />
        <PageHeader title="تخطيط الاحتياجات من المواد (MRP)" subtitle="يقارن الطلب بالمخزون والمشتريات والإنتاج المفتوح ويقترح ما يُشترى وما يُصنَّع" :crumbs="[{ label: 'التصنيع' }, { label: 'تخطيط الاحتياجات' }]" />

        <div v-if="$can('manufacturing.mrp.manage')" :class="[card, 'mb-6 flex flex-wrap items-end gap-3']">
            <div><label class="block text-xs font-bold text-slate-600 mb-1">أفق التخطيط (أيام)</label><input v-model.number="form.horizon_days" type="text" inputmode="decimal" data-num min="1" max="365" class="text-xs rounded-xl border-slate-200 w-32" /></div>
            <button type="button" :disabled="form.processing" @click="form.post(route('manufacturing.mrp.run'))" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold">تشغيل التخطيط</button>
            <p class="text-3xs text-slate-500 flex-1 min-w-60">الطلب: أوامر البيع المؤكدة غير المسلَّمة، ومواد أوامر الإنتاج المفتوحة، وحدود إعادة الطلب للأصناف. تتفكك المنتجات المصنَّعة إلى مكوناتها عبر قوائم المواد على كل المستويات.</p>
            <p v-if="run" class="text-3xs text-slate-400">آخر تشغيل: {{ String(run.run_date).slice(0, 10) }} (أفق {{ run.horizon_days }} يوماً)</p>
        </div>

        <p v-if="!run" :class="[card, 'text-center text-slate-400 text-sm']">لم يُشغَّل التخطيط بعد.</p>
        <template v-else>
            <div :class="[card, 'mb-6']">
                <div class="flex items-center justify-between mb-3"><h2 class="font-black text-slate-800 text-sm">اقتراحات التصنيع</h2></div>
                <p v-if="!manufacture.length" class="text-xs text-slate-400">لا اقتراحات تصنيع.</p>
                <div class="overflow-x-auto" v-else><table class="w-full text-xs">
                    <thead class="text-slate-500"><tr><th class="text-start p-2">المنتج</th><th class="text-start p-2">الطلب</th><th class="text-start p-2">المخزون</th><th class="text-start p-2">قيد التوريد</th><th class="text-start p-2">المطلوب تصنيعه</th><th class="text-start p-2">قبل</th><th class="p-2"></th></tr></thead>
                    <tbody><tr v-for="s in manufacture" :key="s.id" class="border-t border-slate-100">
                        <td class="p-2 font-bold">{{ s.product?.name_ar }}</td><td class="p-2 font-mono">{{ num(s.gross_demand) }}</td><td class="p-2 font-mono">{{ num(s.on_hand) }}</td><td class="p-2 font-mono">{{ num(s.supply) }}</td><td class="p-2 font-mono font-black">{{ num(s.quantity) }}</td><td class="p-2 font-mono">{{ String(s.need_date).slice(0, 10) }}</td>
                        <td class="p-2 text-end whitespace-nowrap"><StatusPill v-if="s.status !== 'new'" :status="s.status" />
                            <template v-else-if="$can('manufacturing.mrp.manage')"><button type="button" @click="produce(s)" class="px-3 py-1 rounded-lg bg-indigo-600 text-white font-bold">أمر إنتاج</button><button type="button" @click="dismiss(s)" class="px-2 py-1 text-slate-400 hover:text-rose-600">تجاهل</button></template>
                            <Link v-if="s.converted_type === 'production_order'" :href="route('manufacturing.orders.show', s.converted_id)" class="ms-2 text-indigo-600 font-bold">فتح</Link></td></tr></tbody>
                </table></div>
            </div>
            <div :class="card">
                <div class="flex items-center justify-between mb-3"><h2 class="font-black text-slate-800 text-sm">اقتراحات الشراء</h2>
                    <div v-if="purchases.length && $can('manufacturing.mrp.manage')" class="flex gap-2"><button type="button" @click="toggleAll" class="text-xs font-bold text-slate-500">تحديد الكل</button><button type="button" :disabled="!selected.length" @click="convertPurchases" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold disabled:opacity-40">إنشاء طلب شراء ({{ selected.length }})</button></div></div>
                <p v-if="!purchases.length" class="text-xs text-slate-400">لا اقتراحات شراء.</p>
                <div class="overflow-x-auto" v-else><table class="w-full text-xs">
                    <thead class="text-slate-500"><tr><th class="w-8"></th><th class="text-start p-2">المادة</th><th class="text-start p-2">الطلب</th><th class="text-start p-2">المخزون</th><th class="text-start p-2">قيد التوريد</th><th class="text-start p-2">المطلوب شراؤه</th><th class="text-start p-2">قبل</th><th class="p-2"></th></tr></thead>
                    <tbody><tr v-for="s in purchases" :key="s.id" class="border-t border-slate-100">
                        <td class="p-2"><input v-if="s.status === 'new'" v-model="selected" type="checkbox" :value="s.id" class="rounded" /></td>
                        <td class="p-2 font-bold">{{ s.product?.name_ar }}</td><td class="p-2 font-mono">{{ num(s.gross_demand) }}</td><td class="p-2 font-mono">{{ num(s.on_hand) }}</td><td class="p-2 font-mono">{{ num(s.supply) }}</td><td class="p-2 font-mono font-black">{{ num(s.quantity) }}</td><td class="p-2 font-mono">{{ String(s.need_date).slice(0, 10) }}</td>
                        <td class="p-2 text-end"><StatusPill v-if="s.status !== 'new'" :status="s.status" /><button v-else-if="$can('manufacturing.mrp.manage')" type="button" @click="dismiss(s)" class="px-2 py-1 text-slate-400 hover:text-rose-600">تجاهل</button></td></tr></tbody>
                </table></div>
            </div>
        </template>
    </AppLayout>
</template>
