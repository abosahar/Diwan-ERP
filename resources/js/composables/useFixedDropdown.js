import { onUnmounted, ref, watch } from 'vue';

/**
 * A dropdown list under a field inside a scroll box (document lines in a table that
 * scrolls sideways, a dialog body): placed with `position: fixed`, because an
 * absolutely placed list would be cut off by the box. It follows the field when the
 * page or a box scrolls, opens upwards when the field is low on the screen, and starts
 * at the field's start edge, so a list wider than its field (`minWidth`, in px) grows
 * the way the text runs, shifting back only where it would run off the screen.
 *
 *   const { style, anchor } = useFixedDropdown(() => listIsOpen);
 *   <input @focus="anchor($event.target)">   <div v-if="listIsOpen" :style="style" class="fixed ...">
 */
export function useFixedDropdown(isOpen, { minWidth = 0 } = {}) {
    const style = ref({});
    let field = null;
    let rtl = false;

    // `fixed` is relative to the screen, unless an ancestor has a transform, a filter or a
    // backdrop blur (the dialogs): then to that ancestor's box, which may scroll as well.
    // A hidden fixed probe beside the field measures the box the list is placed in.
    const frame = () => {
        const probe = document.createElement('div');
        probe.style.cssText = 'position:fixed;inset:0;visibility:hidden;pointer-events:none';
        field.parentNode.appendChild(probe);
        const box = probe.getBoundingClientRect();
        probe.remove();
        return box;
    };

    const place = () => {
        if (!field?.isConnected) return;
        const r = field.getBoundingClientRect();
        const box = frame();
        const width = Math.max(r.width, minWidth);
        const screen = document.documentElement.clientWidth;
        const start = rtl ? Math.max(r.right, Math.min(width + 8, screen)) : Math.min(r.left, Math.max(screen - width - 8, 0));
        const below = window.innerHeight - r.bottom;
        // The lists are at most 16rem (max-h-64) tall; on a short screen they get shorter.
        const up = below < 270 && r.top > below;
        style.value = {
            width: `${width}px`,
            maxHeight: `min(16rem, ${(up ? r.top : below) - 8}px)`,
            ...(rtl ? { right: `${box.right - start}px` } : { left: `${start - box.left}px` }),
            ...(up ? { bottom: `${box.bottom - r.top + 4}px` } : { top: `${r.bottom + 4 - box.top}px` }),
        };
    };
    const anchor = (el) => { field = el; rtl = getComputedStyle(el).direction === 'rtl'; place(); };

    // Listen only while the list is open: a form can hold many of these fields. Scrolling
    // inside the list itself does not move the field.
    const follow = (e) => { if (!(e.target instanceof Element) || e.target.contains(field)) place(); };
    const listen = (on) => {
        const method = on ? 'addEventListener' : 'removeEventListener';
        window[method]('scroll', follow, true);
        window[method]('resize', follow);
    };
    watch(isOpen, listen);
    onUnmounted(() => listen(false));

    return { style, anchor };
}
