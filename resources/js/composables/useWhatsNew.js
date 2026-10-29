import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const KEY = 'diwan.notes_seen';

const read = () => {
    try { return localStorage.getItem(KEY) || ''; } catch { return ''; }
};

/** Remember (per browser) the latest release notes the user has opened. */
export const markNotesSeen = (version) => {
    if (!version) return;
    try { localStorage.setItem(KEY, version); } catch { /* storage unavailable */ }
};

/** True while the newest release notes have not been opened yet. */
export const useUnreadNotes = () => {
    const page = usePage();
    return computed(() => {
        const latest = page.props.brand?.latest_notes;
        return !!latest && read() !== latest && !window.location.pathname.startsWith('/whats-new');
    });
};
