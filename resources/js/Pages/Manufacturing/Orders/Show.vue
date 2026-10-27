<script setup>
import SelectCreate from '@/Components/UI/SelectCreate.vue';
import { reactive, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatusPill from '@/Components/Mfg/StatusPill.vue';

const props = defineProps({ order: Object, sheet: Object, workCenters: Array, employees: Array, inspections: Array, wip: Number });

const o = computed(() => props.order);
const live = computed(() => ['released', 'in_progress'].includes(o.value.status));
const money = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const num = (v) => Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 4 });
const post = (name, data = {}) => router.post(route(`manufacturing.orders.${name}`, o.value.id), data, { preserveScroll: true });
const qty = reactive({});
const material = (m, action) => { if (qty[m.id] > 0) { post(action, { material_id: m.id, quantity: qty[m.id] }); qty[m.id] = ''; } };

const time = useForm({ work_center_id: props.order.operations?.[0]?.work_center_id || props.workCenters?.[0]?.id || '', operation_id: '', employee_id: '', minutes: '', quantity: '', date: new Date().toISOString().slice(0, 10) });
const logTime = () => time.post(route('manufacturing.orders.time', o.value.id), { preserveScroll: true, onSuccess: () => time.reset('minutes', 'quantity') });
const pickOp = () => { const op = o.value.operations.find((x) => x.id === time.operation_id); if (op) time.work_center_id = op.work_center_id; };

const rec = useForm({ quantity: '', scrap_quantity: '', scrap_abnormal: false, batch_number: '', date: new Date().toISOString().slice(0, 10) });
const receive = () => rec.post(route('manufacturing.orders.receive', o.value.id), { preserveScroll: true, onSuccess: () => rec.reset('quantity', 'scrap_quantity') });
const remove = () => confirm('حذف أمر المسودة؟') && router.delete(route('manufacturing.orders.destroy', o.value.id));
const inspectionOf = (receiptId) => props.inspections.find((i) => i.source_id === receiptId);
const input = 'w-full text-xs rounded-xl border-slate-200';
const card = 'bg-white border border-slate-200/80 rounded-2xl p-5';
</script>

<template>
    <AppLayout>
        <Head :title="`أمر إنتاج ${o.order_number}`" />
        <PageHeader :title="`أمر إنتاج ${o.order_number}`" :subtitle="o.product?.name_ar" :crumbs="[{ label: 'التصنيع' }, { label: 'أوامر الإنتاج', href: route('manufacturing.orders.index') }, { label: o.order_number }]">
            <template #actions>
                <StatusPill :status="o.status" />
                <button v-if="o.status === 'draft' && $can('manufacturing.orders.approve')" type="button" @click="post('release')" class="px-4 py-2 rounded-xl bg-sky-600 text-white text-xs font-bold">إفراج عن الأمر</button>
                <button v-if="live && $can('manufacturing.orders.post')" type="button" @click="post('issue-all')" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold">صرف كل المواد</button>
                <button v-if="['in_progress', 'completed'].includes(o.status) && $can('manufacturing.orders.post')" type="button" @click="confirm('إغلاق الأمر وترحيل الفروقات؟') && post('close')" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold">إغلاق وترحيل الفروقات</button>
                <button v-if="['draft', 'released'].includes(o.status) && $can('manufacturing.orders.cancel')" type="button" @click="confirm('إلغاء الأمر؟') && post('cancel')" class="px-4 py-2 rounded-xl border border-rose-300 text-rose-600 text-xs font-bold">إلغاء</button>
                <button v-if="o.status === 'draft' && $can('manufacturing.orders.delete')" type="button" @click="remove" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 text-xs font-bold">حذف</button>
                <a v-if="$can('manufacturing.orders.print')" :href="route('manufacturing.orders.print', o.id)" target="_blank" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 text-xs font-bold">بطاقة تشغيل</a>
            </template>
        </PageHeader>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div :class="card"><p class="text-3xs font-bold text-slate-400">الكمية / المستلم</p><p class="text-xl font-black font-mono">{{ num(o.received_quantity) }} / {{ num(o.quantity) }}</p></div>
            <div :class="card"><p class="text-3xs font-bold text-slate-400">مواد</p><p class="text-xl font-black font-mono">{{ money(o.material_cost) }}</p></div>
            <div :class="card"><p class="text-3xs font-bold text-slate-400">أجور + مصروفات</p><p class="text-xl font-black font-mono">{{ money(Number(o.labour_cost) + Number(o.overhead_cost)) }}</p></div>
            <div :class="card"><p class="text-3xs font-bold text-slate-400">تحت التشغيل</p><p class="text-xl font-black font-mono text-indigo-700">{{ money(wip) }}</p></div>
            <div :class="card"><p class="text-3xs font-bold text-slate-400">تكلفة الوحدة التامة</p><p class="text-xl font-black font-mono">{{ sheet.unit_cost === null ? '—' : money(sheet.unit_cost) }}</p></div>
        </div>

        <div :class="[card, 'mb-6']">
            <h2 class="font-black text-slate-800 text-sm mb-3">المواد</h2>
            <div class="overflow-x-auto"><table class="w-full text-xs">
                <thead class="text-slate-500"><tr><th class="text-start p-2">المادة</th><th class="text-start p-2">المطلوب</th><th class="text-start p-2">المصروف</th><th class="text-start p-2">المرتجع</th><th class="text-start p-2">التكلفة</th><th v-if="live" class="p-2 text-start">صرف / إرجاع</th></tr></thead>
                <tbody>
                    <tr v-for="m in o.materials" :key="m.id" class="border-t border-slate-100">
                        <td class="p-2 font-bold">{{ m.product?.name_ar }}</td><td class="p-2 font-mono">{{ num(m.planned_quantity) }}</td><td class="p-2 font-mono">{{ num(m.issued_quantity) }}</td><td class="p-2 font-mono">{{ num(m.returned_quantity) }}</td><td class="p-2 font-mono">{{ money(m.cost) }}</td>
                        <td v-if="live && $can('manufacturing.orders.post')" class="p-2 whitespace-nowrap">
                            <input v-model="qty[m.id]" type="number" step="0.0001" placeholder="الكمية" class="w-24 text-xs rounded-lg border-slate-200 me-1" />
                            <button type="button" @click="material(m, 'issue')" class="px-2 py-1 rounded-lg bg-indigo-50 text-indigo-700 font-bold">صرف</button>
                            <button type="button" @click="material(m, 'return')" class="px-2 py-1 rounded-lg bg-slate-100 text-slate-600 font-bold ms-1">إرجاع</button>
                        </td>
                    </tr>
                    <tr v-if="!o.materials.length"><td colspan="6" class="p-4 text-center text-slate-400">لا مواد مخططة (بلا قائمة مواد).</td></tr>
                </tbody>
            </table></div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
            <div :class="card">
                <h2 class="font-black text-slate-800 text-sm mb-3">العمليات والوقت</h2>
                <table class="w-full text-xs mb-4"><thead class="text-slate-500"><tr><th class="text-start p-2">#</th><th class="text-start p-2">العملية</th><th class="text-start p-2">مخطط (د)</th><th class="text-start p-2">فعلي (د)</th><th class="text-start p-2">الحالة</th></tr></thead>
                    <tbody><tr v-for="op in o.operations" :key="op.id" class="border-t border-slate-100"><td class="p-2 font-mono">{{ op.seq }}</td><td class="p-2">{{ op.name }} <span class="text-slate-400">({{ op.work_center?.name }})</span></td><td class="p-2 font-mono">{{ num(op.planned_minutes) }}</td><td class="p-2 font-mono">{{ num(op.actual_minutes) }}</td><td class="p-2"><StatusPill :status="op.status" /></td></tr>
                    <tr v-if="!o.operations.length"><td colspan="5" class="p-4 text-center text-slate-400">لا عمليات (بلا مسار تشغيل).</td></tr></tbody></table>
                <form v-if="live && $can('manufacturing.orders.post')" @submit.prevent="logTime" class="grid grid-cols-2 md:grid-cols-3 gap-2 border-t border-slate-100 pt-3">
                    <select v-model="time.operation_id" @change="pickOp" :class="input"><option value="">عملية (اختياري)</option><option v-for="op in o.operations" :key="op.id" :value="op.id">{{ op.seq }} {{ op.name }}</option></select>
                    <SelectCreate v-model="time.work_center_id" :options="workCenters" entity="work_center" label="name" :allow-empty="false" required />
                    <SelectCreate v-model="time.employee_id" :options="employees" entity="employee" label="name" placeholder="عامل (اختياري)" />
                    <input v-model="time.minutes" type="number" step="0.01" placeholder="الدقائق *" :class="input" required />
                    <input v-model="time.quantity" type="number" step="0.0001" placeholder="الكمية المنجزة" :class="input" />
                    <button type="submit" :disabled="time.processing" class="rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">تسجيل الوقت</button>
                    <p v-if="time.errors.minutes" class="col-span-full text-xs text-rose-600">{{ time.errors.minutes }}</p>
                </form>
                <table v-if="o.time_logs.length" class="w-full text-xs mt-3"><tbody><tr v-for="t in o.time_logs" :key="t.id" class="border-t border-slate-100"><td class="p-2 font-mono">{{ String(t.date).slice(0, 10) }}</td><td class="p-2">{{ t.work_center?.name }} {{ t.employee?.name ? '· ' + t.employee.name : '' }}</td><td class="p-2 font-mono">{{ num(t.minutes) }} د</td><td class="p-2 font-mono">{{ money(Number(t.labour_cost) + Number(t.overhead_cost)) }}</td></tr></tbody></table>
            </div>

            <div :class="card">
                <h2 class="font-black text-slate-800 text-sm mb-3">استلام المنتج التام</h2>
                <table class="w-full text-xs mb-4"><thead class="text-slate-500"><tr><th class="text-start p-2">التاريخ</th><th class="text-start p-2">سليم</th><th class="text-start p-2">هالك</th><th class="text-start p-2">التكلفة</th><th class="text-start p-2">الحالة</th></tr></thead>
                    <tbody><tr v-for="r in o.receipts" :key="r.id" class="border-t border-slate-100"><td class="p-2 font-mono">{{ String(r.date).slice(0, 10) }}</td><td class="p-2 font-mono">{{ num(r.quantity) }}</td><td class="p-2 font-mono">{{ num(r.scrap_quantity) }}</td><td class="p-2 font-mono">{{ money(r.total_cost) }}</td>
                        <td class="p-2"><StatusPill :status="r.status" /><Link v-if="inspectionOf(r.id)" :href="route('manufacturing.inspections.show', inspectionOf(r.id).id)" class="ms-2 text-indigo-600 font-bold">فحص</Link></td></tr>
                    <tr v-if="!o.receipts.length"><td colspan="5" class="p-4 text-center text-slate-400">لا استلامات بعد.</td></tr></tbody></table>
                <form v-if="live || o.status === 'completed'" @submit.prevent="receive" class="grid grid-cols-2 gap-2 border-t border-slate-100 pt-3">
                    <template v-if="$can('manufacturing.orders.post')">
                        <input v-model="rec.quantity" type="number" step="0.0001" placeholder="الكمية السليمة *" :class="input" />
                        <input v-model="rec.scrap_quantity" type="number" step="0.0001" placeholder="الهالك" :class="input" />
                        <input v-model="rec.batch_number" placeholder="رقم الدفعة" :class="input" />
                        <input v-model="rec.date" type="date" :class="input" />
                        <label class="col-span-2 inline-flex items-center gap-2 text-xs"><input v-model="rec.scrap_abnormal" type="checkbox" class="rounded" /> الهالك غير طبيعي (يُحمَّل خسارة لا على التكلفة)</label>
                        <button type="submit" :disabled="rec.processing" class="col-span-2 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold">استلام</button>
                        <p v-if="rec.errors.quantity || rec.errors.order" class="col-span-2 text-xs text-rose-600">{{ rec.errors.quantity || rec.errors.order }}</p>
                    </template>
                </form>
            </div>
        </div>

        <div :class="card">
            <h2 class="font-black text-slate-800 text-sm mb-3">ورقة التكلفة: المخطط والفعلي</h2>
            <div class="grid md:grid-cols-3 gap-4 text-xs">
                <div><p class="text-slate-400 font-bold mb-1">المواد</p><p class="font-mono">مخطط {{ money(sheet.planned.material) }}</p><p class="font-mono font-black">فعلي {{ money(sheet.actual.material) }}</p></div>
                <div><p class="text-slate-400 font-bold mb-1">التحويل (أجور + مصروفات)</p><p class="font-mono">مخطط {{ money(sheet.planned.conversion) }}</p><p class="font-mono font-black">فعلي {{ money(sheet.actual.labour + sheet.actual.overhead) }}</p></div>
                <div><p class="text-slate-400 font-bold mb-1">الإجمالي</p><p class="font-mono">مخطط {{ money(sheet.planned.total) }}</p><p class="font-mono font-black">فعلي {{ money(sheet.actual.total) }}</p><p v-if="o.status === 'closed'" class="font-mono text-rose-600">فروقات {{ money(sheet.variance) }}</p></div>
            </div>
        </div>
    </AppLayout>
</template>
