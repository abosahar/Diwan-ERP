<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import CustomerModal from './CustomerModal.vue';

const props = defineProps({
    nextCode: {
        type: String,
        default: '1-000001',
    },
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
        <Head title="إضافة عميل جديد" />

        <PageHeader title="إضافة عميل" :crumbs="[{ label: 'المبيعات' }, { label: 'العملاء', href: route('sales.clients.index') }, { label: 'جديد' }]" />

        <div>
            <CustomerModal
                :show="true"
                page
                :nextCode="nextCode"
                @close="handleClose"
                @saved="handleSaved"
            />
        </div>
    </AppLayout>
</template>
