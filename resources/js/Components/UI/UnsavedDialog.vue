<script setup>
import Dialog from '@/Components/UI/Dialog.vue';
import { unsavedState as s, settleUnsaved } from '@/composables/useDialog';
import { ArrowDownTrayIcon } from '@heroicons/vue/24/outline';

/** Host of the «لديك تغييرات غير محفوظة» dialog (mounted once at the app root, like PromptDialog). */
</script>

<template>
    <Dialog :show="s.open" title="لديك تغييرات غير محفوظة" size="md" @close="settleUnsaved('stay')">
        <p class="text-sm text-slate-600" data-unsaved-dialog>لديك تغييرات لم يتم حفظها. هل تريد حفظ التغييرات قبل مغادرة الصفحة؟</p>
        <template #footer>
            <button type="button" @click="settleUnsaved('save')" class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-indigo-600 text-white text-sm font-bold" data-unsaved-save>
                <ArrowDownTrayIcon class="w-4 h-4" /> حفظ
            </button>
            <button type="button" @click="settleUnsaved('discard')" class="px-5 py-2 rounded-xl border border-slate-200 text-slate-700 text-sm font-bold" data-unsaved-discard>إلغاء ومغادرة الصفحة</button>
        </template>
    </Dialog>
</template>
