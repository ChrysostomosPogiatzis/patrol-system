<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';

defineProps<{
    title: string;
}>();

const isSidebarOpen = ref(true);
const page = usePage();
const user = page.props.auth.user as any;
const tenantName = user.tenant?.name || 'Witbo Control';

const superadminTenants = computed(() => (page.props.auth as any).superadmin_tenants || []);
const overrideTenantId = ref((page.props.auth as any).override_tenant_id || '');

async function handleTenantSwitch() {
    try {
        await axios.post('/admin/api/superadmin/switch-tenant', {
            tenant_id: overrideTenantId.value ? Number(overrideTenantId.value) : null
        });
        router.visit(window.location.href);
    } catch (e) {
        alert('Failed to switch tenant context.');
    }
}
</script>

<template>
    <div class="min-h-screen bg-slate-100 text-slate-800 flex flex-col font-sans antialiased selection:bg-indigo-100 selection:text-indigo-850">
        <!-- Top Glassmorphic Header -->
        <header class="bg-white/85 backdrop-blur-md border-b border-slate-200/80 h-16 flex items-center justify-between px-6 z-40 sticky top-0 shadow-sm shadow-slate-100/40">
            <div class="flex items-center space-x-4">
                <!-- Sidebar Toggle Button -->
                <button 
                    @click="isSidebarOpen = !isSidebarOpen"
                    class="p-2 rounded-xl text-slate-500 hover:text-slate-900 hover:bg-slate-100 active:scale-95 transition-all focus:outline-none min-h-[44px] min-w-[44px] flex items-center justify-center"
                    aria-label="Toggle Sidebar"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="flex flex-col" v-if="user.role !== 'superadmin'">
                    <span class="text-xs font-black tracking-widest text-indigo-600 uppercase font-mono leading-none">
                        {{ tenantName }}
                    </span>
                    <span class="text-[9px] font-bold text-slate-450 tracking-wider uppercase font-mono mt-1 hidden sm:inline-block">
                        Dispatch Operations Platform
                    </span>
                </div>
                <div class="flex items-center" v-else>
                    <select 
                        v-model="overrideTenantId" 
                        @change="handleTenantSwitch"
                        class="bg-white border border-slate-200 rounded-xl text-xs font-black text-indigo-600 uppercase tracking-widest px-3 py-2 focus:outline-none focus:ring-1 focus:ring-indigo-500 cursor-pointer min-h-[44px] min-w-[200px] shadow-sm"
                    >
                        <option value="">All Companies (Global Console)</option>
                        <option v-for="t in superadminTenants" :key="t.id" :value="t.id">
                            {{ t.name }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Header Actions -->
            <div class="flex items-center space-x-4">
                <!-- User Profile Summary -->
                <div class="flex items-center space-x-3 bg-slate-100/60 border border-slate-200/80 px-3 py-1.5 rounded-xl">
                    <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-650 flex items-center justify-center font-bold text-[10px] text-white tracking-wider shadow-sm">
                        {{ user.name.slice(0, 2).toUpperCase() }}
                    </div>
                    <span class="text-xs font-bold text-slate-700 hidden md:inline-block">
                        {{ user.name }}
                    </span>
                </div>

                <!-- Sign Out Button -->
                <Link 
                    :href="route('logout')" 
                    method="post" 
                    as="button"
                    class="p-2 rounded-xl border border-slate-200 text-slate-500 hover:text-slate-900 hover:bg-slate-100 active:scale-95 transition-all focus:outline-none min-h-[40px] min-w-[40px] flex items-center justify-center shadow-sm"
                    title="Log Out"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </Link>
            </div>
        </header>

        <div class="flex flex-1 relative min-h-[calc(100vh-4rem)]">
            <!-- Left Collapsible Sidebar -->
            <aside 
                class="bg-white border-r border-slate-200/80 w-64 flex flex-col z-30 transition-all duration-300 absolute md:relative min-h-full shadow-sm shadow-slate-100/50"
                :class="isSidebarOpen ? 'translate-x-0' : '-translate-x-full md:w-0 md:border-r-0 md:overflow-hidden'"
            >
                <!-- Brand logo Header -->
                <div class="px-6 py-5 border-b border-slate-100 flex items-center space-x-3 bg-slate-50/50">
                    <div class="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center shadow-lg shadow-indigo-500/10">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs font-black tracking-widest text-slate-900 font-mono leading-none">WITBO</span>
                        <span class="text-[8px] font-bold text-slate-450 tracking-wider uppercase font-mono mt-0.5">Control Panel</span>
                    </div>
                </div>

                <!-- Navigation menu -->
                <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
                    <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest px-3 mb-3 font-mono">Operations Console</div>
                    
                    <!-- Tab 1: Overview Dashboard -->
                    <Link 
                        :href="route('dashboard')"
                        class="flex items-center space-x-3 px-4 py-3 rounded-xl text-xs font-black uppercase tracking-wider transition-all min-h-[44px]"
                        :class="route().current('dashboard') ? 'bg-indigo-50/70 border border-indigo-100 text-indigo-600 shadow-sm font-black' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 border border-transparent'"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2z" />
                        </svg>
                        <span>📡 Live Dispatch</span>
                    </Link>

                    <!-- Tab 2: Security Guards -->
                    <Link 
                        :href="route('admin.guards')"
                        class="flex items-center space-x-3 px-4 py-3 rounded-xl text-xs font-black uppercase tracking-wider transition-all min-h-[44px]"
                        :class="route().current('admin.guards') ? 'bg-indigo-50/70 border border-indigo-100 text-indigo-600 shadow-sm font-black' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 border border-transparent'"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <span>🛡️ Security Guards</span>
                    </Link>

                    <!-- Tab 3: Checkpoints & Sites -->
                    <Link 
                        :href="route('admin.checkpoints')"
                        class="flex items-center space-x-3 px-4 py-3 rounded-xl text-xs font-black uppercase tracking-wider transition-all min-h-[44px]"
                        :class="route().current('admin.checkpoints') ? 'bg-indigo-50/70 border border-indigo-100 text-indigo-600 shadow-sm font-black' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 border border-transparent'"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>📍 Checkpoints & Sites</span>
                    </Link>

                    <!-- Tab 4: Patrol Routes -->
                    <Link 
                        :href="route('admin.routes')"
                        class="flex items-center space-x-3 px-4 py-3 rounded-xl text-xs font-black uppercase tracking-wider transition-all min-h-[44px]"
                        :class="route().current('admin.routes') ? 'bg-indigo-50/70 border border-indigo-100 text-indigo-600 shadow-sm font-black' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 border border-transparent'"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                        </svg>
                        <span>🗺️ Patrol Routes</span>
                    </Link>

                    <!-- Tab 5: Alert Contacts -->
                    <Link 
                        :href="route('admin.contacts')"
                        class="flex items-center space-x-3 px-4 py-3 rounded-xl text-xs font-black uppercase tracking-wider transition-all min-h-[44px]"
                        :class="route().current('admin.contacts') ? 'bg-indigo-50/70 border border-indigo-100 text-indigo-600 shadow-sm font-black' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 border border-transparent'"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <span>🔔 Alert Contacts</span>
                    </Link>

                    <!-- Tab 6: History Logs -->
                    <Link 
                        :href="route('admin.history')"
                        class="flex items-center space-x-3 px-4 py-3 rounded-xl text-xs font-black uppercase tracking-wider transition-all min-h-[44px]"
                        :class="route().current('admin.history') ? 'bg-indigo-50/70 border border-indigo-100 text-indigo-600 shadow-sm font-black' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 border border-transparent'"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        <span>📜 History Logs</span>
                    </Link>

                    <!-- Tab 7: Subscription Plan -->
                    <Link 
                        :href="route('admin.subscription')"
                        class="flex items-center space-x-3 px-4 py-3 rounded-xl text-xs font-black uppercase tracking-wider transition-all min-h-[44px]"
                        :class="route().current('admin.subscription') ? 'bg-indigo-50/70 border border-indigo-100 text-indigo-600 shadow-sm font-black' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 border border-transparent'"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                        <span>💳 Plan Quota</span>
                    </Link>

                    <!-- Tab 8: Superadmin Console -->
                    <Link 
                        v-if="user.role === 'superadmin'"
                        :href="route('admin.superadmin')"
                        class="flex items-center space-x-3 px-4 py-3 rounded-xl text-xs font-black uppercase tracking-wider transition-all min-h-[44px]"
                        :class="route().current('admin.superadmin') ? 'bg-indigo-50/70 border border-indigo-100 text-indigo-600 shadow-sm font-black' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 border border-transparent'"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span>👑 Superadmin Console</span>
                    </Link>
                </nav>
            </aside>

            <!-- Main Content Area Wrapper -->
            <main class="flex-1 bg-slate-100 p-6 sm:p-8 overflow-y-auto z-10">
                <!-- Content Header (Title + AdminLTE Breadcrumbs) -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-slate-200/80 mb-6 gap-3">
                    <div>
                        <h1 class="text-lg font-black font-mono text-slate-800 uppercase tracking-widest leading-none">
                            {{ title }}
                        </h1>
                    </div>
                    
                    <!-- Breadcrumbs -->
                    <ol class="flex items-center space-x-2 text-[10px] font-black uppercase tracking-widest text-slate-450 font-mono">
                        <li>
                            <Link :href="route('dashboard')" class="hover:text-indigo-600 transition-colors">Home</Link>
                        </li>
                        <li class="flex items-center space-x-2">
                            <span class="text-slate-350">/</span>
                            <span class="text-slate-600 font-semibold">{{ title }}</span>
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
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.animate-fade-in {
    animation: fadeIn 0.35s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
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
