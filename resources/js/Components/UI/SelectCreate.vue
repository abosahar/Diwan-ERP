<script setup>
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref } from 'vue';
import { ChevronUpDownIcon, PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { quickCreate } from '@/quickCreate';
import { canRoute } from '@/permissions';
import { useFixedDropdown } from '@/composables/useFixedDropdown';

const CustomerModal = defineAsyncComponent(() => import('@/Pages/Sales/Clients/CustomerModal.vue'));
const QuickCreateDialog = defineAsyncComponent(() => import('@/Components/UI/QuickCreateDialog.vue'));

/**
 * The app's dropdown for any list the user builds from the panel (customers,
 * warehouses, accounts, categories...). It searches while typing and, when the
 * typed text matches nothing, offers  إنشاء "text"  which opens the entity's full
 * create form in a dialog and selects the new record. Use it instead of a bare
 * <select> for such lists, always with `entity` (see resources/js/quickCreate.js).
 */
const props = defineProps({
    modelValue: { type: [String, Number, null], default: '' },
    options: { type: Array, default: () => [] },
    entity: { type: String, default: '' },
    // How to read an option: value key, and label key or function.
    valueKey: { type: String, default: 'id' },
    label: { type: [String, Function], default: 'label' },
    placeholder: { type: String, default: 'اختر...' },
    allowEmpty: { type: Boolean, default: true },
    emptyLabel: { type: String, default: '—' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'created']);

const root = ref(null);
const open = ref(false);
const query = ref('');
const creating = ref(false);
const extra = ref([]);

const labelOf = (o) => String(typeof props.label === 'function' ? props.label(o) : (o[props.label] ?? o.label ?? o.name ?? o.name_ar ?? ''));
const all = computed(() => [...props.options, ...extra.value.filter((e) => !props.options.some((o) => o[props.valueKey] === e[props.valueKey]))]);
const selected = computed(() => all.value.find((o) => String(o[props.valueKey]) === String(props.modelValue)));
const term = computed(() => query.value.trim());
const filtered = computed(() => {
    const q = term.value.toLowerCase();
    return (q ? all.value.filter((o) => labelOf(o).toLowerCase().includes(q)) : all.value).slice(0, 100);
});
const config = computed(() => quickCreate[props.entity] || (props.entity === 'customer' ? { route: 'sales.clients.store' } : null));
const canCreate = computed(() => !!config.value && !props.disabled && canRoute(config.value.route));
const exact = computed(() => all.value.some((o) => labelOf(o).trim().toLowerCase() === term.value.toLowerCase()));
const showCreate = computed(() => canCreate.value && term.value !== '' && !exact.value);

const shown = computed(() => (open.value ? query.value : (selected.value ? labelOf(selected.value) : '')));
// The list is fixed under the field: in a line table that scrolls sideways or in a
// dialog body, an absolute one would be cut off by the scroll box. At least 12rem wide.
const { style: listStyle, anchor } = useFixedDropdown(() => open.value, { minWidth: 192 });
const openList = (el) => { open.value = true; anchor(el); };
const choose = (value) => { emit('update:modelValue', value); open.value = false; query.value = ''; };
const startCreate = () => { open.value = false; creating.value = true; };
const created = (record) => {
    extra.value.push(record);
    creating.value = false;
    query.value = '';
    emit('created', record);
    emit('update:modelValue', record[props.valueKey]);
};

const outside = (e) => { if (open.value && root.value && !root.value.contains(e.target)) { open.value = false; query.value = ''; } };
onMounted(() => document.addEventListener('mousedown', outside));
onBeforeUnmount(() => document.removeEventListener('mousedown', outside));
</script>

<template>
    <div ref="root" class="relative" data-select-create>
        <input
            type="text" :value="shown" :placeholder="placeholder" :disabled="disabled" autocomplete="off"
            @focus="openList($event.target)" @input="query = $event.target.value; openList($event.target)"
            @keydown.enter.prevent="showCreate ? startCreate() : (filtered[0] && choose(filtered[0][valueKey]))"
            @keydown.esc="open = false; query = ''"
            class="w-full rounded-xl border-slate-200 text-xs ps-3 pe-8 py-2 focus:border-indigo-600 focus:ring-indigo-600 disabled:bg-slate-50"
        />
        <input v-if="required" type="text" tabindex="-1" aria-hidden="true" :value="modelValue" required class="absolute inset-0 opacity-0 pointer-events-none" />
        <span class="absolute inset-y-0 end-2 flex items-center text-slate-400 pointer-events-none"><ChevronUpDownIcon class="w-4 h-4" /></span>
        <div v-if="open" :style="listStyle" data-select-list class="fixed z-40 bg-white border border-slate-200 rounded-xl shadow-lg max-h-64 overflow-y-auto p-1 text-xs">
            <button v-if="showCreate" type="button" @click="startCreate" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-start" data-create-option>
                <PlusIcon class="w-4 h-4 stroke-2" /> <span>إنشاء "{{ term }}"</span>
            </button>
            <button v-if="allowEmpty && !term" type="button" @click="choose('')" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-50 text-start"><XMarkIcon class="w-3.5 h-3.5" /> {{ emptyLabel }}</button>
            <button v-for="o in filtered" :key="o[valueKey]" type="button" @click="choose(o[valueKey])"
                class="w-full px-3 py-2 rounded-lg text-start hover:bg-slate-50" :class="String(o[valueKey]) === String(modelValue) ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700'">
                {{ labelOf(o) }}
            </button>
            <p v-if="!filtered.length && !showCreate" class="px-3 py-2 text-slate-400">لا توجد نتائج</p>
        </div>
        <CustomerModal v-if="creating && entity === 'customer'" :show="true" :initial-name="term" @close="creating = false" @saved="(c) => created({ ...c, label: c.name })" />
        <QuickCreateDialog v-else-if="creating" :entity="entity" :name="term" @close="creating = false" @created="created" />
    </div>
</template>
