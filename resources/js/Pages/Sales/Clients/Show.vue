<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import CustomerViewModal from './CustomerViewModal.vue';

const props = defineProps({
    client: Object,
});

const handleClose = () => router.visit(route('sales.clients.index'));
const handleEdit = () => router.visit(route('sales.clients.edit', props.client.id));
</script>

<template>
    <AppLayout>
        <Head :title="`عرض عميل - ${client.name}`" />
        <PageHeader :title="client.name" :crumbs="[{ label: 'المبيعات' }, { label: 'العملاء', href: route('sales.clients.index') }, { label: 'عرض' }]" />

        <CustomerViewModal :show="true" page :customer="client" @close="handleClose" @edit="handleEdit" />
    </AppLayout>
</template>
