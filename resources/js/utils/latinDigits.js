/**
 * Numbers are always typed and shown with Western digits (0-9). Fields marked
 * data-num (every numeric input) turn Arabic-Indic / Persian digits and the
 * Arabic decimal mark into 0-9 and "." while typing or pasting, and drop
 * anything that is not part of a number. Runs before Vue's own listener
 * (document capture), so v-model always sees the cleaned value.
 */
const arabicIndic = /[٠-٩]/g;
const persian = /[۰-۹]/g;

export const toLatinDigits = (value) => String(value ?? '')
    .replace(arabicIndic, (d) => String(d.charCodeAt(0) - 0x0660))
    .replace(persian, (d) => String(d.charCodeAt(0) - 0x06f0))
    .replace(/٫/g, '.')
    .replace(/٬/g, '');

export const cleanNumberText = (value) => toLatinDigits(value).replace(/[^0-9.\-+eE]/g, '');

export function installLatinDigits() {
    document.addEventListener('input', (event) => {
        const el = event.target;
        if (!(el instanceof HTMLInputElement) || !el.hasAttribute('data-num') || event.isComposing) return;
        const cleaned = cleanNumberText(el.value);
        if (cleaned === el.value) return;
        const caret = el.selectionStart;
        const removed = el.value.length - cleaned.length;
        el.value = cleaned;
        if (caret !== null) el.setSelectionRange(Math.max(0, caret - removed), Math.max(0, caret - removed));
    }, true);
}
