import { onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import { confirmUnsaved } from '@/composables/useDialog';
import { useSaveAndNew } from '@/composables/useSaveAndNew';

/**
 * Leaving a create / edit page with unsaved changes asks first: save, leave without
 * saving, or stay. Use on every full-page form:  useUnsavedGuard(form)  right after
 * `const form = useForm(...)`. Only navigation to another page (GET) is guarded; the
 * form's own post/put and same-page reloads (filters) are not.
 */
let leaving = false;
// Dirty checks of the forms on the current page.
const guarded = new Set();

const submitPageForm = () => (document.querySelector('main form') || document.querySelector('form'))?.requestSubmit();

/**
 * Before an action that reloads the page in place (switching the branch or fiscal
 * year): resolves true to go ahead. With unsaved changes it asks first, and «حفظ»
 * submits the form instead of going ahead.
 */
export async function confirmLeaving() {
    if (![...guarded].some((dirty) => dirty())) return true;

    const choice = await confirmUnsaved();
    if (choice === 'save') submitPageForm();

    return choice === 'discard';
}

export function useUnsavedGuard(form) {
    useSaveAndNew(form);
    const dirty = () => !!form.isDirty && !form.processing;
    guarded.add(dirty);

    const stop = router.on('before', (event) => {
        const visit = event.detail.visit;
        if (leaving || !dirty() || visit.method !== 'get' || visit.url.pathname === window.location.pathname) return;

        confirmUnsaved().then((choice) => {
            if (choice === 'save') {
                submitPageForm();
            } else if (choice === 'discard') {
                leaving = true;
                router.visit(visit.url.href, { method: visit.method, data: visit.data, replace: visit.replace, preserveScroll: visit.preserveScroll, preserveState: visit.preserveState });
                setTimeout(() => { leaving = false; }, 500);
            }
        });

        return false;
    });

    const beforeUnload = (e) => {
        if (leaving || !dirty()) return;
        e.preventDefault();
        e.returnValue = '';
    };
    window.addEventListener('beforeunload', beforeUnload);

    onBeforeUnmount(() => {
        guarded.delete(dirty);
        stop();
        window.removeEventListener('beforeunload', beforeUnload);
    });
}
