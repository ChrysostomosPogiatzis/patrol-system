<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { useOfflineSync } from '@/Composables/useOfflineSync';
import { useGeolocation } from '@/Composables/useGeolocation';

interface Props {
    currentTab: string;
    guardName?: string;
    avatarUrl?: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'navigate', tab: string): void;
    (e: 'logout'): void;
}>();

const { isOnline, queue, isSyncing, triggerSync, toggleOnline } = useOfflineSync();
const { batteryPct, updateLocation } = useGeolocation();

// Periodic battery update
let batteryInterval: any = null;

onMounted(async () => {
    await updateLocation();
    batteryInterval = setInterval(async () => {
        await updateLocation();
    }, 30000);
});

onUnmounted(() => {
    if (batteryInterval) clearInterval(batteryInterval);
});
</script>

<template>
    <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col font-sans select-none antialiased">
        <!-- Status & Connectivity Banner -->
        <div 
            v-if="!isOnline" 
            class="bg-amber-600/90 backdrop-blur-md text-amber-50 text-xs text-center py-1.5 px-4 font-semibold flex items-center justify-center space-x-2 transition-all duration-300 shadow-md border-b border-amber-500/30"
        >
            <span class="w-2 h-2 rounded-full bg-amber-200 animate-pulse"></span>
            <span>Offline Mode Active • {{ queue.length }} actions queued</span>
        </div>

        <div 
            v-else-if="isSyncing" 
            class="bg-indigo-600/90 backdrop-blur-md text-indigo-50 text-xs text-center py-1.5 px-4 font-semibold flex items-center justify-center space-x-2 transition-all duration-300 shadow-md border-b border-indigo-500/30"
        >
            <span class="w-2 h-2 rounded-full bg-indigo-200 animate-ping"></span>
            <span>Synchronizing offline logs...</span>
        </div>

        <!-- Top Header Navigation -->
        <header class="bg-slate-900/80 backdrop-blur-md border-b border-slate-800/80 sticky top-0 z-30 px-4 py-3 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-indigo-500 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-sm font-black tracking-wider text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-violet-400">
                         SENTINEL
                    </h1>
                    <p class="text-[10px] text-slate-400 font-mono tracking-widest leading-none">PATROL SYSTEM</p>
                </div>
            </div>

            <!-- Device Stats & Profile -->
            <div class="flex items-center space-x-4">
                <!-- Battery Info -->
                <div class="flex items-center space-x-1 text-slate-400" title="Device Battery Level">
                    <svg class="w-4 h-4" :class="{'text-emerald-400': (batteryPct ?? 0) > 50, 'text-amber-400': (batteryPct ?? 0) <= 50 && (batteryPct ?? 0) > 20, 'text-rose-500 animate-pulse': (batteryPct ?? 0) <= 20}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span class="text-xs font-mono">{{ batteryPct !== null ? `${batteryPct}%` : '---' }}</span>
                </div>

                <!-- Online Status Tag (Togglable) -->
                <div 
                    @click="toggleOnline"
                    class="px-2 py-0.5 rounded-full flex items-center space-x-1.5 bg-slate-950/80 border text-[10px] font-semibold tracking-wide cursor-pointer active:scale-95 transition-all select-none"
                    :class="isOnline ? 'border-emerald-500/20 text-emerald-400' : 'border-amber-500/20 text-amber-400'"
                >
                    <span class="w-1.5 h-1.5 rounded-full" :class="isOnline ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'"></span>
                    <span>{{ isOnline ? 'ONLINE' : 'OFFLINE' }}</span>
                </div>

                <!-- User Profile & Sign Out -->
                <button 
                    @click="emit('logout')" 
                    class="p-1.5 rounded-lg border border-slate-800 text-slate-400 hover:text-slate-200 hover:bg-slate-800/50 transition-all duration-200"
                    title="Log Out"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </button>
            </div>
        </header>

        <!-- Main View Content Area -->
        <main class="flex-1 overflow-y-auto px-4 py-4 pb-28">
            <slot />
        </main>

        <!-- Bottom Navigation Dock -->
        <nav class="fixed bottom-0 left-0 right-0 z-40 bg-slate-950/90 backdrop-blur-lg border-t border-slate-900 px-3 py-2 flex justify-around items-center max-w-lg mx-auto rounded-t-2xl shadow-2xl">
            <!-- Dashboard Tab -->
            <button 
                @click="emit('navigate', 'dashboard')" 
                class="flex flex-col items-center justify-center w-16 h-12 rounded-xl transition-all duration-200 focus:outline-none"
                :class="currentTab === 'dashboard' ? 'text-indigo-400 bg-indigo-500/10' : 'text-slate-500 hover:text-slate-300'"
            >
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
                </svg>
                <span class="text-[9px] font-bold tracking-wider uppercase">Home</span>
            </button>

            <!-- Patrol/Routes Tab -->
            <button 
                @click="emit('navigate', 'patrol')" 
                class="flex flex-col items-center justify-center w-16 h-12 rounded-xl transition-all duration-200 focus:outline-none"
                :class="currentTab === 'patrol' ? 'text-indigo-400 bg-indigo-500/10' : 'text-slate-500 hover:text-slate-300'"
            >
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                </svg>
                <span class="text-[9px] font-bold tracking-wider uppercase">Patrol</span>
            </button>

            <!-- Quick Incident Tab -->
            <button 
                @click="emit('navigate', 'incident')" 
                class="flex flex-col items-center justify-center w-16 h-12 rounded-xl transition-all duration-200 focus:outline-none"
                :class="currentTab === 'incident' ? 'text-indigo-400 bg-indigo-500/10' : 'text-slate-500 hover:text-slate-300'"
            >
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span class="text-[9px] font-bold tracking-wider uppercase">Incident</span>
            </button>

            <!-- History Tab -->
            <button 
                @click="emit('navigate', 'history')" 
                class="flex flex-col items-center justify-center w-16 h-12 rounded-xl transition-all duration-200 focus:outline-none"
                :class="currentTab === 'history' ? 'text-indigo-400 bg-indigo-500/10' : 'text-slate-500 hover:text-slate-300'"
            >
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-[9px] font-bold tracking-wider uppercase">History</span>
            </button>

            <!-- Prominent Pulse SOS Button -->
            <button 
                @click="emit('navigate', 'sos')" 
                class="flex flex-col items-center justify-center w-16 h-12 rounded-xl transition-all duration-200 focus:outline-none group"
                :class="currentTab === 'sos' ? 'text-rose-400 bg-rose-500/10' : 'text-slate-500 hover:text-rose-400'"
            >
                <div class="relative flex items-center justify-center w-6 h-6 mb-0.5">
                    <span 
                        class="absolute inline-flex h-full w-full rounded-full bg-rose-500 opacity-20 group-hover:animate-ping"
                        :class="currentTab === 'sos' ? 'animate-ping' : ''"
                    ></span>
                    <svg class="w-5 h-5 relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                </div>
                <span class="text-[9px] font-bold tracking-wider uppercase text-rose-500">Panic SOS</span>
            </button>
        </nav>
    </div>
</template>

<style scoped>
/* Glassmorphism custom enhancements */
nav {
    box-shadow: 0 -10px 25px -5px rgba(0, 0, 0, 0.5);
}
</style>
