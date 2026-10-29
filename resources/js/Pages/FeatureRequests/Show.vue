<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Field from '@/Components/UI/Field.vue';
import { date } from '@/utils/format';
import { statusTone } from './status';

const props = defineProps({ item: Object, statuses: Object, kinds: Object });
const form = useForm({ status: props.item.status, admin_note: props.item.admin_note || '', released_in: props.item.released_in || '' });
const vote = () => router.post(route('feature-requests.vote', props.item.id), {}, { preserveScroll: true });
const input = 'w-full rounded-xl border-slate-200 text-xs';
</script>

<template>
    <AppLayout>
        <Head :title="item.title" />
        <PageHeader :title="item.title" :crumbs="[{ label: 'التحديثات' }, { label: 'طلبات المميزات', href: route('feature-requests.index') }, { label: 'تفاصيل الطلب' }]" />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 text-xs">
            <section class="lg:col-span-2 bg-white border border-slate-200 rounded-2xl p-5 space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span :class="statusTone[item.status]" class="px-2 py-0.5 rounded-md text-3xs font-bold">{{ statuses[item.status] }}</span>
                    <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 text-3xs font-bold">{{ kinds[item.kind] }}</span>
                    <span v-if="item.area" class="text-slate-500">{{ item.area }}</span>
                    <span v-if="item.released_in" class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 text-3xs font-bold font-mono">نُفِّذ في v{{ item.released_in }}</span>
                </div>
                <p class="whitespace-pre-line leading-relaxed text-slate-800">{{ item.description }}</p>
                <p class="text-3xs text-slate-400">{{ item.author?.name || '—' }} · {{ date(item.created_at) }}</p>
                <div v-if="item.admin_note" class="p-3 rounded-xl bg-indigo-50/60 border border-indigo-100">
                    <b class="text-indigo-900 block mb-1">ملاحظة فريق الإدارة</b>
                    <p class="whitespace-pre-line text-slate-700">{{ item.admin_note }}</p>
                </div>
                <button type="button" @click="vote" :class="item.voted ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-700 border-slate-200'" class="px-4 py-2 rounded-xl border font-bold">
                    ▲ {{ item.voted ? 'تم التصويت' : 'صوّت لهذا الطلب' }} ({{ item.votes_count }})
                </button>
            </section>

            <form v-if="$can('admin.feature_requests.manage')" @submit.prevent="form.put(route('feature-requests.update', item.id), { preserveScroll: true })" class="bg-white border border-slate-200 rounded-2xl p-5 space-y-3 self-start">
                <h3 class="font-black text-slate-900">إدارة الطلب</h3>
                <Field label="الحالة" :error="form.errors.status"><select v-model="form.status" :class="input"><option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option></select></Field>
                <Field label="نُفِّذ في الإصدار" :error="form.errors.released_in" hint="مثل 1.5.0"><input v-model="form.released_in" :class="input" class="font-mono" /></Field>
                <Field label="ملاحظة للمستخدمين" :error="form.errors.admin_note"><textarea v-model="form.admin_note" rows="4" :class="input"></textarea></Field>
                <button type="submit" :disabled="form.processing" class="w-full px-4 py-2 rounded-xl bg-indigo-600 text-white font-bold">حفظ</button>
            </form>
        </div>
    </AppLayout>
</template>
