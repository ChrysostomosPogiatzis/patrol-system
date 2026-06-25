<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

defineProps<{
    title: string;
}>();

const isSidebarOpen = ref(true);
const page = usePage();
const user = page.props.auth.user as any;
const tenantName = user.tenant?.name || 'Witbo Control';

const superadminTenants = computed(
    () => (page.props.auth as any).superadmin_tenants || [],
);
const overrideTenantId = ref((page.props.auth as any).override_tenant_id || '');

async function handleTenantSwitch() {
    try {
        await axios.post('/admin/api/superadmin/switch-tenant', {
            tenant_id: overrideTenantId.value
                ? Number(overrideTenantId.value)
                : null,
        });
        router.visit(window.location.href);
    } catch (e) {
        alert('Failed to switch tenant context.');
    }
}
</script>

<template>
    <div
        class="selection:text-indigo-850 flex min-h-screen flex-col bg-slate-100 font-sans text-slate-800 antialiased selection:bg-indigo-100"
    >
        <!-- Top Glassmorphic Header -->
        <header
            class="sticky top-0 z-40 flex h-16 items-center justify-between border-b border-slate-200/80 bg-white/85 px-6 shadow-sm shadow-slate-100/40 backdrop-blur-md"
        >
            <div class="flex items-center space-x-4">
                <!-- Sidebar Toggle Button -->
                <button
                    @click="isSidebarOpen = !isSidebarOpen"
                    class="flex min-h-[44px] min-w-[44px] items-center justify-center rounded-xl p-2 text-slate-500 transition-all hover:bg-slate-100 hover:text-slate-900 focus:outline-none active:scale-95"
                    aria-label="Toggle Sidebar"
                >
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"
                        />
                    </svg>
                </button>
                <div class="flex flex-col" v-if="user.role !== 'superadmin'">
                    <span
                        class="font-mono text-xs font-black uppercase leading-none tracking-widest text-indigo-600"
                    >
                        {{ tenantName }}
                    </span>
                    <span
                        class="text-slate-450 mt-1 hidden font-mono text-[9px] font-bold uppercase tracking-wider sm:inline-block"
                    >
                        Dispatch Operations Platform
                    </span>
                </div>
                <div class="flex items-center" v-else>
                    <select
                        v-model="overrideTenantId"
                        @change="handleTenantSwitch"
                        class="min-h-[44px] min-w-[200px] cursor-pointer rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black uppercase tracking-widest text-indigo-600 shadow-sm focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    >
                        <option value="">All Companies (Global Console)</option>
                        <option
                            v-for="t in superadminTenants"
                            :key="t.id"
                            :value="t.id"
                        >
                            {{ t.name }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Header Actions -->
            <div class="flex items-center space-x-4">
                <!-- User Profile Summary -->
                <div
                    class="flex items-center space-x-3 rounded-xl border border-slate-200/80 bg-slate-100/60 px-3 py-1.5"
                >
                    <div
                        class="to-purple-650 flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 text-[10px] font-bold tracking-wider text-white shadow-sm"
                    >
                        {{ user.name.slice(0, 2).toUpperCase() }}
                    </div>
                    <span
                        class="hidden text-xs font-bold text-slate-700 md:inline-block"
                    >
                        {{ user.name }}
                    </span>
                </div>

                <!-- Sign Out Button -->
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="flex min-h-[40px] min-w-[40px] items-center justify-center rounded-xl border border-slate-200 p-2 text-slate-500 shadow-sm transition-all hover:bg-slate-100 hover:text-slate-900 focus:outline-none active:scale-95"
                    title="Log Out"
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                        />
                    </svg>
                </Link>
            </div>
        </header>

        <div class="relative flex min-h-[calc(100vh-4rem)] flex-1">
            <!-- Left Collapsible Sidebar -->
            <aside
                class="absolute z-30 flex min-h-full w-64 flex-col border-r border-slate-200/80 bg-white shadow-sm shadow-slate-100/50 transition-all duration-300 md:relative"
                :class="
                    isSidebarOpen
                        ? 'translate-x-0'
                        : '-translate-x-full md:w-0 md:overflow-hidden md:border-r-0'
                "
            >
                <!-- Brand logo Header -->
                <div
                    class="flex items-center space-x-3 border-b border-slate-100 bg-slate-50/50 px-6 py-5"
                >
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-600 shadow-lg shadow-indigo-500/10"
                    >
                        <svg
                            class="h-4 w-4 text-white"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                            />
                        </svg>
                    </div>
                    <div class="flex flex-col">
                        <span
                            class="font-mono text-xs font-black leading-none tracking-widest text-slate-900"
                            >WITBO</span
                        >
                        <span
                            class="text-slate-450 mt-0.5 font-mono text-[8px] font-bold uppercase tracking-wider"
                            >Control Panel</span
                        >
                    </div>
                </div>

                <!-- Navigation menu -->
                <nav class="flex-1 space-y-2 overflow-y-auto px-4 py-6">
                    <div
                        class="mb-3 px-3 font-mono text-[9px] font-black uppercase tracking-widest text-slate-400"
                    >
                        Operations Console
                    </div>

                    <!-- Tab 1: Overview Dashboard -->
                    <Link
                        :href="route('dashboard')"
                        class="flex min-h-[44px] items-center space-x-3 rounded-xl px-4 py-3 text-xs font-black uppercase tracking-wider transition-all"
                        :class="
                            route().current('dashboard')
                                ? 'border border-indigo-100 bg-indigo-50/70 font-black text-indigo-600 shadow-sm'
                                : 'border border-transparent text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                        "
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2z"
                            />
                        </svg>
                        <span>📡 Live Dispatch</span>
                    </Link>

                    <!-- Tab 2: Security Guards -->
                    <Link
                        :href="route('admin.guards')"
                        class="flex min-h-[44px] items-center space-x-3 rounded-xl px-4 py-3 text-xs font-black uppercase tracking-wider transition-all"
                        :class="
                            route().current('admin.guards')
                                ? 'border border-indigo-100 bg-indigo-50/70 font-black text-indigo-600 shadow-sm'
                                : 'border border-transparent text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                        "
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
                            />
                        </svg>
                        <span>🛡️ Security Guards</span>
                    </Link>

                    <!-- Tab 3: Checkpoints & Sites -->
                    <Link
                        :href="route('admin.checkpoints')"
                        class="flex min-h-[44px] items-center space-x-3 rounded-xl px-4 py-3 text-xs font-black uppercase tracking-wider transition-all"
                        :class="
                            route().current('admin.checkpoints')
                                ? 'border border-indigo-100 bg-indigo-50/70 font-black text-indigo-600 shadow-sm'
                                : 'border border-transparent text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                        "
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                            />
                        </svg>
                        <span>📍 Checkpoints & Sites</span>
                    </Link>

                    <!-- Tab 4: Patrol Routes -->
                    <Link
                        :href="route('admin.routes')"
                        class="flex min-h-[44px] items-center space-x-3 rounded-xl px-4 py-3 text-xs font-black uppercase tracking-wider transition-all"
                        :class="
                            route().current('admin.routes')
                                ? 'border border-indigo-100 bg-indigo-50/70 font-black text-indigo-600 shadow-sm'
                                : 'border border-transparent text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                        "
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"
                            />
                        </svg>
                        <span>🗺️ Patrol Routes</span>
                    </Link>

                    <!-- Tab 5: Alert Contacts -->
                    <Link
                        :href="route('admin.contacts')"
                        class="flex min-h-[44px] items-center space-x-3 rounded-xl px-4 py-3 text-xs font-black uppercase tracking-wider transition-all"
                        :class="
                            route().current('admin.contacts')
                                ? 'border border-indigo-100 bg-indigo-50/70 font-black text-indigo-600 shadow-sm'
                                : 'border border-transparent text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                        "
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                            />
                        </svg>
                        <span>🔔 Alert Contacts</span>
                    </Link>

                    <!-- Tab 6: History Logs -->
                    <Link
                        :href="route('admin.history')"
                        class="flex min-h-[44px] items-center space-x-3 rounded-xl px-4 py-3 text-xs font-black uppercase tracking-wider transition-all"
                        :class="
                            route().current('admin.history')
                                ? 'border border-indigo-100 bg-indigo-50/70 font-black text-indigo-600 shadow-sm'
                                : 'border border-transparent text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                        "
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
                            />
                        </svg>
                        <span>📜 History Logs</span>
                    </Link>

                    <!-- Tab 7: Subscription Plan -->
                    <Link
                        :href="route('admin.subscription')"
                        class="flex min-h-[44px] items-center space-x-3 rounded-xl px-4 py-3 text-xs font-black uppercase tracking-wider transition-all"
                        :class="
                            route().current('admin.subscription')
                                ? 'border border-indigo-100 bg-indigo-50/70 font-black text-indigo-600 shadow-sm'
                                : 'border border-transparent text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                        "
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                            />
                        </svg>
                        <span>💳 Plan Quota</span>
                    </Link>

                    <!-- Tab 8: Superadmin Console -->
                    <Link
                        v-if="user.role === 'superadmin'"
                        :href="route('admin.superadmin')"
                        class="flex min-h-[44px] items-center space-x-3 rounded-xl px-4 py-3 text-xs font-black uppercase tracking-wider transition-all"
                        :class="
                            route().current('admin.superadmin')
                                ? 'border border-indigo-100 bg-indigo-50/70 font-black text-indigo-600 shadow-sm'
                                : 'border border-transparent text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                        "
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                            />
                        </svg>
                        <span>👑 Superadmin Console</span>
                    </Link>
                </nav>
            </aside>

            <!-- Main Content Area Wrapper -->
            <main class="z-10 flex-1 overflow-y-auto bg-slate-100 p-6 sm:p-8">
                <!-- Content Header (Title + AdminLTE Breadcrumbs) -->
                <div
                    class="mb-6 flex flex-col justify-between gap-3 border-b border-slate-200/80 pb-5 sm:flex-row sm:items-center"
                >
                    <div>
                        <h1
                            class="font-mono text-lg font-black uppercase leading-none tracking-widest text-slate-800"
                        >
                            {{ title }}
                        </h1>
                    </div>

                    <!-- Breadcrumbs -->
                    <ol
                        class="text-slate-450 flex items-center space-x-2 font-mono text-[10px] font-black uppercase tracking-widest"
                    >
                        <li>
                            <Link
                                :href="route('dashboard')"
                                class="transition-colors hover:text-indigo-600"
                                >Home</Link
                            >
                        </li>
                        <li class="flex items-center space-x-2">
                            <span class="text-slate-350">/</span>
                            <span class="font-semibold text-slate-600">{{
                                title
                            }}</span>
                        </li>
                    </ol>
                </div>

                <!-- Page Slot Content -->
                <div class="animate-fade-in">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>

<style>
/* Smooth transitions */
aside {
    transition:
        transform 0.3s cubic-bezier(0.4, 0, 0.2, 1),
        width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.animate-fade-in {
    animation: fadeIn 0.35s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Reusable scrollbar customization */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
::-webkit-scrollbar-track {
    background: transparent;
}
::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.1);
    border-radius: 4px;
}
::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 0, 0, 0.2);
}
</style>
