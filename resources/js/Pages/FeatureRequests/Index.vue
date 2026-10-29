<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Field from '@/Components/UI/Field.vue';

const props = defineProps({ repo: String });
const page = usePage();
const kinds = { feature: 'ميزة جديدة', improvement: 'تحسين على ميزة قائمة' };
const form = reactive({ kind: 'feature', area: '', title: '', description: '' });
const input = 'w-full rounded-xl border-slate-200 text-xs';
const issues = `https://github.com/${props.repo}/issues`;

// Opens GitHub's "new issue" page pre-filled; the user reviews and submits it there.
const send = () => {
    const body = [
        `**النوع:** ${kinds[form.kind]}`,
        form.area ? `**الشاشة أو القسم:** ${form.area}` : '',
        `**إصدار الديوان:** ${page.props.brand?.version}`,
        '',
        form.description,
    ].filter((l, i) => l !== '' || i > 0).join('\n');
    const query = new URLSearchParams({ title: form.title, body, labels: form.kind === 'feature' ? 'enhancement' : 'improvement' });
    window.open(`${issues}/new?${query}`, '_blank', 'noopener');
};
</script>

<template>
    <AppLayout>
        <Head title="طلبات المميزات" />
        <PageHeader title="طلب ميزة أو تحسين" subtitle="يصل طلبك إلى مطوّر النظام مباشرة على GitHub" :crumbs="[{ label: 'التحديثات' }, { label: 'طلبات المميزات' }]">
            <template #actions>
                <Link :href="route('whats-new.index')" class="px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700">أبرز التحديثات</Link>
                <a :href="issues" target="_blank" rel="noopener" class="px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700">الطلبات المفتوحة والتصويت</a>
            </template>
        </PageHeader>
        <form @submit.prevent="send" class="bg-white border border-slate-200 rounded-2xl p-5 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
            <Field label="نوع الطلب *">
                <select v-model="form.kind" :class="input"><option v-for="(label, key) in kinds" :key="key" :value="key">{{ label }}</option></select>
            </Field>
            <Field label="الشاشة أو القسم" hint="مثل: فواتير المبيعات، الرواتب"><input v-model="form.area" :class="input" /></Field>
            <Field label="عنوان الطلب *" class="md:col-span-2"><input v-model="form.title" required maxlength="200" :class="input" /></Field>
            <Field label="الوصف *" hint="ما الذي تريده، ولماذا، وكيف تتخيل عمله؟" class="md:col-span-2"><textarea v-model="form.description" required rows="6" :class="input"></textarea></Field>
            <p class="md:col-span-2 text-slate-500">عند الإرسال تُفتح صفحة GitHub بالطلب جاهزًا لتراجعه وتؤكد إرساله بحسابك (يلزم حساب GitHub مجاني). يمكن للآخرين التصويت عليه بـ 👍.</p>
            <div class="md:col-span-2 pt-2 border-t border-slate-100">
                <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 text-white font-bold">إرسال إلى المطوّر عبر GitHub</button>
            </div>
        </form>
    </AppLayout>
</template>
