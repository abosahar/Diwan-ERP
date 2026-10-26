import { ref, watch } from 'vue';

/**
 * Desktop sidebar: expanded (w-72) or a collapsed icon rail (w-20). Shared by
 * the sidebar and the layout (which pads its content by the same width) and
 * remembered in this browser.
 */
const KEY = 'diwan.sidebar.collapsed';

const read = () => {
    try { return localStorage.getItem(KEY) === '1'; } catch { return false; }
};

export const sidebarCollapsed = ref(read());

watch(sidebarCollapsed, (value) => {
    try { localStorage.setItem(KEY, value ? '1' : '0'); } catch { /* private mode: not remembered */ }
});

export const toggleSidebar = () => { sidebarCollapsed.value = !sidebarCollapsed.value; };
