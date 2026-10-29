<script setup>
import DatePicker from '@/Components/UI/DatePicker.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Field from '@/Components/UI/Field.vue';

const props = defineProps({ stages: Array });
const now = new Date();
const form = useForm({
    process_stage_id: props.stages?.[0]?.id || '',
    date_from: new Date(now.getFullYear(), now.getMonth(), 1, 12).toISOString().slice(0, 10),
    date_to: new Date(now.getFullYear(), now.getMonth() + 1, 0, 12).toISOString().slice(0, 10),
});
const input = 'w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600';
</script>

<template>
    <AppLayout>
        <Head title="فتح فترة تكلفة" />
        <PageHeader title="فتح فترة تكلفة" :crumbs="[{ label: 'التصنيع' }, { label: 'فترات التكلفة', href: route('manufacturing.periods.index') }, { label: 'جديدة' }]" />
        <form @submit.prevent="form.post(route('manufacturing.periods.store'))" class="bg-white border border-slate-200/80 rounded-2xl p-6 space-y-5">
            <div class="grid md:grid-cols-3 gap-4">
                <Field label="المرحلة *" :error="form.errors.process_stage_id || form.errors.stage"><select v-model="form.process_stage_id" :class="input" required><option v-for="s in stages" :key="s.id" :value="s.id">{{ s.code }} — {{ s.name }}</option></select></Field>
                <Field label="من *"><DatePicker v-model="form.date_from" :class="input" /></Field>
                <Field label="إلى *" :error="form.errors.date_to"><DatePicker v-model="form.date_to" :class="input" /></Field>
            </div>
            <p class="text-xs text-slate-500">تبدأ الفترة بما بقي تحت التشغيل في إغلاق الفترة السابقة للمرحلة نفسها.</p>
            <div class="flex gap-2 pt-2 border-t border-slate-100">
                <button type="submit" :disabled="form.processing" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold disabled:opacity-50">فتح الفترة</button>
                <Link :href="route('manufacturing.periods.index')" class="px-6 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600">إلغاء</Link>
            </div>
        </form>
    </AppLayout>
</template>
