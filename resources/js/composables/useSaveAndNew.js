import { nextTick, onBeforeUnmount, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * «حفظ وإضافة جديد» on create pages: a secondary button next to the page's save button
 * (injected beside the first submit button of the main form) and Ctrl/⌘+Enter. The visit
 * carries an X-Save-And-New header; the server (SaveAndNew middleware) sends the user back
 * to the empty create page and the form is reset here. Edit pages are left alone.
 * Called from useUnsavedGuard, so every guarded form page gets it.
 */
let pending = false;
let inflight = false;

export function useSaveAndNew(form) {
    const isCreate = () => /\.create$/.test(route().current() || '');
    let button = null;

    const saveAndNew = () => {
        const target = document.querySelector('main form') || document.querySelector('form');
        if (!target) return;
        pending = true;
        target.requestSubmit();
    };

    const offBefore = router.on('before', (event) => {
        if (!pending) return;
        pending = false;
        if (event.detail.visit.method === 'post') {
            event.detail.visit.headers = { ...(event.detail.visit.headers || {}), 'X-Save-And-New': '1' };
            inflight = true;
        }
    });
    // Saved: the same create page is shown again, so start from an empty form.
    const offSuccess = router.on('success', () => {
        if (!inflight) return;
        inflight = false;
        form?.reset();
        form?.clearErrors();
    });
    const offError = router.on('error', () => { inflight = false; });

    const onKey = (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && isCreate()) {
            e.preventDefault();
            saveAndNew();
        }
    };

    onMounted(async () => {
        window.addEventListener('keydown', onKey);
        if (!isCreate()) return;
        await nextTick();
        const submit = document.querySelector('main form button[type=submit]') || document.querySelector('form button[type=submit]');
        if (!submit || submit.dataset.saveAndNewHost) return;
        submit.dataset.saveAndNewHost = '1';
        button = document.createElement('button');
        button.type = 'button';
        button.textContent = 'حفظ وإضافة جديد';
        button.dataset.saveAndNew = '1';
        button.className = 'px-4 py-2 rounded-xl border border-indigo-200 bg-indigo-50 text-indigo-700 text-xs font-bold';
        button.addEventListener('click', saveAndNew);
        submit.after(button);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('keydown', onKey);
        offBefore();
        offSuccess();
        offError();
        button?.remove();
    });
}
