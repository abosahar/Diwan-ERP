<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

// Product name and tagline come from config (APP_NAME), shared as page.props.brand.
const brand = computed(() => usePage().props.brand || {});

defineProps({
    size: {
        type: String,
        default: 'md', // sm, md, lg
    },
    showSubtitle: {
        type: Boolean,
        default: true,
    },
    white: {
        type: Boolean,
        default: false,
    },
    // Icon only (the collapsed sidebar rail).
    compact: {
        type: Boolean,
        default: false,
    }
});
</script>

<template>
    <div class="flex items-center gap-3 select-none">
        <!-- The Diwan mark (public/brand/diwan-mark.svg): green rounded square with stacked layers. -->
        <svg
            :class="[size === 'sm' ? 'w-8 h-8' : size === 'lg' ? 'w-14 h-14' : 'w-10 h-10', 'flex-shrink-0 drop-shadow-sm']"
            viewBox="0 0 120 120" role="img" aria-label="الديوان"
        >
            <defs>
                <linearGradient id="diwan-mark-gradient" x1="1" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#3be3a2" />
                    <stop offset="0.55" stop-color="#11998e" />
                    <stop offset="1" stop-color="#0b6466" />
                </linearGradient>
            </defs>
            <rect x="4" y="4" width="112" height="112" rx="28" fill="url(#diwan-mark-gradient)" />
            <g fill="none" stroke="#fff" stroke-width="7" stroke-linecap="round" stroke-linejoin="round">
                <path d="M60 29 L87 43.5 L60 58 L33 43.5 Z" />
                <path d="M33 60 L60 74.5 L87 60" />
                <path d="M33 75.5 L60 90 L87 75.5" />
            </g>
        </svg>

        <div v-if="!compact" class="flex flex-col leading-tight">
            <span 
                :class="[
                    size === 'sm' ? 'text-base font-bold' : size === 'lg' ? 'text-2xl font-extrabold' : 'text-xl font-bold',
                    white ? 'text-white' : 'text-slate-900',
                    'tracking-tight font-sans'
                ]"
            >
                {{ brand.name || 'الديوان' }}
            </span>
            <span 
                v-if="showSubtitle" 
                :class="[
                    size === 'sm' ? 'text-[10px]' : size === 'lg' ? 'text-xs' : 'text-[11px]',
                    white ? 'text-slate-400' : 'text-teal-600',
                    'font-medium tracking-wide'
                ]"
            >
                {{ brand.subtitle || 'نظام إدارة الأعمال المتكامل' }}
            </span>
        </div>
    </div>
</template>
