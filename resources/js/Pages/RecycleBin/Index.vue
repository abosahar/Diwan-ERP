<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { date } from '@/utils/format';

defineProps({ rows: Array, days: Number });
const restore = (r) => router.post(route('recycle-bin.restore', [r.type, r.id]), {}, { preserveScroll: true });
const purge = (r) => { if (confirm('حذف نهائي لا يمكن التراجع عنه. متابعة؟')) router.delete(route('recycle-bin.purge', [r.type, r.id]), { preserveScroll: true }); };
</script>

<template>
    <AppLayout>
        <Head title="سلة المحذوفات" />
        <PageHeader title="سلة المحذوفات" :subtitle="`تُحفظ المستندات المحذوفة ${days} يومًا ثم تُمسح نهائيًا`" :crumbs="[{ label: 'الإعدادات' }, { label: 'سلة المحذوفات' }]" />
        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500"><tr><th class="text-start px-4 py-2">النوع</th><th class="text-start px-4 py-2">الرقم</th><th class="text-start px-4 py-2">الطرف</th><th class="px-4 py-2 text-end">الإجمالي</th><th class="px-4 py-2">تاريخ الحذف</th><th class="px-4 py-2">يُمسح في</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="r in rows" :key="r.type + r.id" class="border-t border-slate-100">
                        <td class="px-4 py-2">{{ r.kind }}</td>
                        <td class="px-4 py-2 font-mono">{{ r.number }}</td>
                        <td class="px-4 py-2">{{ r.party || '—' }}</td>
                        <td class="px-4 py-2 text-end font-mono">{{ Number(r.total).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}</td>
                        <td class="px-4 py-2 text-center font-mono">{{ date(r.deleted_at) }}</td>
                        <td class="px-4 py-2 text-center font-mono text-slate-500">{{ date(r.expires_at) }}</td>
                        <td class="px-4 py-2 text-end whitespace-nowrap">
                            <button v-if="$can('admin.recycle_bin.manage')" @click="restore(r)" class="text-emerald-600 font-bold me-3">استعادة</button>
                            <button v-if="$can('admin.recycle_bin.manage')" @click="purge(r)" class="text-rose-600 font-bold">حذف نهائي</button>
                        </td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="7" class="py-10 text-center text-slate-400">السلة فارغة</td></tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
