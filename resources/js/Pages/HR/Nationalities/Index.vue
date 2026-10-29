<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import { PencilSquareIcon, PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ nationalities: Array, stats: Object, filters: Object });
const f = reactive({ search: props.filters.search || '', status: props.filters.status || 'active' });
const apply = () => router.get(route('hr.nationalities.index'), { search: f.search || undefined, status: f.status }, { preserveState: true, replace: true });
// Regional-indicator letters make the flag emoji of an ISO2 code.
const flag = (code) => String.fromCodePoint(...[...String(code).toUpperCase()].map((c) => 127397 + c.charCodeAt(0)));
</script>

<template>
    <AppLayout>
        <Head title="الجنسيات" />
        <PageHeader title="الجنسيات" subtitle="قائمة الجنسيات المتاحة عند تسجيل الموظفين" :crumbs="[{ label: 'الموارد البشرية' }, { label: 'الجنسيات' }]">
            <template #actions>
                <Link v-if="$can('hr.nationalities.create')" :href="route('hr.nationalities.create')" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold"><PlusIcon class="w-4 h-4" /> إضافة جنسية</Link>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
            <StatCard label="إجمالي الجنسيات المسجلة" :value="stats.total" />
            <StatCard label="جنسيات نشطة" :value="stats.active" />
            <StatCard label="جنسيات معطلة" :value="stats.inactive" />
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl p-3 mb-4 flex flex-wrap gap-2 text-xs">
            <input v-model="f.search" @input="apply" placeholder="بحث بالاسم أو ISO2..." class="flex-1 min-w-[12rem] rounded-xl border-slate-200 text-xs" />
            <select v-model="f.status" @change="apply" class="rounded-xl border-slate-200 text-xs">
                <option value="active">نشط فقط</option><option value="inactive">معطّل فقط</option><option value="all">الكل</option>
            </select>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500"><tr>
                    <th class="px-4 py-2 text-start w-16">الترتيب</th><th class="px-4 py-2 text-start w-16">العلم</th><th class="px-4 py-2 text-start">الجنسية بالعربي</th><th class="px-4 py-2 text-start">بالإنجليزي</th>
                    <th class="px-4 py-2 text-start">ISO2</th><th class="px-4 py-2 text-start">عدد الموظفين</th><th class="px-4 py-2 text-start">الحالة</th><th class="px-4 py-2 w-16"></th>
                </tr></thead>
                <tbody>
                    <tr v-for="(n, i) in nationalities" :key="n.id" class="border-t border-slate-100">
                        <td class="px-4 py-2 text-slate-400">{{ i + 1 }}</td>
                        <td class="px-4 py-2 text-lg">{{ flag(n.code) }}</td>
                        <td class="px-4 py-2 font-bold">{{ n.name_ar }}</td>
                        <td class="px-4 py-2 text-slate-500">{{ n.name_en }}</td>
                        <td class="px-4 py-2"><span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 font-mono font-bold">{{ n.code }}</span></td>
                        <td class="px-4 py-2 font-mono">{{ n.employees }}</td>
                        <td class="px-4 py-2"><span :class="n.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'" class="px-2 py-0.5 rounded-full border text-[11px] font-bold">{{ n.is_active ? 'نشط' : 'معطّل' }}</span></td>
                        <td class="px-4 py-2 text-end"><Link v-if="$can('hr.nationalities.edit')" :href="route('hr.nationalities.edit', n.id)" class="text-slate-400 hover:text-indigo-600"><PencilSquareIcon class="w-4 h-4" /></Link></td>
                    </tr>
                    <tr v-if="!nationalities.length"><td colspan="8" class="py-10 text-center text-slate-400">لا توجد نتائج.</td></tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
