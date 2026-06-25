<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';

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
    tenant?: {
        id: number;
        name: string;
    };
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
    tenant?: {
        id: number;
        name: string;
    };
}

const locations = ref<Location[]>([]);
const checkpoints = ref<Checkpoint[]>([]);

const showAddLocationModal = ref(false);
const showAddCheckpointModal = ref(false);

const editingLocation = ref<Location | null>(null);
const editingCheckpoint = ref<Checkpoint | null>(null);

const page = usePage();
const isAllCompaniesMode = computed(() => {
    const user = page.props.auth.user as any;
    return (
        user.role === 'superadmin' &&
        !(page.props.auth as any).override_tenant_id
    );
});

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
    if (
        !confirm(
            'Are you sure you want to delete this site location? All associated checkpoints must be deleted first.',
        )
    )
        return;
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
            await axios.put(
                `/admin/api/locations/${editingLocation.value.id}`,
                payload,
            );
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
            await axios.put(
                `/admin/api/checkpoints/${editingCheckpoint.value.id}`,
                payload,
            );
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
        list = list.filter(
            (cp) =>
                cp.name.toLowerCase().includes(q) ||
                (cp.description && cp.description.toLowerCase().includes(q)) ||
                (cp.qr_code && cp.qr_code.toLowerCase().includes(q)) ||
                (cp.nfc_tag_id && cp.nfc_tag_id.toLowerCase().includes(q)),
        );
    }

    if (siteFilter.value) {
        list = list.filter((cp) => cp.location_id === Number(siteFilter.value));
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

const activeQrCode = ref<string | null>(null);
const activeQrName = ref<string | null>(null);

function showQrModal(code: string, name: string) {
    activeQrCode.value = code;
    activeQrName.value = name;
}

function closeQrModal() {
    activeQrCode.value = null;
    activeQrName.value = null;
}

function downloadQrCode() {
    if (!activeQrCode.value) return;
    const url = `https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=${encodeURIComponent(activeQrCode.value)}`;
    window.open(url, '_blank');
}

onMounted(() => {
    fetchData();
});
</script>

<template>
    <Head title="Sites & Checkpoints" />

    <AdminLayout title="Sites & Checkpoints Configuration">
        <div class="space-y-6">
            <!-- Context warning banner -->
            <div
                v-if="isAllCompaniesMode"
                class="border-indigo-150 text-indigo-750 flex items-center gap-3 rounded-2xl border bg-indigo-50 p-4 text-xs font-medium"
            >
                <span class="text-base">ℹ️</span>
                <span
                    >You are viewing sites and checkpoints across
                    <strong>all companies</strong>. To register new sites,
                    configure checkpoints, or edit geographic fences, please
                    select a specific company context from the dropdown at the
                    top.</span
                >
            </div>

            <!-- Action bar -->
            <div class="flex gap-3">
                <button
                    @click="openAddLocation"
                    :disabled="isAllCompaniesMode"
                    class="flex min-h-[48px] items-center space-x-2 rounded-xl px-5 py-3 text-xs font-black uppercase tracking-wider text-white shadow-md transition-all"
                    :class="
                        isAllCompaniesMode
                            ? 'bg-slate-350 cursor-not-allowed opacity-60'
                            : 'bg-indigo-600 hover:bg-indigo-500 active:scale-95'
                    "
                    :title="
                        isAllCompaniesMode
                            ? 'Select a company context to add locations'
                            : ''
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
                            stroke-width="2.2"
                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                        />
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2.2"
                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                        />
                    </svg>
                    <span>Add Location Site</span>
                </button>

                <button
                    @click="openAddCheckpoint"
                    :disabled="isAllCompaniesMode"
                    class="flex min-h-[48px] items-center space-x-2 rounded-xl border border-slate-200 px-5 py-3 text-xs font-black uppercase tracking-wider shadow-sm transition-all"
                    :class="
                        isAllCompaniesMode
                            ? 'cursor-not-allowed bg-slate-50 text-slate-400 opacity-60'
                            : 'bg-white text-slate-700 hover:bg-slate-50 hover:text-slate-900 active:scale-95'
                    "
                    :title="
                        isAllCompaniesMode
                            ? 'Select a company context to add checkpoints'
                            : ''
                    "
                >
                    <svg
                        class="h-4 w-4"
                        :class="
                            isAllCompaniesMode
                                ? 'text-slate-300'
                                : 'text-indigo-600'
                        "
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2.2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                        />
                    </svg>
                    <span>Add Checkpoint</span>
                </button>
            </div>

            <!-- Main Layout Grid -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Locations panel -->
                <div
                    class="space-y-4 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"
                >
                    <h3
                        class="text-slate-450 border-slate-150 border-b pb-2 font-mono text-xs font-black uppercase tracking-widest"
                    >
                        Registered Sites ({{ locations.length }})
                    </h3>
                    <div class="max-h-[600px] space-y-3 overflow-y-auto pr-1">
                        <div
                            v-for="l in locations"
                            :key="l.id"
                            class="border-slate-150 hover:border-slate-250 space-y-3 rounded-xl border bg-slate-50 p-4 transition-colors"
                        >
                            <div class="flex items-start justify-between">
                                <div>
                                    <h4
                                        class="font-mono text-xs font-black leading-tight text-slate-800"
                                    >
                                        {{ l.name }}
                                    </h4>
                                    <div
                                        v-if="isAllCompaniesMode"
                                        class="text-slate-450 mt-0.5 text-[9px] font-bold uppercase"
                                    >
                                        {{ l.tenant?.name || 'System' }}
                                    </div>
                                </div>
                                <span
                                    class="border-indigo-150 rounded border bg-indigo-50 px-1.5 py-0.5 font-mono text-[8px] font-bold text-indigo-600"
                                >
                                    ID: {{ l.id }}
                                </span>
                            </div>
                            <p
                                class="text-[10px] leading-relaxed text-slate-500"
                            >
                                {{ l.address }}, {{ l.city }}
                            </p>
                            <div
                                class="border-slate-150 flex items-center justify-between border-t pt-2 font-mono text-[9px] text-slate-400"
                            >
                                <span>Fence: {{ l.geofence_radius }}m</span>
                                <span class="font-bold text-indigo-600"
                                    >{{ formatCoord(l.latitude, 4) }},
                                    {{ formatCoord(l.longitude, 4) }}</span
                                >
                            </div>
                            <!-- Actions footer for locations -->
                            <div
                                class="border-slate-150/60 flex justify-end space-x-2 border-t pt-2"
                            >
                                <button
                                    @click="openEditLocation(l)"
                                    class="rounded border border-slate-200 bg-white px-2.5 py-1 font-mono text-[9px] font-black uppercase text-indigo-600 hover:bg-slate-50"
                                >
                                    Edit
                                </button>
                                <button
                                    @click="deleteLocation(l.id)"
                                    class="text-red-650 rounded border border-slate-200 bg-white px-2.5 py-1 font-mono text-[9px] font-black uppercase hover:bg-red-50"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                        <div
                            v-if="locations.length === 0"
                            class="text-slate-455 py-12 text-center text-xs"
                        >
                            No sites found.
                        </div>
                    </div>
                </div>

                <!-- Checkpoints list panel -->
                <div
                    class="space-y-4 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:col-span-2"
                >
                    <div
                        class="border-slate-150 flex flex-col items-start justify-between gap-3 border-b pb-2 md:flex-row md:items-center"
                    >
                        <h3
                            class="text-slate-455 font-mono text-xs font-black uppercase tracking-widest"
                        >
                            Checkpoints ({{
                                filteredAndSortedCheckpoints.length
                            }})
                        </h3>
                        <!-- Sort buttons -->
                        <div
                            class="flex flex-wrap gap-2 font-mono text-[9px] uppercase text-slate-500"
                        >
                            <span class="mr-1 self-center">Sort by:</span>
                            <button
                                @click="toggleSort('name')"
                                class="rounded border bg-slate-50 px-2 py-1"
                                :class="
                                    sortKey === 'name'
                                        ? 'border-indigo-200 bg-indigo-50 font-bold text-indigo-600'
                                        : 'border-slate-150'
                                "
                            >
                                Name
                                <span v-if="sortKey === 'name'">{{
                                    sortOrder === 'asc' ? '▲' : '▼'
                                }}</span>
                            </button>
                            <button
                                @click="toggleSort('location')"
                                class="rounded border bg-slate-50 px-2 py-1"
                                :class="
                                    sortKey === 'location'
                                        ? 'border-indigo-200 bg-indigo-50 font-bold text-indigo-600'
                                        : 'border-slate-150'
                                "
                            >
                                Site
                                <span v-if="sortKey === 'location'">{{
                                    sortOrder === 'asc' ? '▲' : '▼'
                                }}</span>
                            </button>
                            <button
                                @click="toggleSort('scan_method')"
                                class="rounded border bg-slate-50 px-2 py-1"
                                :class="
                                    sortKey === 'scan_method'
                                        ? 'border-indigo-200 bg-indigo-50 font-bold text-indigo-600'
                                        : 'border-slate-150'
                                "
                            >
                                Method
                                <span v-if="sortKey === 'scan_method'">{{
                                    sortOrder === 'asc' ? '▲' : '▼'
                                }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Search and filters -->
                    <div
                        class="border-slate-150 flex flex-col gap-3 rounded-xl border bg-slate-50 p-3 md:flex-row"
                    >
                        <div class="relative flex-1">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search checkpoints name, qr, tag..."
                                class="min-h-[36px] w-full rounded-lg border border-slate-200 bg-white py-1.5 pl-8 pr-3 text-xs focus:border-indigo-500 focus:outline-none"
                            />
                            <span
                                class="absolute left-2.5 top-2.5 text-xs text-slate-400"
                                >🔍</span
                            >
                        </div>
                        <div class="w-full md:w-48">
                            <select
                                v-model="siteFilter"
                                class="min-h-[36px] w-full cursor-pointer rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs focus:border-indigo-500 focus:outline-none"
                            >
                                <option value="">All Sites</option>
                                <option
                                    v-for="l in locations"
                                    :key="l.id"
                                    :value="l.id"
                                >
                                    {{ l.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- List body -->
                    <div class="max-h-[500px] space-y-3.5 overflow-y-auto pr-1">
                        <div
                            v-for="cp in filteredAndSortedCheckpoints"
                            :key="cp.id"
                            class="border-slate-150 group flex flex-col justify-between gap-4 rounded-xl border bg-slate-50 p-4 transition-colors hover:border-slate-300 md:flex-row md:items-start"
                        >
                            <div class="flex-1 space-y-1.5">
                                <div
                                    class="flex flex-wrap items-center gap-y-1 space-x-2"
                                >
                                    <h4
                                        class="font-mono text-xs font-black text-slate-800 transition-colors group-hover:text-indigo-600"
                                    >
                                        {{ cp.name }}
                                    </h4>
                                    <span
                                        v-if="isAllCompaniesMode"
                                        class="text-slate-450 rounded border border-slate-200 bg-slate-100 px-1.5 py-0.5 font-mono text-[9px] font-bold uppercase"
                                        >{{ cp.tenant?.name || 'System' }}</span
                                    >
                                    <span
                                        class="border-indigo-150 inline-block rounded-full border bg-indigo-50 px-2 py-0.5 font-mono text-[8px] font-bold uppercase text-indigo-600"
                                    >
                                        {{ cp.scan_method }}
                                    </span>
                                </div>
                                <p
                                    class="text-[10px] leading-relaxed text-slate-500"
                                >
                                    {{
                                        cp.description ||
                                        'No description provided'
                                    }}
                                </p>

                                <div
                                    class="flex flex-wrap gap-1.5 pt-1.5 font-mono text-[8px] font-bold uppercase"
                                >
                                    <span
                                        class="rounded border border-slate-200 bg-white px-2 py-0.5 text-slate-500"
                                    >
                                        Photo: {{ cp.photo_requirement }}
                                    </span>
                                    <span
                                        class="rounded border border-slate-200 bg-white px-2 py-0.5 text-slate-500"
                                    >
                                        Note: {{ cp.note_requirement }}
                                    </span>
                                    <span
                                        class="rounded border border-slate-200 bg-white px-2 py-0.5 text-slate-500"
                                    >
                                        Voice: {{ cp.voice_requirement }}
                                    </span>
                                    <span
                                        v-if="cp.signature_required"
                                        class="text-purple-650 rounded border border-purple-200 bg-purple-50 px-2 py-0.5"
                                    >
                                        Signature Required
                                    </span>
                                    <span
                                        v-if="cp.gps_required"
                                        class="rounded border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-emerald-600"
                                    >
                                        GPS Fence: {{ cp.gps_fence_radius }}m
                                    </span>
                                </div>

                                <div
                                    class="text-slate-455 border-slate-150/40 flex items-center justify-between border-t pt-1.5 text-[9px]"
                                >
                                    <div>
                                        <span>Location Site:</span>
                                        <strong
                                            class="ml-1 font-mono text-indigo-600"
                                            >{{ cp.location?.name }}</strong
                                        >
                                    </div>
                                    <!-- Edit/Delete buttons on individual checkpoint item -->
                                    <div class="flex space-x-2">
                                        <button
                                            @click="openEditCheckpoint(cp)"
                                            class="rounded border border-slate-200 bg-white px-2 py-0.5 font-mono text-[9px] font-black uppercase text-indigo-600 transition-all hover:bg-slate-50 active:scale-95"
                                        >
                                            Edit Checkpoint
                                        </button>
                                        <button
                                            @click="deleteCheckpoint(cp.id)"
                                            class="text-red-650 rounded border border-slate-200 bg-white px-2 py-0.5 font-mono text-[9px] font-black uppercase transition-all hover:bg-red-50 active:scale-95"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Diagnostic codes -->
                            <div
                                class="md:border-slate-150 min-w-[150px] space-y-1.5 text-left font-mono text-[9px] md:border-l md:pl-4 md:text-right"
                            >
                                <div
                                    v-if="cp.qr_code"
                                    class="cursor-pointer text-slate-600"
                                    @click="showQrModal(cp.qr_code, cp.name)"
                                >
                                    <span
                                        class="text-slate-455 block flex items-center gap-1 text-[8px] font-black uppercase transition-colors hover:text-indigo-600 md:justify-end"
                                    >
                                        <span>QR Target Code</span>
                                        <svg
                                            class="h-3.5 w-3.5 text-indigo-500"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2.5"
                                                d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"
                                            />
                                        </svg>
                                    </span>
                                    <span
                                        class="shadow-xs mt-0.5 inline-block rounded border border-indigo-200 bg-indigo-50 px-2 py-0.5 font-bold text-indigo-700 transition-all hover:bg-indigo-100"
                                    >
                                        {{ cp.qr_code }}
                                    </span>
                                </div>
                                <div
                                    v-if="cp.nfc_tag_id"
                                    class="text-slate-600"
                                >
                                    <span
                                        class="text-slate-455 block text-[8px] font-black uppercase"
                                        >NFC Tag ID</span
                                    >
                                    <span
                                        class="mt-0.5 inline-block rounded border border-slate-200 bg-white px-1.5 py-0.5 font-bold text-slate-700"
                                        >{{ cp.nfc_tag_id }}</span
                                    >
                                </div>
                                <div class="text-slate-455">
                                    <span
                                        class="block text-[8px] font-bold uppercase text-slate-500"
                                        >Coordinates</span
                                    >
                                    {{ formatCoord(cp.latitude, 5) }},
                                    {{ formatCoord(cp.longitude, 5) }}
                                </div>
                            </div>
                        </div>
                        <div
                            v-if="filteredAndSortedCheckpoints.length === 0"
                            class="text-slate-450 rounded-xl border border-dashed bg-white py-16 text-center text-xs"
                        >
                            No matching checkpoints found.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FORM MODALS -->
        <LocationModal
            :show="showAddLocationModal"
            :location="editingLocation"
            @close="showAddLocationModal = false"
            @submit="submitAddLocation"
        />
        <CheckpointModal
            :show="showAddCheckpointModal"
            :locations="locations"
            :checkpoint="editingCheckpoint"
            @close="showAddCheckpointModal = false"
            @submit="submitAddCheckpoint"
        />

        <!-- QR CODE PREVIEW MODAL -->
        <div
            v-if="activeQrCode"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-md transition-all duration-300"
        >
            <div
                class="relative w-full max-w-sm space-y-4 rounded-3xl border border-slate-200/80 bg-white p-6 shadow-2xl"
            >
                <button
                    @click="closeQrModal"
                    class="text-slate-455 absolute right-4 top-4 transition-colors hover:text-slate-700"
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
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>

                <div class="space-y-1 text-center">
                    <h3
                        class="font-mono text-xs font-black uppercase tracking-widest text-slate-400"
                    >
                        Checkpoint QR Code
                    </h3>
                    <h4 class="text-sm font-bold text-slate-800">
                        {{ activeQrName }}
                    </h4>
                </div>

                <div
                    class="flex flex-col items-center justify-center rounded-2xl border border-slate-100 bg-slate-50 p-6"
                >
                    <img
                        :src="`https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(activeQrCode)}`"
                        class="shadow-xs h-48 w-48 rounded-lg border border-slate-200 bg-white p-2"
                        alt="QR Code"
                    />
                    <span
                        class="mt-4 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 font-mono text-xs font-bold text-indigo-700"
                    >
                        {{ activeQrCode }}
                    </span>
                </div>

                <div class="flex gap-2">
                    <button
                        @click="downloadQrCode"
                        class="active:scale-98 flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-indigo-600 py-3 text-[11px] font-black uppercase tracking-wider text-white shadow-md transition-all hover:bg-indigo-500"
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
                                stroke-width="2.2"
                                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                            />
                        </svg>
                        <span>Open & Save QR</span>
                    </button>
                    <button
                        @click="closeQrModal"
                        class="active:scale-98 flex-1 rounded-xl bg-slate-100 py-3 text-[11px] font-black uppercase tracking-wider text-slate-700 transition-all hover:bg-slate-200"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
