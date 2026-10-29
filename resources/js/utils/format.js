import { usePage } from '@inertiajs/vue3';

/**
 * Number / money / date formatting shared by pages.
 */
const moneyFormatter = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const qtyFormatter = new Intl.NumberFormat('en-US', { maximumFractionDigits: 4 });

export function money(value) {
    const n = Number(value ?? 0);
    return Number.isFinite(n) ? moneyFormatter.format(n) : '0.00';
}

/** Accounting style: negatives in parentheses. */
export function amount(value) {
    const n = Number(value ?? 0);
    return n < 0 ? `(${money(-n)})` : money(n);
}

export function qty(value) {
    return qtyFormatter.format(Number(value ?? 0));
}

// App models (SerializesLocalDates) send the business time zone's wall clock
// with its offset (2026-10-29T02:49:31+03:00) and raw query results have no
// offset: both are shown as written. Laravel's own models (notifications, API
// tokens) and Carbon values put straight into props arrive in UTC
// (2026-10-28T23:49:31.000000Z) and are converted to the business time zone,
// which the server shares as page.props.timezone.
const zoneFormatters = {};

function zoneFormatter(timeZone) {
    if (!(timeZone in zoneFormatters)) {
        try {
            zoneFormatters[timeZone] = new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' });
        } catch {
            zoneFormatters[timeZone] = null; // Unknown to this browser: keep UTC.
        }
    }
    return zoneFormatters[timeZone];
}

/** The value as local wall-clock text, 2026-10-29T02:49:31. */
function local(value) {
    const s = String(value);
    if (!/(Z|[+-]00:?00)$/.test(s)) return s;
    const d = new Date(s.replace(/(\.\d{3})\d+/, '$1'));
    const formatter = zoneFormatter(usePage().props?.timezone || 'UTC');
    if (!formatter || Number.isNaN(d.getTime())) return s;
    const p = Object.fromEntries(formatter.formatToParts(d).map((part) => [part.type, part.value]));
    return `${p.year}-${p.month}-${p.day}T${p.hour}:${p.minute}:${p.second}`;
}

export function date(value) {
    return value ? local(value).substring(0, 10) : '—';
}

/** Timestamps (created_at...): "2026-10-29 02:49" in the business time zone. */
export function dateTime(value) {
    return value ? local(value).substring(0, 16).replace('T', ' ') : '—';
}

/** The business time zone for toLocaleString() and the like; undefined (the device's) if the browser lacks it. */
export function timeZone() {
    const zone = usePage().props?.timezone;
    return zone && zoneFormatter(zone) ? zone : undefined;
}

// Date fields default to the business time zone's day: from midnight to 03:00
// in Riyadh the UTC date (toISOString) is still yesterday, and on the 1st the
// previous month.

/** Today in the business time zone, 2026-10-29. */
export function today() {
    return local(new Date().toISOString()).substring(0, 10);
}

/** This month in the business time zone, 2026-10. */
export function thisMonth() {
    return today().substring(0, 7);
}

/** A YYYY-MM-DD date some days later (or earlier): addDays(today(), 30). */
export function addDays(day, days) {
    const d = new Date(`${day}T00:00:00Z`);
    d.setUTCDate(d.getUTCDate() + days);
    return d.toISOString().substring(0, 10);
}

/** A YYYY-MM month some months later (or earlier): addMonths(thisMonth(), -1) is last month. */
export function addMonths(month, months) {
    const d = new Date(`${month}-01T00:00:00Z`);
    d.setUTCMonth(d.getUTCMonth() + months);
    return d.toISOString().substring(0, 7);
}

/** Last day of a YYYY-MM month, 2026-10-31. */
export function monthEnd(month) {
    return addDays(`${addMonths(month, 1)}-01`, -1);
}

export function monthLabel(period) {
    const s = String(period);
    return `${s.substring(0, 4)}-${s.substring(4, 6)}`;
}

// Hijri (Umm al-Qura) date, e.g. "29 ربيع الآخر 1448 هـ". Noon avoids
// day shifts from the viewer's timezone.
const hijriFormatter = (() => {
    try {
        return new Intl.DateTimeFormat('ar-SA-u-ca-islamic-umalqura-nu-latn', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Asia/Riyadh' });
    } catch {
        return null;
    }
})();

export function hijri(value) {
    if (!value || !hijriFormatter) return '';
    const d = new Date(`${date(value)}T12:00:00+03:00`);
    return Number.isNaN(d.getTime()) ? '' : hijriFormatter.format(d);
}
