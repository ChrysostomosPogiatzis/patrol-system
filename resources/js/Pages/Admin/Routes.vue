<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

// Component Import
import RouteModal from '@/Components/Admin/RouteModal.vue';

interface Location {
    id: number;
    name: string;
}

interface Checkpoint {
    id: number;
    name: string;
    location?: Location;
}

interface RouteCheckpoint {
    id: number;
    position: number;
    checkpoint: Checkpoint;
}

interface Route {
    id: number;
    name: string;
    description?: string;
    enforce_order: boolean;
    allow_skip: boolean;
    expected_duration_mins?: number;
    route_checkpoints?: RouteCheckpoint[];
    tenant?: {
        id: number;
        name: string;
    };
}

const routes = ref<Route[]>([]);
const checkpoints = ref<Checkpoint[]>([]);
const showAddRouteModal = ref(false);
const editingRoute = ref<Route | null>(null);

const page = usePage();
const isAllCompaniesMode = computed(() => {
    const user = page.props.auth.user as any;
    return user.role === 'superadmin' && !(page.props.auth as any).override_tenant_id;
});

const searchQuery = ref('');
const sortBy = ref('name'); // name, duration
const sortOrder = ref<'asc' | 'desc'>('asc');

async function fetchData() {
    try {
        const res = await axios.get('/admin/api/locations-data');
        routes.value = res.data.routes;
        checkpoints.value = res.data.checkpoints;
    } catch (e) {
        console.error('Failed to load routes/checkpoints data:', e);
    }
}

function openAddRoute() {
    editingRoute.value = null;
    showAddRouteModal.value = true;
}

function openEditRoute(route: Route) {
    editingRoute.value = route;
    showAddRouteModal.value = true;
}

async function deleteRoute(id: number) {
    if (!confirm('Are you sure you want to delete this patrol route?')) return;
    try {
        await axios.delete(`/admin/api/routes/${id}`);
        fetchData();
    } catch (e: any) {
        alert(e.response?.data?.message || 'Failed to delete route.');
    }
}

async function submitAddRoute(payload: any) {
    try {
        if (editingRoute.value) {
            await axios.put(`/admin/api/routes/${editingRoute.value.id}`, payload);
        } else {
            await axios.post('/admin/api/routes', payload);
        }
        showAddRouteModal.value = false;
        fetchData();
    } catch (e) { 
        alert('Failed to save route.'); 
    }
}

const filteredAndSortedRoutes = computed(() => {
    let list = routes.value;
    
    // Filtering
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(r => 
            r.name.toLowerCase().includes(q) || 
            (r.description && r.description.toLowerCase().includes(q))
        );
    }
    
    // Sorting
    list = [...list].sort((a, b) => {
        let valA: any = '';
        let valB: any = '';
        
        if (sortBy.value === 'name') {
            valA = a.name;
            valB = b.name;
            return sortOrder.value === 'asc' 
                ? valA.localeCompare(valB) 
                : valB.localeCompare(valA);
        } else if (sortBy.value === 'duration') {
            valA = a.expected_duration_mins || 0;
            valB = b.expected_duration_mins || 0;
            return sortOrder.value === 'asc' 
                ? valA - valB 
                : valB - valA;
        }
        
        return 0;
    });
    
    return list;
});

function toggleSort(key: string) {
    if (sortBy.value === key) {
        sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = key;
        sortOrder.value = 'asc';
    }
}

onMounted(() => {
    fetchData();
});
</script>

<template>
    <Head title="Patrol Routes" />

    <AdminLayout title="Patrol Routes Sequence">
        <div class="space-y-4">
            <!-- Context warning banner -->
            <div v-if="isAllCompaniesMode" class="bg-indigo-50 border border-indigo-150 p-4 rounded-2xl flex items-center gap-3 text-xs text-indigo-750 font-medium">
                <span class="text-base">ℹ️</span>
                <span>You are viewing routes across <strong>all companies</strong>. To create new patrol routes or customize sequential checkpoints validation, please select a specific company context from the dropdown at the top.</span>
            </div>

            <!-- Action bar -->
            <div class="flex justify-between items-center">
                <span class="text-xs text-slate-500 font-black uppercase tracking-widest font-mono">Routing Configurations ({{ filteredAndSortedRoutes.length }})</span>
                <button 
                    @click="openAddRoute"
                    :disabled="isAllCompaniesMode"
                    class="text-white font-black text-xs uppercase tracking-wider px-5 py-3 rounded-xl transition-all shadow-md flex items-center space-x-2 min-h-[48px]"
                    :class="isAllCompaniesMode ? 'bg-slate-350 cursor-not-allowed opacity-60' : 'bg-indigo-600 hover:bg-indigo-500 active:scale-95'"
                    :title="isAllCompaniesMode ? 'Select a company context to create routes' : ''"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                    </svg>
                    <span>Create Patrol Route</span>
                </button>
            </div>

            <!-- Search and filters -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 flex flex-col md:flex-row gap-4 shadow-sm">
                <div class="flex-1 relative">
                    <input 
                        v-model="searchQuery" 
                        type="text" 
                        placeholder="Search routes by name or description..." 
                        class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 min-h-[42px]"
                    />
                    <span class="absolute left-3.5 top-3 text-slate-400 text-xs">🔍</span>
                </div>
                <div class="flex items-center space-x-2 text-xs font-mono uppercase text-slate-500">
                    <span class="mr-1">Sort by:</span>
                    <button 
                        @click="toggleSort('name')"
                        class="px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 cursor-pointer min-h-[42px]"
                        :class="sortBy === 'name' ? 'bg-indigo-50 text-indigo-600 font-bold border-indigo-250' : ''"
                    >
                        Name <span v-if="sortBy === 'name'">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
                    </button>
                    <button 
                        @click="toggleSort('duration')"
                        class="px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 cursor-pointer min-h-[42px]"
                        :class="sortBy === 'duration' ? 'bg-indigo-50 text-indigo-600 font-bold border-indigo-250' : ''"
                    >
                        Duration <span v-if="sortBy === 'duration'">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
                    </button>
                </div>
            </div>

            <!-- Routes Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div 
                    v-for="route in filteredAndSortedRoutes" 
                    :key="route.id"
                    class="bg-white border border-slate-200/80 rounded-2xl p-5 space-y-4 shadow-sm hover:border-slate-350 transition-all duration-200 group"
                >
                    <div class="flex justify-between items-start">
                        <div>
                            <h4 class="text-sm font-black text-slate-800 font-mono group-hover:text-indigo-600 transition-colors flex items-center gap-2 flex-wrap">
                                <span>{{ route.name }}</span>
                                <span v-if="isAllCompaniesMode" class="text-[9px] font-bold text-slate-450 uppercase bg-slate-105 border border-slate-200 px-1.5 py-0.5 rounded font-sans normal-case">{{ route.tenant?.name || 'System' }}</span>
                            </h4>
                            <p class="text-[11px] text-slate-505 mt-1.5 leading-relaxed">{{ route.description || 'No description provided' }}</p>
                        </div>
                        <div class="text-right text-[9px] font-mono space-y-1.5 min-w-[90px]">
                            <span class="block bg-slate-50 text-slate-600 px-2.5 py-1 rounded-lg border border-slate-150 font-bold uppercase">
                                {{ route.expected_duration_mins || 30 }} mins
                            </span>
                            <span class="block font-black uppercase text-indigo-600">
                                {{ route.enforce_order ? 'Strict Seq' : 'Flexible' }}
                            </span>
                        </div>
                    </div>

                    <div class="space-y-3 pt-3 border-t border-slate-150">
                        <h5 class="text-[9px] font-black text-slate-400 uppercase tracking-widest font-mono">Ordered Checkpoints Sequence</h5>
                        <div class="space-y-2 pl-2 border-l-2 border-indigo-500/25">
                            <div 
                                v-for="rc in route.route_checkpoints" 
                                :key="rc.id"
                                class="text-xs text-slate-600 flex items-center space-x-2.5"
                            >
                                <span class="w-5 h-5 rounded-lg bg-slate-50 text-[9px] font-mono text-indigo-600 flex items-center justify-center border border-slate-150 font-black">
                                    {{ rc.position }}
                                </span>
                                <span class="font-medium text-slate-700">
                                    {{ rc.checkpoint?.name }} 
                                    <small class="text-slate-455 font-mono">({{ rc.checkpoint?.location?.name }})</small>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions footer for routes -->
                    <div class="flex justify-end space-x-2.5 pt-3 border-t border-slate-150/60">
                        <button 
                            @click="openEditRoute(route)"
                            class="bg-indigo-50 hover:bg-indigo-100 text-indigo-650 px-3.5 py-1.5 rounded-lg text-[10px] font-black uppercase font-mono tracking-wider border border-indigo-100/60 active:scale-95 transition-all"
                        >
                            Edit Route
                        </button>
                        <button 
                            @click="deleteRoute(route.id)"
                            class="bg-red-50 hover:bg-red-100 text-red-650 px-3.5 py-1.5 rounded-lg text-[10px] font-black uppercase font-mono tracking-wider border border-red-100/60 active:scale-95 transition-all"
                        >
                            Delete
                        </button>
                    </div>
                </div>

                <div v-if="filteredAndSortedRoutes.length === 0" class="col-span-2 p-16 text-center text-slate-400 bg-white border border-dashed border-slate-200 rounded-2xl text-xs font-medium">
                    No patrol routes match the query.
                </div>
            </div>
        </div>

        <!-- FORM MODAL -->
        <RouteModal :show="showAddRouteModal" :checkpoints="checkpoints" :editRoute="editingRoute" @close="showAddRouteModal = false" @submit="submitAddRoute" />
    </AdminLayout>
</template>
