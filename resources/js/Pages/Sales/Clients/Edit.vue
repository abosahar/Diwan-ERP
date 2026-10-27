<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import CustomerModal from './CustomerModal.vue';

const props = defineProps({
    client: Object,
});

const handleClose = () => {
    router.visit(route('sales.clients.index'));
};

const handleSaved = () => {
    router.visit(route('sales.clients.index'));
};
</script>

<template>
    <AppLayout>
        <Head :title="`تعديل بيانات العميل - ${client.name}`" />

        <PageHeader title="تعديل بيانات العميل" :crumbs="[{ label: 'المبيعات' }, { label: 'العملاء', href: route('sales.clients.index') }, { label: 'تعديل' }]" />

        <div>
            <CustomerModal
                :show="true"
                page
                :customer="client"
                @close="handleClose"
                @saved="handleSaved"
            />
        </div>
    </AppLayout>
</template>
