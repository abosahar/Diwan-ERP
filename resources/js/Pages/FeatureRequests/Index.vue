<script setup>
import { reactive, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { date } from '@/utils/format';
import { statusTone } from './status';

const props = defineProps({ requests: Object, filters: Object, statuses: Object, kinds: Object });
const f = reactive({ ...props.filters });
let timer;
watch(f, () => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('feature-requests.index'), { ...f, mine: f.mine ? 1 : undefined }, { preserveState: true, replace: true }), 250);
});
const vote = (r) => router.post(route('feature-requests.vote', r.id), {}, { preserveScroll: true });
const input = 'rounded-xl border-slate-200 text-xs';
</script>

<template>
    <AppLayout>
        <Head title="طلبات المميزات" />
        <PageHeader title="طلبات المميزات والتحسينات" subtitle="اقترح ميزة جديدة أو تحسينًا على ميزة قائمة، وصوّت على ما يهمك" :crumbs="[{ label: 'التحديثات' }, { label: 'طلبات المميزات' }]">
            <template #actions>
                <Link :href="route('whats-new.index')" class="px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700">أبرز التحديثات</Link>
                <Link :href="route('feature-requests.create')" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold">طلب جديد</Link>
            </template>
        </PageHeader>

        <div class="bg-white border border-slate-200 rounded-2xl p-3 flex flex-wrap items-center gap-2 mb-4">
            <input v-model="f.search" type="search" placeholder="بحث في الطلبات" :class="input" class="w-56" />
            <select v-model="f.status" :class="input">
                <option value="open">المفتوحة</option>
                <option value="all">الكل</option>
                <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
            </select>
            <select v-model="f.kind" :class="input">
                <option value="">كل الأنواع</option>
                <option v-for="(label, key) in kinds" :key="key" :value="key">{{ label }}</option>
            </select>
            <select v-model="f.sort" :class="input">
                <option value="votes">الأكثر تصويتًا</option>
                <option value="latest">الأحدث</option>
            </select>
            <label class="flex items-center gap-1.5 text-xs font-bold text-slate-600"><input type="checkbox" v-model="f.mine" class="rounded" /> طلباتي فقط</label>
        </div>

        <div class="space-y-2">
            <article v-for="r in requests.data" :key="r.id" class="bg-white border border-slate-200 rounded-2xl p-4 flex items-start gap-4">
                <button type="button" @click="vote(r)" :class="r.voted ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200 hover:border-indigo-300'" class="flex flex-col items-center justify-center w-14 py-2 rounded-xl border font-bold text-xs flex-shrink-0" :title="r.voted ? 'إلغاء التصويت' : 'تصويت'">
                    <span>▲</span><span class="font-mono text-sm">{{ r.votes_count }}</span>
                </button>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <Link :href="route('feature-requests.show', r.id)" class="font-black text-sm text-slate-900 hover:text-indigo-600">{{ r.title }}</Link>
                        <span :class="statusTone[r.status]" class="px-2 py-0.5 rounded-md text-3xs font-bold">{{ statuses[r.status] }}</span>
                        <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 text-3xs font-bold">{{ kinds[r.kind] }}</span>
                        <span v-if="r.released_in" class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 text-3xs font-bold font-mono">v{{ r.released_in }}</span>
                    </div>
                    <p class="text-xs text-slate-600 mt-1 line-clamp-2">{{ r.description }}</p>
                    <p class="text-3xs text-slate-400 mt-1.5">{{ r.author?.name || '—' }} · {{ date(r.created_at) }}<span v-if="r.area"> · {{ r.area }}</span></p>
                </div>
            </article>
            <p v-if="!requests.data.length" class="text-center text-slate-400 text-xs py-10">لا توجد طلبات مطابقة</p>
        </div>
        <Pagination :paginator="requests" />
    </AppLayout>
</template>
