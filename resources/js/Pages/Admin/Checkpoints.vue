<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

// Component Imports
import CheckpointModal from '@/Components/Admin/CheckpointModal.vue';
import LocationModal from '@/Components/Admin/LocationModal.vue';

interface Location {
    id: number;
    name: string;
    address?: string;
    city?: string;
    country?: string;
    latitude: number;
    longitude: number;
    geofence_radius: number;
}

interface Checkpoint {
    id: number;
    location_id: number;
    name: string;
    description?: string;
    scan_method: 'qr' | 'nfc' | 'both';
    qr_code?: string;
    nfc_tag_id?: string;
    latitude?: number;
    longitude?: number;
    gps_required: boolean;
    gps_fence_radius: number;
    photo_requirement: string;
    note_requirement: string;
    voice_requirement: string;
    signature_required: boolean;
    incident_enabled: boolean;
    location?: Location;
}

const locations = ref<Location[]>([]);
const checkpoints = ref<Checkpoint[]>([]);

const showAddLocationModal = ref(false);
const showAddCheckpointModal = ref(false);

const editingLocation = ref<Location | null>(null);
const editingCheckpoint = ref<Checkpoint | null>(null);

const searchQuery = ref('');
const siteFilter = ref('');
const sortKey = ref('name');
const sortOrder = ref<'asc' | 'desc'>('asc');

function formatCoord(val: any, decimals: number = 5): string {
    if (val === undefined || val === null || val === '') return '';
    const num = Number(val);
    return isNaN(num) ? '' : num.toFixed(decimals);
}

async function fetchData() {
    try {
        const res = await axios.get('/admin/api/locations-data');
        locations.value = res.data.locations;
        checkpoints.value = res.data.checkpoints;
    } catch (e) {
        console.error('Failed to load locations/checkpoints:', e);
    }
}

function openAddLocation() {
    editingLocation.value = null;
    showAddLocationModal.value = true;
}

function openEditLocation(loc: Location) {
    editingLocation.value = loc;
    showAddLocationModal.value = true;
}

async function deleteLocation(id: number) {
    if (!confirm('Are you sure you want to delete this site location? All associated checkpoints must be deleted first.')) return;
    try {
        const res = await axios.delete(`/admin/api/locations/${id}`);
        fetchData();
    } catch (e: any) {
        alert(e.response?.data?.message || 'Failed to delete site location.');
    }
}

function openAddCheckpoint() {
    editingCheckpoint.value = null;
    showAddCheckpointModal.value = true;
}

function openEditCheckpoint(cp: Checkpoint) {
    editingCheckpoint.value = cp;
    showAddCheckpointModal.value = true;
}

async function deleteCheckpoint(id: number) {
    if (!confirm('Are you sure you want to delete this checkpoint?')) return;
    try {
        await axios.delete(`/admin/api/checkpoints/${id}`);
        fetchData();
    } catch (e: any) {
        alert(e.response?.data?.message || 'Failed to delete checkpoint.');
    }
}

async function submitAddLocation(payload: any) {
    try {
        if (editingLocation.value) {
            await axios.put(`/admin/api/locations/${editingLocation.value.id}`, payload);
        } else {
            await axios.post('/admin/api/locations', payload);
        }
        showAddLocationModal.value = false;
        fetchData();
    } catch (e) { 
        alert('Failed to save location.'); 
    }
}

async function submitAddCheckpoint(payload: any) {
    try {
        if (editingCheckpoint.value) {
            await axios.put(`/admin/api/checkpoints/${editingCheckpoint.value.id}`, payload);
        } else {
            await axios.post('/admin/api/checkpoints', payload);
        }
        showAddCheckpointModal.value = false;
        fetchData();
    } catch (e) { 
        alert('Failed to save checkpoint.'); 
    }
}

const filteredAndSortedCheckpoints = computed(() => {
    let list = checkpoints.value;
    
    // Filtering
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(cp => 
            cp.name.toLowerCase().includes(q) || 
            (cp.description && cp.description.toLowerCase().includes(q)) ||
            (cp.qr_code && cp.qr_code.toLowerCase().includes(q)) ||
            (cp.nfc_tag_id && cp.nfc_tag_id.toLowerCase().includes(q))
        );
    }
    
    if (siteFilter.value) {
        list = list.filter(cp => cp.location_id === Number(siteFilter.value));
    }
    
    // Sorting
    list = [...list].sort((a, b) => {
        let valA: any = '';
        let valB: any = '';
        
        if (sortKey.value === 'name') {
            valA = a.name;
            valB = b.name;
        } else if (sortKey.value === 'location') {
            valA = a.location?.name || '';
            valB = b.location?.name || '';
        } else if (sortKey.value === 'scan_method') {
            valA = a.scan_method;
            valB = b.scan_method;
        }
        
        return sortOrder.value === 'asc'
            ? valA.localeCompare(valB)
            : valB.localeCompare(valA);
    });
    
    return list;
});

function toggleSort(key: string) {
    if (sortKey.value === key) {
        sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortOrder.value = 'asc';
    }
}

onMounted(() => {
    fetchData();
});
</script>

<template>
    <Head title="Sites & Checkpoints" />

    <AdminLayout title="Sites & Checkpoints Configuration">
        <div class="space-y-6">
            <!-- Action bar -->
            <div class="flex gap-3">
                <button 
                    @click="openAddLocation" 
                    class="bg-indigo-600 hover:bg-indigo-500 text-white font-black text-xs uppercase tracking-wider px-5 py-3 rounded-xl transition-all shadow-md active:scale-95 flex items-center space-x-2 min-h-[48px]"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>Add Location Site</span>
                </button>
                
                <button 
                    @click="openAddCheckpoint" 
                    class="bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-900 border border-slate-200 font-black text-xs uppercase tracking-wider px-5 py-3 rounded-xl transition-all shadow-sm active:scale-95 flex items-center space-x-2 min-h-[48px]"
                >
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <span>Add Checkpoint</span>
                </button>
            </div>

            <!-- Main Layout Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Locations panel -->
                <div class="bg-white border border-slate-200/80 rounded-2xl p-5 space-y-4 shadow-sm">
                    <h3 class="text-xs font-black tracking-widest text-slate-450 uppercase font-mono pb-2 border-b border-slate-150">
                        Registered Sites ({{ locations.length }})
                    </h3>
                    <div class="space-y-3 max-h-[600px] overflow-y-auto pr-1">
                        <div v-for="l in locations" :key="l.id" class="bg-slate-50 border border-slate-150 rounded-xl p-4 space-y-3 hover:border-slate-250 transition-colors">
                            <div class="flex justify-between items-start">
                                <h4 class="text-xs font-black text-slate-800 font-mono leading-tight">{{ l.name }}</h4>
                                <span class="text-[8px] font-mono font-bold bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded border border-indigo-150">
                                    ID: {{ l.id }}
                                </span>
                            </div>
                            <p class="text-[10px] text-slate-500 leading-relaxed">{{ l.address }}, {{ l.city }}</p>
                            <div class="flex justify-between items-center text-[9px] font-mono text-slate-400 border-t border-slate-150 pt-2">
                                <span>Fence: {{ l.geofence_radius }}m</span>
                                <span class="text-indigo-600 font-bold">{{ formatCoord(l.latitude, 4) }}, {{ formatCoord(l.longitude, 4) }}</span>
                            </div>
                            <!-- Actions footer for locations -->
                            <div class="flex space-x-2 pt-2 border-t border-slate-150/60 justify-end">
                                <button 
                                    @click="openEditLocation(l)"
                                    class="text-[9px] font-black uppercase font-mono bg-white border border-slate-200 hover:bg-slate-50 text-indigo-600 px-2.5 py-1 rounded"
                                >
                                    Edit
                                </button>
                                <button 
                                    @click="deleteLocation(l.id)"
                                    class="text-[9px] font-black uppercase font-mono bg-white border border-slate-200 hover:bg-red-50 text-red-650 px-2.5 py-1 rounded"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                        <div v-if="locations.length === 0" class="text-center text-slate-455 py-12 text-xs">No sites found.</div>
                    </div>
                </div>

                <!-- Checkpoints list panel -->
                <div class="lg:col-span-2 bg-white border border-slate-200/80 rounded-2xl p-5 space-y-4 shadow-sm">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 pb-2 border-b border-slate-150">
                        <h3 class="text-xs font-black tracking-widest text-slate-450 uppercase font-mono">
                            Checkpoints ({{ filteredAndSortedCheckpoints.length }})
                        </h3>
                        <!-- Sort buttons -->
                        <div class="flex flex-wrap gap-2 text-[9px] font-mono uppercase text-slate-500">
                            <span class="mr-1 self-center">Sort by:</span>
                            <button 
                                @click="toggleSort('name')"
                                class="px-2 py-1 bg-slate-50 rounded border"
                                :class="sortKey === 'name' ? 'bg-indigo-50 text-indigo-600 font-bold border-indigo-200' : 'border-slate-150'"
                            >
                                Name <span v-if="sortKey === 'name'">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
                            </button>
                            <button 
                                @click="toggleSort('location')"
                                class="px-2 py-1 bg-slate-50 rounded border"
                                :class="sortKey === 'location' ? 'bg-indigo-50 text-indigo-600 font-bold border-indigo-200' : 'border-slate-150'"
                            >
                                Site <span v-if="sortKey === 'location'">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
                            </button>
                            <button 
                                @click="toggleSort('scan_method')"
                                class="px-2 py-1 bg-slate-50 rounded border"
                                :class="sortKey === 'scan_method' ? 'bg-indigo-50 text-indigo-600 font-bold border-indigo-200' : 'border-slate-150'"
                            >
                                Method <span v-if="sortKey === 'scan_method'">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Search and filters -->
                    <div class="flex flex-col md:flex-row gap-3 bg-slate-50 p-3 rounded-xl border border-slate-150">
                        <div class="flex-1 relative">
                            <input 
                                v-model="searchQuery" 
                                type="text" 
                                placeholder="Search checkpoints name, qr, tag..." 
                                class="w-full pl-8 pr-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-indigo-500 min-h-[36px]"
                            />
                            <span class="absolute left-2.5 top-2.5 text-slate-400 text-xs">🔍</span>
                        </div>
                        <div class="w-full md:w-48">
                            <select 
                                v-model="siteFilter" 
                                class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-indigo-500 min-h-[36px] cursor-pointer"
                            >
                                <option value="">All Sites</option>
                                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- List body -->
                    <div class="space-y-3.5 max-h-[500px] overflow-y-auto pr-1">
                        <div 
                            v-for="cp in filteredAndSortedCheckpoints" 
                            :key="cp.id"
                            class="bg-slate-50 border border-slate-150 rounded-xl p-4 flex flex-col md:flex-row md:items-start justify-between gap-4 group hover:border-slate-300 transition-colors"
                        >
                            <div class="space-y-1.5 flex-1">
                                <div class="flex items-center space-x-2">
                                    <h4 class="text-xs font-black text-slate-800 font-mono group-hover:text-indigo-600 transition-colors">{{ cp.name }}</h4>
                                    <span class="inline-block text-[8px] font-mono bg-indigo-50 border border-indigo-150 text-indigo-600 px-2 py-0.5 rounded-full font-bold uppercase">
                                        {{ cp.scan_method }}
                                    </span>
                                </div>
                                <p class="text-[10px] text-slate-500 leading-relaxed">{{ cp.description || 'No description provided' }}</p>
                                
                                <div class="flex flex-wrap gap-1.5 pt-1.5 text-[8px] font-mono font-bold uppercase">
                                    <span class="px-2 py-0.5 rounded bg-white text-slate-500 border border-slate-200">
                                        Photo: {{ cp.photo_requirement }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded bg-white text-slate-500 border border-slate-200">
                                        Note: {{ cp.note_requirement }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded bg-white text-slate-500 border border-slate-200">
                                        Voice: {{ cp.voice_requirement }}
                                    </span>
                                    <span v-if="cp.signature_required" class="px-2 py-0.5 rounded bg-purple-50 text-purple-650 border border-purple-200">
                                        Signature Required
                                    </span>
                                    <span v-if="cp.gps_required" class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-600 border border-emerald-200">
                                        GPS Fence: {{ cp.gps_fence_radius }}m
                                    </span>
                                </div>

                                <div class="text-[9px] text-slate-455 pt-1.5 flex items-center justify-between border-t border-slate-150/40">
                                    <div>
                                        <span>Location Site:</span>
                                        <strong class="text-indigo-600 font-mono ml-1">{{ cp.location?.name }}</strong>
                                    </div>
                                    <!-- Edit/Delete buttons on individual checkpoint item -->
                                    <div class="flex space-x-2">
                                        <button 
                                            @click="openEditCheckpoint(cp)"
                                            class="text-[9px] font-black uppercase font-mono bg-white border border-slate-200 hover:bg-slate-50 text-indigo-600 px-2 py-0.5 rounded active:scale-95 transition-all"
                                        >
                                            Edit Checkpoint
                                        </button>
                                        <button 
                                            @click="deleteCheckpoint(cp.id)"
                                            class="text-[9px] font-black uppercase font-mono bg-white border border-slate-200 hover:bg-red-50 text-red-650 px-2 py-0.5 rounded active:scale-95 transition-all"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Diagnostic codes -->
                            <div class="text-left md:text-right space-y-1.5 font-mono text-[9px] md:border-l md:border-slate-150 md:pl-4 min-w-[150px]">
                                <div v-if="cp.qr_code" class="text-slate-600">
                                    <span class="text-slate-450 uppercase font-black block text-[8px]">QR Target Code</span>
                                    <span class="bg-white px-1.5 py-0.5 rounded border border-slate-200 inline-block mt-0.5 font-bold text-slate-700">{{ cp.qr_code }}</span>
                                </div>
                                <div v-if="cp.nfc_tag_id" class="text-slate-600">
                                    <span class="text-slate-450 uppercase font-black block text-[8px]">NFC Tag ID</span>
                                    <span class="bg-white px-1.5 py-0.5 rounded border border-slate-200 inline-block mt-0.5 font-bold text-slate-700">{{ cp.nfc_tag_id }}</span>
                                </div>
                                <div class="text-slate-450">
                                    <span class="text-slate-500 font-bold block text-[8px] uppercase">Coordinates</span>
                                    {{ formatCoord(cp.latitude, 5) }}, {{ formatCoord(cp.longitude, 5) }}
                                </div>
                            </div>
                        </div>
                        <div v-if="filteredAndSortedCheckpoints.length === 0" class="text-center text-slate-450 py-16 border border-dashed rounded-xl bg-white text-xs">No matching checkpoints found.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FORM MODALS -->
        <LocationModal :show="showAddLocationModal" :location="editingLocation" @close="showAddLocationModal = false" @submit="submitAddLocation" />
        <CheckpointModal :show="showAddCheckpointModal" :locations="locations" :checkpoint="editingCheckpoint" @close="showAddCheckpointModal = false" @submit="submitAddCheckpoint" />
    </AdminLayout>
</template>
