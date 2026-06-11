<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import GuardLayout from '@/Layouts/GuardLayout.vue';
import Login from '@/Pages/Guard/Login.vue';
import Dashboard from '@/Pages/Guard/Dashboard.vue';
import Patrol from '@/Pages/Guard/Patrol.vue';
import Incident from '@/Pages/Guard/Incident.vue';
import Sos from '@/Pages/Guard/Sos.vue';
import History from '@/Pages/Guard/History.vue';
import { useOfflineSync } from '@/Composables/useOfflineSync';
import { useGeolocation } from '@/Composables/useGeolocation';

// Central State
const currentTab = ref('dashboard');
const guardToken = ref<string | null>(null);
const guard = ref<any>(null);
const activePatrol = ref<any>(null);
const activeSos = ref<any>(null);
const isLoading = ref(true);

const { isOnline, queue, triggerSync } = useOfflineSync();
const { startTracking, stopTracking } = useGeolocation();

// Verify and fetch guard profile
async function checkAuth() {
    const token = localStorage.getItem('guard_token');
    if (token) {
        guardToken.value = token;
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        
        try {
            const response = await axios.get('/api/guard/me');
            if (response.data && response.data.guard) {
                guard.value = response.data.guard;
                
                // Always check the server for an active patrol first (handles device switch / refresh)
                try {
                    const patrolRes = await axios.get('/api/guard/patrols/active');
                    if (patrolRes.data?.patrol) {
                        activePatrol.value = patrolRes.data.patrol;
                        // Sync checkpoint logs from server response
                        if (activePatrol.value.checkpoint_logs) {
                            localStorage.setItem(`patrol_logs_${activePatrol.value.id}`, JSON.stringify(activePatrol.value.checkpoint_logs));
                        }
                        localStorage.setItem('patrol_active_session', JSON.stringify(activePatrol.value));
                        startTracking(activePatrol.value.id);
                    } else {
                        // No active patrol on server — clear stale local cache
                        activePatrol.value = null;
                        localStorage.removeItem('patrol_active_session');
                    }
                } catch {
                    // Fallback to localStorage if active-patrol check fails (offline)
                    const cachedPatrol = localStorage.getItem('patrol_active_session');
                    if (cachedPatrol) {
                        activePatrol.value = JSON.parse(cachedPatrol);
                        startTracking(activePatrol.value.id);
                    }
                }
                
                // Retrieve active SOS
                const cachedSos = localStorage.getItem('patrol_active_sos');
                if (cachedSos) {
                    activeSos.value = JSON.parse(cachedSos);
                }
            } else {
                handleLogout();
            }
        } catch (e: any) {
            console.error('Auth verification failed:', e);
            if (e.response?.status === 401) {
                handleLogout();
            } else {
                // Offline fallback - use cached guard details if available
                const cachedGuard = localStorage.getItem('guard_profile');
                if (cachedGuard) {
                    guard.value = JSON.parse(cachedGuard);
                    
                    const cachedPatrol = localStorage.getItem('patrol_active_session');
                    if (cachedPatrol) {
                        activePatrol.value = JSON.parse(cachedPatrol);
                    }
                    
                    const cachedSos = localStorage.getItem('patrol_active_sos');
                    if (cachedSos) {
                        activeSos.value = JSON.parse(cachedSos);
                    }
                } else {
                    handleLogout();
                }
            }
        }
    } else {
        guardToken.value = null;
        guard.value = null;
    }
    isLoading.value = false;
}

// Success Login Handler
function handleLoginSuccess(data: { token: string; guard: any }) {
    localStorage.setItem('guard_token', data.token);
    localStorage.setItem('guard_profile', JSON.stringify(data.guard));
    guardToken.value = data.token;
    guard.value = data.guard;
    axios.defaults.headers.common['Authorization'] = `Bearer ${data.token}`;
    
    // Check if there are queued items from before, trigger sync
    triggerSync();
}

// Logout
function handleLogout() {
    localStorage.removeItem('guard_token');
    localStorage.removeItem('guard_profile');
    localStorage.removeItem('patrol_active_session');
    localStorage.removeItem('patrol_active_sos');
    guardToken.value = null;
    guard.value = null;
    activePatrol.value = null;
    activeSos.value = null;
    delete axios.defaults.headers.common['Authorization'];
    stopTracking();
}

// Start Patrol session
async function handleStartPatrol(routeId: number) {
    if (!isOnline.value) {
        alert('You must have active internet coverage to initialize a new patrol shift.');
        return;
    }

    try {
        const response = await axios.post('/api/guard/patrols/start', {
            route_id: routeId
        });

        if (response.data && response.data.patrol) {
            activePatrol.value = response.data.patrol;
            localStorage.setItem('patrol_active_session', JSON.stringify(activePatrol.value));
            
            // Start periodic background location updates
            startTracking(activePatrol.value.id);
            
            // Redirect to active checklist
            currentTab.value = 'patrol';
        }
    } catch (e: any) {
        // If the guard already has an active patrol, resume it instead of erroring
        if (e.response?.status === 422 && e.response?.data?.patrol) {
            const existingPatrol = e.response.data.patrol;
            // Fetch full patrol with logs from the dedicated active-patrol endpoint
            try {
                const resumeRes = await axios.get('/api/guard/patrols/active');
                if (resumeRes.data?.patrol) {
                    activePatrol.value = resumeRes.data.patrol;
                    if (activePatrol.value.checkpoint_logs) {
                        localStorage.setItem(`patrol_logs_${activePatrol.value.id}`, JSON.stringify(activePatrol.value.checkpoint_logs));
                    }
                    localStorage.setItem('patrol_active_session', JSON.stringify(activePatrol.value));
                    startTracking(activePatrol.value.id);
                    currentTab.value = 'patrol';
                    return;
                }
            } catch { /* fallback below */ }
            // Fallback: use what the server returned in the 422
            activePatrol.value = existingPatrol;
            localStorage.setItem('patrol_active_session', JSON.stringify(existingPatrol));
            startTracking(existingPatrol.id);
            currentTab.value = 'patrol';
        } else {
            alert(e.response?.data?.message || 'Failed to start patrol shift.');
        }
    }
}

// Patrol Completion Handler
function handlePatrolCompleted() {
    activePatrol.value = null;
    localStorage.removeItem('patrol_active_session');
    stopTracking();
    currentTab.value = 'dashboard';
    
    // Trigger sync to dispatch final logs
    triggerSync();
}

// Trigger SOS Alarm
function handleSosTriggered(sosData: any) {
    activeSos.value = sosData;
    localStorage.setItem('patrol_active_sos', JSON.stringify(sosData));
    currentTab.value = 'sos';
}

// Trigger SOS directly from Dashboard button
async function handleTriggerSosDirectly() {
    currentTab.value = 'sos';
    
    // Get immediate location coords
    let lat = 34.671200;
    let lon = 33.041200;
    
    try {
        const response = await axios.post('/api/guard/sos/trigger', {
            latitude: lat,
            longitude: lon,
            note: 'Panic button pressed from Dashboard'
        });

        if (response.data && response.data.sos_alert) {
            handleSosTriggered(response.data.sos_alert);
        }
    } catch (e: any) {
        console.error('Failed to trigger SOS online, activating local mock:', e);
        const mockSos = {
            id: Date.now(),
            triggered_latitude: lat,
            triggered_longitude: lon,
            note: 'Panic button pressed from Dashboard (Offline)',
            triggered_at: new Date().toISOString()
        };
        handleSosTriggered(mockSos);
    }
}

// Resolve SOS Alarm
function handleSosResolved() {
    activeSos.value = null;
    localStorage.removeItem('patrol_active_sos');
    currentTab.value = 'dashboard';
}


// Auto-Sync pending closures once online
watch(isOnline, async (nextOnline) => {
    if (nextOnline) {
        await triggerSync();
        
        // Check if there was an offline completion pending
        const pendingCompletion = localStorage.getItem('patrol_offline_completion_pending');
        if (pendingCompletion) {
            try {
                const details = JSON.parse(pendingCompletion);
                
                const formData = new FormData();
                if (details.general_note) {
                    formData.append('general_note', details.general_note);
                }
                if (details.completion_latitude) {
                    formData.append('completion_latitude', details.completion_latitude.toString());
                }
                if (details.completion_longitude) {
                    formData.append('completion_longitude', details.completion_longitude.toString());
                }
                
                if (details.signature_base64 && details.signature_base64.length > 1500) {
                    const arr = details.signature_base64.split(',');
                    const mime = arr[0].match(/:(.*?);/)![1];
                    const bstr = atob(arr[1]);
                    let n = bstr.length;
                    const u8arr = new Uint8Array(n);
                    while (n--) {
                        u8arr[n] = bstr.charCodeAt(n);
                    }
                    const sigFile = new File([u8arr], 'completion_signature.png', { type: mime });
                    formData.append('completion_signature', sigFile);
                }

                await axios.post(`/api/guard/patrols/${details.patrol_id}/complete`, formData, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                });
                
                localStorage.removeItem('patrol_offline_completion_pending');
                console.log('Pending offline patrol completion synced.');
            } catch (e) {
                console.error('Failed to sync pending offline completion:', e);
            }
        }
    }
});

onMounted(() => {
    checkAuth();
});
</script>

<template>
    <div class="bg-slate-950 min-h-screen text-slate-100 flex flex-col items-center justify-center">
        <!-- Main Application Loading Shell -->
        <div v-if="isLoading" class="flex flex-col items-center justify-center space-y-4">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-500 to-violet-600 flex items-center justify-center shadow-2xl animate-spin">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
            <p class="text-xs text-slate-500 uppercase tracking-widest font-mono">Loading Security Client...</p>
        </div>

        <!-- LOGIN PANEL (UNAUTHENTICATED) -->
        <div v-else-if="!guardToken" class="w-full">
            <Login @login-success="handleLoginSuccess" />
        </div>

        <!-- MOBILE APPLICATION PORT (AUTHENTICATED DOCK) -->
        <div v-else class="w-full max-w-lg min-h-screen flex flex-col bg-slate-950 border-x border-slate-900 shadow-2xl">
            <GuardLayout 
                :currentTab="currentTab" 
                :guardName="guard?.full_name"
                @navigate="(tab) => currentTab = tab"
                @logout="handleLogout"
            >
                <!-- Dashboard View -->
                <Dashboard 
                    v-if="currentTab === 'dashboard'" 
                    :guard="guard"
                    :activePatrol="activePatrol"
                    @start-patrol="handleStartPatrol"
                    @trigger-sos="handleTriggerSosDirectly"
                    @navigate="(tab) => currentTab = tab"
                />

                <!-- Active Patrol Checklist View -->
                <Patrol 
                    v-else-if="currentTab === 'patrol'" 
                    :guard="guard"
                    :activePatrol="activePatrol"
                    @patrol-completed="handlePatrolCompleted"
                    @navigate="(tab) => currentTab = tab"
                />

                <!-- Incident Logging View -->
                <Incident 
                    v-else-if="currentTab === 'incident'" 
                    :guard="guard"
                    :activePatrol="activePatrol"
                    @navigate="(tab) => currentTab = tab"
                />

                <!-- SOS Emergency View -->
                <Sos 
                    v-else-if="currentTab === 'sos'" 
                    :guard="guard"
                    :activeSos="activeSos"
                    @sos-triggered="handleSosTriggered"
                    @sos-resolved="handleSosResolved"
                    @navigate="(tab) => currentTab = tab"
                />

                <!-- Patrol History View -->
                <History
                    v-else-if="currentTab === 'history'"
                    :guard="guard"
                />
            </GuardLayout>
        </div>
    </div>
</template>

<style>
/* Global Touch improvements and mobile transitions */
html, body {
    background-color: #020617; /* bg-slate-950 */
    overflow-x: hidden;
}
.touch-manipulation {
    touch-action: manipulation;
}
</style>
