import { onMounted, onUnmounted, ref } from 'vue';

/**
 * A dropdown list under a field in a table that scrolls sideways (document lines):
 * placed with `position: fixed`, because an absolutely placed list would be cut off
 * by the table's scroll box. It follows the field when the page or the table scrolls
 * and opens upwards when the field is low on the screen.
 *
 *   const { style, anchor } = useFixedDropdown(() => listIsOpen);
 *   <input @focus="anchor($event.target)">   <div v-if="listIsOpen" :style="style" class="fixed ...">
 */
export function useFixedDropdown(isOpen) {
    const style = ref({});
    let field = null;

    const place = () => {
        const r = field?.getBoundingClientRect();
        if (!r) return;
        const below = window.innerHeight - r.bottom;
        style.value = {
            left: `${r.left}px`,
            width: `${r.width}px`,
            // The lists are at most 16rem (max-h-64) tall.
            ...(below < 270 && r.top > below ? { bottom: `${window.innerHeight - r.top + 4}px` } : { top: `${r.bottom + 4}px` }),
        };
    };
    const anchor = (el) => { field = el; place(); };
    const follow = () => { if (isOpen()) place(); };

    onMounted(() => {
        window.addEventListener('scroll', follow, true);
        window.addEventListener('resize', follow);
    });
    onUnmounted(() => {
        window.removeEventListener('scroll', follow, true);
        window.removeEventListener('resize', follow);
    });

    return { style, anchor };
}
