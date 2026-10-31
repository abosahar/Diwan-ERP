import { router } from '@inertiajs/vue3';

/**
 * Back / Forward show a page as the browser history kept it, with the data it
 * had then. When the company, branch or fiscal year («سياق العمل») has changed
 * since, that data belongs to the old context: the page is loaded again.
 */
const contextOf = (page) => JSON.stringify([
    page?.props?.tenant?.id ?? null,
    page?.props?.branchSwitcher?.active ?? null,
    page?.props?.fiscalSwitcher?.active ?? null,
]);

export function installContextHistoryGuard(initialPage) {
    // The context of the latest page the server sent.
    let current = contextOf(initialPage);
    // A page opened with Back / Forward is restored from history, not from the server.
    let restoring = performance.getEntriesByType?.('navigation')?.[0]?.type === 'back_forward';

    window.addEventListener('popstate', () => { restoring = true; });
    router.on('start', () => { restoring = false; });
    router.on('success', (event) => { current = contextOf(event.detail.page); });
    router.on('navigate', (event) => {
        if (!restoring) return;
        restoring = false;
        if (contextOf(event.detail.page) !== current) {
            router.visit(window.location.href, { replace: true, preserveScroll: true });
        }
    });
}
