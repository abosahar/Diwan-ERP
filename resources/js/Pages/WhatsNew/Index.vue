<script setup>
import { onMounted } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { markNotesSeen } from '@/composables/useWhatsNew';

const props = defineProps({ releases: Array });
const page = usePage();
const types = {
    new: ['جديد', 'bg-emerald-50 text-emerald-700'],
    improved: ['تحسين', 'bg-indigo-50 text-indigo-700'],
    fixed: ['إصلاح', 'bg-amber-50 text-amber-700'],
};
onMounted(() => markNotesSeen(props.releases[0]?.version));
</script>

<template>
    <AppLayout>
        <Head title="أبرز التحديثات" />
        <PageHeader title="أبرز التحديثات" :subtitle="`الإصدار الحالي ${page.props.brand?.version}`" :crumbs="[{ label: 'التحديثات' }, { label: 'أبرز التحديثات' }]">
            <template #actions>
                <Link :href="route('feature-requests.index')" class="px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700">طلبات المميزات</Link>
            </template>
        </PageHeader>

        <div class="space-y-4">
            <section v-for="r in releases" :key="r.version" class="bg-white border border-slate-200 rounded-2xl p-5">
                <div class="flex flex-wrap items-baseline gap-3 mb-3">
                    <span class="px-2.5 py-1 rounded-lg bg-indigo-600 text-white text-xs font-black font-mono">v{{ r.version }}</span>
                    <h2 class="text-sm font-black text-slate-900">{{ r.title }}</h2>
                    <span class="text-3xs text-slate-400 font-mono ms-auto">{{ r.date }}</span>
                </div>
                <ul class="space-y-2 text-xs">
                    <li v-for="(item, i) in r.items" :key="i" class="flex items-start gap-2">
                        <span :class="types[item[0]][1]" class="px-2 py-0.5 rounded-md font-bold text-3xs flex-shrink-0 mt-0.5">{{ types[item[0]][0] }}</span>
                        <span class="text-slate-700 leading-relaxed">{{ item[1] }}</span>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
