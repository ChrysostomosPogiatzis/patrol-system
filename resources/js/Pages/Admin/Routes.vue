<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';

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
    return (
        user.role === 'superadmin' &&
        !(page.props.auth as any).override_tenant_id
    );
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
            await axios.put(
                `/admin/api/routes/${editingRoute.value.id}`,
                payload,
            );
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
        list = list.filter(
            (r) =>
                r.name.toLowerCase().includes(q) ||
                (r.description && r.description.toLowerCase().includes(q)),
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
            return sortOrder.value === 'asc' ? valA - valB : valB - valA;
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
            <div
                v-if="isAllCompaniesMode"
                class="border-indigo-150 text-indigo-750 flex items-center gap-3 rounded-2xl border bg-indigo-50 p-4 text-xs font-medium"
            >
                <span class="text-base">ℹ️</span>
                <span
                    >You are viewing routes across
                    <strong>all companies</strong>. To create new patrol routes
                    or customize sequential checkpoints validation, please
                    select a specific company context from the dropdown at the
                    top.</span
                >
            </div>

            <!-- Action bar -->
            <div class="flex items-center justify-between">
                <span
                    class="font-mono text-xs font-black uppercase tracking-widest text-slate-500"
                    >Routing Configurations ({{
                        filteredAndSortedRoutes.length
                    }})</span
                >
                <button
                    @click="openAddRoute"
                    :disabled="isAllCompaniesMode"
                    class="flex min-h-[48px] items-center space-x-2 rounded-xl px-5 py-3 text-xs font-black uppercase tracking-wider text-white shadow-md transition-all"
                    :class="
                        isAllCompaniesMode
                            ? 'bg-slate-350 cursor-not-allowed opacity-60'
                            : 'bg-indigo-600 hover:bg-indigo-500 active:scale-95'
                    "
                    :title="
                        isAllCompaniesMode
                            ? 'Select a company context to create routes'
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
                            d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"
                        />
                    </svg>
                    <span>Create Patrol Route</span>
                </button>
            </div>

            <!-- Search and filters -->
            <div
                class="flex flex-col gap-4 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm md:flex-row"
            >
                <div class="relative flex-1">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search routes by name or description..."
                        class="min-h-[42px] w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs focus:border-indigo-500 focus:outline-none"
                    />
                    <span class="absolute left-3.5 top-3 text-xs text-slate-400"
                        >🔍</span
                    >
                </div>
                <div
                    class="flex items-center space-x-2 font-mono text-xs uppercase text-slate-500"
                >
                    <span class="mr-1">Sort by:</span>
                    <button
                        @click="toggleSort('name')"
                        class="min-h-[42px] cursor-pointer rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs focus:border-indigo-500 focus:outline-none"
                        :class="
                            sortBy === 'name'
                                ? 'border-indigo-250 bg-indigo-50 font-bold text-indigo-600'
                                : ''
                        "
                    >
                        Name
                        <span v-if="sortBy === 'name'">{{
                            sortOrder === 'asc' ? '▲' : '▼'
                        }}</span>
                    </button>
                    <button
                        @click="toggleSort('duration')"
                        class="min-h-[42px] cursor-pointer rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs focus:border-indigo-500 focus:outline-none"
                        :class="
                            sortBy === 'duration'
                                ? 'border-indigo-250 bg-indigo-50 font-bold text-indigo-600'
                                : ''
                        "
                    >
                        Duration
                        <span v-if="sortBy === 'duration'">{{
                            sortOrder === 'asc' ? '▲' : '▼'
                        }}</span>
                    </button>
                </div>
            </div>

            <!-- Routes Cards Grid -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div
                    v-for="route in filteredAndSortedRoutes"
                    :key="route.id"
                    class="hover:border-slate-350 group space-y-4 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition-all duration-200"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <h4
                                class="flex flex-wrap items-center gap-2 font-mono text-sm font-black text-slate-800 transition-colors group-hover:text-indigo-600"
                            >
                                <span>{{ route.name }}</span>
                                <span
                                    v-if="isAllCompaniesMode"
                                    class="text-slate-450 bg-slate-105 rounded border border-slate-200 px-1.5 py-0.5 font-sans text-[9px] font-bold uppercase normal-case"
                                    >{{ route.tenant?.name || 'System' }}</span
                                >
                            </h4>
                            <p
                                class="text-slate-505 mt-1.5 text-[11px] leading-relaxed"
                            >
                                {{
                                    route.description ||
                                    'No description provided'
                                }}
                            </p>
                        </div>
                        <div
                            class="min-w-[90px] space-y-1.5 text-right font-mono text-[9px]"
                        >
                            <span
                                class="border-slate-150 block rounded-lg border bg-slate-50 px-2.5 py-1 font-bold uppercase text-slate-600"
                            >
                                {{ route.expected_duration_mins || 30 }} mins
                            </span>
                            <span
                                class="block font-black uppercase text-indigo-600"
                            >
                                {{
                                    route.enforce_order
                                        ? 'Strict Seq'
                                        : 'Flexible'
                                }}
                            </span>
                        </div>
                    </div>

                    <div class="border-slate-150 space-y-3 border-t pt-3">
                        <h5
                            class="font-mono text-[9px] font-black uppercase tracking-widest text-slate-400"
                        >
                            Ordered Checkpoints Sequence
                        </h5>
                        <div
                            class="space-y-2 border-l-2 border-indigo-500/25 pl-2"
                        >
                            <div
                                v-for="rc in route.route_checkpoints"
                                :key="rc.id"
                                class="flex items-center space-x-2.5 text-xs text-slate-600"
                            >
                                <span
                                    class="border-slate-150 flex h-5 w-5 items-center justify-center rounded-lg border bg-slate-50 font-mono text-[9px] font-black text-indigo-600"
                                >
                                    {{ rc.position }}
                                </span>
                                <span class="font-medium text-slate-700">
                                    {{ rc.checkpoint?.name }}
                                    <small class="text-slate-455 font-mono"
                                        >({{
                                            rc.checkpoint?.location?.name
                                        }})</small
                                    >
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions footer for routes -->
                    <div
                        class="border-slate-150/60 flex justify-end space-x-2.5 border-t pt-3"
                    >
                        <button
                            @click="openEditRoute(route)"
                            class="text-indigo-650 rounded-lg border border-indigo-100/60 bg-indigo-50 px-3.5 py-1.5 font-mono text-[10px] font-black uppercase tracking-wider transition-all hover:bg-indigo-100 active:scale-95"
                        >
                            Edit Route
                        </button>
                        <button
                            @click="deleteRoute(route.id)"
                            class="text-red-650 rounded-lg border border-red-100/60 bg-red-50 px-3.5 py-1.5 font-mono text-[10px] font-black uppercase tracking-wider transition-all hover:bg-red-100 active:scale-95"
                        >
                            Delete
                        </button>
                    </div>
                </div>

                <div
                    v-if="filteredAndSortedRoutes.length === 0"
                    class="col-span-2 rounded-2xl border border-dashed border-slate-200 bg-white p-16 text-center text-xs font-medium text-slate-400"
                >
                    No patrol routes match the query.
                </div>
            </div>
        </div>

        <!-- FORM MODAL -->
        <RouteModal
            :show="showAddRouteModal"
            :checkpoints="checkpoints"
            :editRoute="editingRoute"
            @close="showAddRouteModal = false"
            @submit="submitAddRoute"
        />
    </AdminLayout>
</template>
