<script setup>
import GlobalSearch from '@/Components/GlobalSearch.vue';
import QuickMenus from '@/Components/QuickMenus.vue';
import { computed, ref } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { 
    Bars3Icon, 
    BellIcon, 
    DeviceTabletIcon,
    BuildingStorefrontIcon,
    ChevronDownIcon,
    UserCircleIcon,
    ArrowRightOnRectangleIcon,
    Cog6ToothIcon,
    ShieldCheckIcon,
    ArrowsPointingOutIcon,
    ArrowsPointingInIcon,
    GlobeAltIcon,
    CalendarDaysIcon
} from '@heroicons/vue/24/outline';
import LanguageSwitcher from './LanguageSwitcher.vue';
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue';

defineEmits(['toggleSidebar']);

const page = usePage();
const user = page.props.auth?.user;
const tenant = page.props.tenant;

// Working branch and fiscal year (chosen once here; every page follows).
const branchSwitcher = computed(() => page.props.branchSwitcher);
const fiscalSwitcher = computed(() => page.props.fiscalSwitcher);
const activeBranch = computed(() => branchSwitcher.value?.items.find((b) => b.id === branchSwitcher.value.active));
const manyCountries = computed(() => new Set((branchSwitcher.value?.items || []).map((b) => b.country)).size > 1);
const branchLabel = (b) => (manyCountries.value ? `${b.name} — ${b.country_name}` : b.name);
const switchBranch = (event) => {
    const value = event.target.value;
    router.post(route('context.branch'), { branch_id: value === 'all' ? 'all' : Number(value) }, { preserveScroll: true });
};
const switchYear = (event) => {
    router.post(route('context.fiscal-year'), { fiscal_year_id: Number(event.target.value) }, { preserveScroll: true });
};

const isFullscreen = ref(false);
const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen?.().then(() => (isFullscreen.value = true)).catch(() => {});
    } else {
        document.exitFullscreen?.().then(() => (isFullscreen.value = false)).catch(() => {});
    }
};

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <header class="h-16 bg-white border-b border-slate-200 sticky top-0 z-30 px-4 lg:px-8 flex items-center justify-between shadow-[0_1px_3px_0_rgba(0,0,0,0.02)]">
        <!-- Left / Right depending on RTL: Sidebar Toggle & Search Bar -->
        <div class="flex items-center gap-2 lg:gap-3 flex-1 min-w-0">
            <button 
                @click="$emit('toggleSidebar')" 
                type="button" 
                class="lg:hidden p-2 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition"
            >
                <Bars3Icon class="w-6 h-6" />
            </button>

            <!-- Global search palette (Ctrl+K) -->
            <GlobalSearch />
            <QuickMenus />

            <!-- Fiscal year and working branch -->
            <div v-if="branchSwitcher" class="flex items-center gap-2 sm:border-s sm:border-slate-200 sm:ps-4 shrink-0" data-context-selects>
                <label v-if="fiscalSwitcher?.items.length" class="hidden xl:flex items-center" :title="'السنة المالية'">
                    <CalendarDaysIcon class="w-4 h-4 text-slate-400 -me-6 ms-2 relative z-10 pointer-events-none" />
                    <select data-fiscal-select :value="fiscalSwitcher.active" @change="switchYear"
                        class="bg-slate-100 border border-slate-200 text-xs font-semibold rounded-lg ps-8 pe-7 py-2 text-slate-700 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        <option v-for="y in fiscalSwitcher.items" :key="y.id" :value="y.id">السنة المالية {{ y.name }}</option>
                    </select>
                </label>

                <label v-if="branchSwitcher.items.length > 1" class="flex items-center" title="الفرع الذي تعمل عليه">
                    <GlobeAltIcon class="w-4 h-4 text-indigo-600 -me-6 ms-2 relative z-10 pointer-events-none" />
                    <select data-branch-switch :value="branchSwitcher.active" @change="switchBranch"
                        class="max-w-[8.5rem] sm:max-w-[16rem] bg-indigo-50 border border-indigo-100 text-xs font-bold rounded-lg ps-8 pe-7 py-2 text-indigo-900 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        <option v-if="branchSwitcher.can_all" value="all">كل الفروع (عرض موحّد)</option>
                        <option v-for="b in branchSwitcher.items" :key="b.id" :value="b.id">{{ branchLabel(b) }}</option>
                    </select>
                </label>
                <span v-else-if="activeBranch" class="hidden md:inline-flex items-center gap-1.5 bg-slate-100 border border-slate-200 text-xs font-semibold rounded-lg px-3 py-2 text-slate-700">
                    <GlobeAltIcon class="w-4 h-4 text-slate-400" /> {{ activeBranch.name }}
                </span>
            </div>
        </div>

        <!-- Center / End: Company Badge, POS Button, Language, Notifications, User Menu -->
        <div class="flex items-center gap-2 sm:gap-3">
            <!-- Active Company & Branch Pill -->
            <div v-if="tenant" class="hidden 2xl:flex items-center gap-2 bg-indigo-50/80 text-indigo-900 border border-indigo-100 px-3 py-1.5 rounded-lg text-xs font-medium">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <BuildingStorefrontIcon class="w-4 h-4 text-indigo-600" />
                <span class="font-bold">{{ tenant.name }}</span>
            </div>

            <!-- Full screen -->
            <button type="button" @click="toggleFullscreen" title="ملء الشاشة"
                class="hidden 2xl:flex w-9 h-9 rounded-lg border border-slate-200 text-slate-500 hover:text-slate-800 hover:bg-slate-100 items-center justify-center transition-colors">
                <component :is="isFullscreen ? ArrowsPointingInIcon : ArrowsPointingOutIcon" class="w-4 h-4" />
            </button>

            <!-- Quick POS Cashier Action Button -->
            <Link 
                :href="route('pos.index')"
                class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold px-3 py-2 rounded-lg shadow-sm transition-colors"
                title="فتح نقطة البيع (الكاشير)"
            >
                <DeviceTabletIcon class="w-4 h-4" />
                <span class="hidden 2xl:inline">نقطة البيع (POS)</span>
            </Link>

            <!-- Language Switcher -->
            <div class="hidden sm:block"><LanguageSwitcher /></div>

            <!-- Notifications Icon -->
            <Link
                :href="route('notifications.index')"
                class="p-2 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors relative"
                title="الإشعارات"
            >
                <BellIcon class="w-5 h-5" />
                <span v-if="$page.props.unreadNotifications" class="absolute -top-0.5 -end-0.5 min-w-4 h-4 px-1 bg-red-500 text-white text-[10px] font-bold rounded-full ring-2 ring-white flex items-center justify-center">{{ $page.props.unreadNotifications > 99 ? '99+' : $page.props.unreadNotifications }}</span>
            </Link>

            <!-- User Dropdown Menu -->
            <Menu as="div" class="relative">
                <MenuButton class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-50 transition border border-transparent hover:border-slate-200">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-indigo-600 to-indigo-700 text-white flex items-center justify-center font-bold text-xs shadow-sm">
                        {{ user?.name ? user.name.charAt(0) : 'U' }}
                    </div>
                    <div class="hidden xl:flex flex-col text-start leading-tight">
                        <span class="text-xs font-bold text-slate-800">{{ user?.name }}</span>
                        <span class="text-[10px] text-slate-500">{{ user?.is_super_admin ? 'مدير النظام' : 'مدير المؤسسة' }}</span>
                    </div>
                    <ChevronDownIcon class="w-4 h-4 text-slate-400 hidden sm:block" />
                </MenuButton>

                <transition
                    enter-active-class="transition duration-100 ease-out"
                    enter-from-class="transform scale-95 opacity-0"
                    enter-to-class="transform scale-100 opacity-100"
                    leave-active-class="transition duration-75 ease-in"
                    leave-from-class="transform scale-100 opacity-100"
                    leave-to-class="transform scale-95 opacity-0"
                >
                    <MenuItems class="absolute end-0 mt-2 w-56 origin-top-right rounded-xl bg-white p-1.5 shadow-lg ring-1 ring-black/5 focus:outline-none z-50 text-xs divide-y divide-slate-100">
                        <div class="px-3 py-2.5">
                            <p class="font-bold text-slate-900">{{ user?.name }}</p>
                            <p class="text-slate-500 text-[11px] truncate">{{ user?.email }}</p>
                            <span v-if="user?.is_super_admin" class="mt-1.5 inline-flex items-center gap-1 bg-purple-50 text-purple-700 border border-purple-200 text-[10px] font-semibold px-2 py-0.5 rounded">
                                <ShieldCheckIcon class="w-3 h-3" />
                                Super Admin
                            </span>
                        </div>

                        <div class="py-1">
                            <MenuItem v-slot="{ active }">
                                <Link 
                                    :href="route('profile.edit')"
                                    :class="[active ? 'bg-indigo-50 text-indigo-600' : 'text-slate-700', 'flex items-center gap-2 px-3 py-2 rounded-lg font-medium transition']"
                                >
                                    <UserCircleIcon class="w-4 h-4" />
                                    <span>الملف الشخصي</span>
                                </Link>
                            </MenuItem>
                            <MenuItem v-slot="{ active }">
                                <Link 
                                    :href="route('settings.index')"
                                    :class="[active ? 'bg-indigo-50 text-indigo-600' : 'text-slate-700', 'flex items-center gap-2 px-3 py-2 rounded-lg font-medium transition']"
                                >
                                    <Cog6ToothIcon class="w-4 h-4" />
                                    <span>إعدادات المؤسسة</span>
                                </Link>
                            </MenuItem>
                            <MenuItem v-if="user?.is_super_admin" v-slot="{ active }">
                                <Link 
                                    :href="route('super-admin.dashboard')"
                                    :class="[active ? 'bg-purple-50 text-purple-700' : 'text-purple-600', 'flex items-center gap-2 px-3 py-2 rounded-lg font-medium transition']"
                                >
                                    <ShieldCheckIcon class="w-4 h-4" />
                                    <span>لوحة تحكم النظام العام</span>
                                </Link>
                            </MenuItem>
                        </div>

                        <div class="pt-1">
                            <MenuItem v-slot="{ active }">
                                <button 
                                    @click="logout"
                                    :class="[active ? 'bg-red-50 text-red-600' : 'text-red-600', 'w-full flex items-center gap-2 px-3 py-2 rounded-lg font-medium transition text-start']"
                                >
                                    <ArrowRightOnRectangleIcon class="w-4 h-4" />
                                    <span>تسجيل الخروج</span>
                                </button>
                            </MenuItem>
                        </div>
                    </MenuItems>
                </transition>
            </Menu>
        </div>
    </header>
</template>
