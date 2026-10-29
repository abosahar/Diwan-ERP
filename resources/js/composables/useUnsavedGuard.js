import { onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import { confirmUnsaved } from '@/composables/useDialog';

/**
 * Leaving a create / edit page with unsaved changes asks first: save, leave without
 * saving, or stay. Use on every full-page form:  useUnsavedGuard(form)  right after
 * `const form = useForm(...)`. Only navigation to another page (GET) is guarded; the
 * form's own post/put and same-page reloads (filters) are not.
 */
let leaving = false;

export function useUnsavedGuard(form) {
    const dirty = () => !!form.isDirty && !form.processing;

    const stop = router.on('before', (event) => {
        const visit = event.detail.visit;
        if (leaving || !dirty() || visit.method !== 'get' || visit.url.pathname === window.location.pathname) return;

        confirmUnsaved().then((choice) => {
            if (choice === 'save') {
                (document.querySelector('main form') || document.querySelector('form'))?.requestSubmit();
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
        stop();
        window.removeEventListener('beforeunload', beforeUnload);
    });
}
