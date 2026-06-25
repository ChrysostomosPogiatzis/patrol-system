<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';

// Component Import
import AssignPatrolModal from '@/Components/Admin/AssignPatrolModal.vue';
import GuardModal from '@/Components/Admin/GuardModal.vue';

interface Guard {
    id: number;
    full_name: string;
    phone: string;
    employee_id: string;
    is_active: boolean;
    last_login_at: string | null;
    last_seen_at: string | null;
    tenant?: {
        id: number;
        name: string;
    };
}

interface Route {
    id: number;
    name: string;
    description?: string;
    expected_duration_mins?: number;
    route_checkpoints?: any[];
}

const guards = ref<Guard[]>([]);
const routes = ref<Route[]>([]);
const showAddGuardModal = ref(false);
const editingGuard = ref<Guard | null>(null);
const showAssignModal = ref(false);
const assigningGuard = ref<Guard | null>(null);

const page = usePage();
const isAllCompaniesMode = computed(() => {
    const user = page.props.auth.user as any;
    return (
        user.role === 'superadmin' &&
        !(page.props.auth as any).override_tenant_id
    );
});

const searchQuery = ref('');
const statusFilter = ref('all');
const sortKey = ref('id');
const sortOrder = ref<'asc' | 'desc'>('desc');

async function fetchGuards() {
    try {
        const res = await axios.get('/admin/api/guards');
        guards.value = res.data.guards;
    } catch (e) {
        console.error('Failed to fetch guards:', e);
    }
}

async function fetchRoutes() {
    try {
        const res = await axios.get('/admin/api/locations-data');
        routes.value = res.data.routes;
    } catch (e) {
        console.error('Failed to fetch routes:', e);
    }
}

function openAddGuard() {
    if (isAllCompaniesMode.value) return;
    editingGuard.value = null;
    showAddGuardModal.value = true;
}

function openEditGuard(guard: Guard) {
    editingGuard.value = guard;
    showAddGuardModal.value = true;
}

function openAssignPatrol(guard: Guard) {
    assigningGuard.value = guard;
    showAssignModal.value = true;
}

async function deleteGuard(id: number) {
    if (!confirm('Are you sure you want to delete this security guard?'))
        return;
    try {
        await axios.delete(`/admin/api/guards/${id}`);
        fetchGuards();
    } catch (e: any) {
        alert(e.response?.data?.message || 'Failed to delete guard.');
    }
}

async function submitAddGuard(payload: any) {
    try {
        if (editingGuard.value) {
            await axios.put(
                `/admin/api/guards/${editingGuard.value.id}`,
                payload,
            );
        } else {
            await axios.post('/admin/api/guards', payload);
        }
        showAddGuardModal.value = false;
        fetchGuards();
    } catch (e: any) {
        alert(
            e.response?.data?.message || 'Phone or Employee ID already exists.',
        );
    }
}

function formatDate(dateStr: string | null) {
    if (!dateStr) return 'Never';
    try {
        return new Date(dateStr).toLocaleString([], {
            dateStyle: 'short',
            timeStyle: 'short',
        });
    } catch (e) {
        return dateStr;
    }
}

const filteredAndSortedGuards = computed(() => {
    let list = guards.value;

    // Filtering
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(
            (g) =>
                g.full_name.toLowerCase().includes(q) ||
                g.phone.toLowerCase().includes(q) ||
                g.employee_id.toLowerCase().includes(q),
        );
    }

    if (statusFilter.value === 'active') {
        list = list.filter((g) => g.is_active);
    } else if (statusFilter.value === 'inactive') {
        list = list.filter((g) => !g.is_active);
    }

    // Sorting
    list = [...list].sort((a, b) => {
        let valA = (a as any)[sortKey.value];
        let valB = (b as any)[sortKey.value];

        if (valA === null || valA === undefined) valA = '';
        if (valB === null || valB === undefined) valB = '';

        if (typeof valA === 'string' && typeof valB === 'string') {
            return sortOrder.value === 'asc'
                ? valA.localeCompare(valB)
                : valB.localeCompare(valA);
        } else {
            return sortOrder.value === 'asc'
                ? valA > valB
                    ? 1
                    : -1
                : valA < valB
                  ? 1
                  : -1;
        }
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
    fetchGuards();
    fetchRoutes();
});
</script>

<template>
    <Head title="Guards Registry" />

    <AdminLayout title="Security Guards Registry">
        <div class="space-y-4">
            <!-- Action Header -->
            <div
                class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center"
            >
                <span
                    class="font-mono text-xs font-black uppercase tracking-widest text-slate-500"
                    >Guards Directory ({{
                        filteredAndSortedGuards.length
                    }})</span
                >
                <button
                    @click="openAddGuard"
                    :disabled="isAllCompaniesMode"
                    class="flex min-h-[48px] items-center space-x-2 rounded-xl px-5 py-3 text-xs font-black uppercase tracking-wider text-white shadow-md transition-all"
                    :class="
                        isAllCompaniesMode
                            ? 'bg-slate-350 cursor-not-allowed opacity-60'
                            : 'bg-indigo-600 hover:bg-indigo-500 active:scale-95'
                    "
                    :title="
                        isAllCompaniesMode
                            ? 'Select a company from the top context selector to register guards'
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
                            d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"
                        />
                    </svg>
                    <span>Register Guard</span>
                </button>
            </div>

            <!-- Context warning banner -->
            <div
                v-if="isAllCompaniesMode"
                class="border-indigo-150 text-indigo-750 flex items-center gap-3 rounded-2xl border bg-indigo-50 p-4 text-xs font-medium"
            >
                <span class="text-base">ℹ️</span>
                <span
                    >You are viewing guards across
                    <strong>all companies</strong>. To register new security
                    guards or edit their security PIN configurations, please
                    select a specific company context from the dropdown at the
                    top.</span
                >
            </div>

            <!-- Filters Bar -->
            <div
                class="flex flex-col gap-4 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm md:flex-row"
            >
                <div class="relative flex-1">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search guards by name, ID, or phone..."
                        class="min-h-[42px] w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs focus:border-indigo-500 focus:outline-none"
                    />
                    <span class="absolute left-3.5 top-3 text-xs text-slate-400"
                        >🔍</span
                    >
                </div>
                <div class="w-full md:w-48">
                    <select
                        v-model="statusFilter"
                        class="min-h-[42px] w-full cursor-pointer rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs focus:border-indigo-500 focus:outline-none"
                    >
                        <option value="all">All Statuses</option>
                        <option value="active">Active Only</option>
                        <option value="inactive">Inactive Only</option>
                    </select>
                </div>
            </div>

            <!-- Table Container -->
            <div
                class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm"
            >
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr
                                class="border-slate-150 text-slate-450 border-b bg-slate-50/50 font-mono text-[9px] font-black uppercase tracking-widest"
                            >
                                <th v-if="isAllCompaniesMode" class="p-5">
                                    Company
                                </th>
                                <th
                                    class="cursor-pointer select-none p-5 hover:bg-slate-100"
                                    @click="toggleSort('full_name')"
                                >
                                    Guard Details
                                    <span v-if="sortKey === 'full_name'">{{
                                        sortOrder === 'asc' ? '▲' : '▼'
                                    }}</span>
                                </th>
                                <th
                                    class="cursor-pointer select-none p-5 hover:bg-slate-100"
                                    @click="toggleSort('employee_id')"
                                >
                                    Employee ID
                                    <span v-if="sortKey === 'employee_id'">{{
                                        sortOrder === 'asc' ? '▲' : '▼'
                                    }}</span>
                                </th>
                                <th
                                    class="cursor-pointer select-none p-5 hover:bg-slate-100"
                                    @click="toggleSort('phone')"
                                >
                                    Phone Number
                                    <span v-if="sortKey === 'phone'">{{
                                        sortOrder === 'asc' ? '▲' : '▼'
                                    }}</span>
                                </th>
                                <th
                                    class="cursor-pointer select-none p-5 hover:bg-slate-100"
                                    @click="toggleSort('last_seen_at')"
                                >
                                    Last Activity
                                    <span v-if="sortKey === 'last_seen_at'">{{
                                        sortOrder === 'asc' ? '▲' : '▼'
                                    }}</span>
                                </th>
                                <th
                                    class="cursor-pointer select-none p-5 text-center hover:bg-slate-100"
                                    @click="toggleSort('is_active')"
                                >
                                    Status
                                    <span v-if="sortKey === 'is_active'">{{
                                        sortOrder === 'asc' ? '▲' : '▼'
                                    }}</span>
                                </th>
                                <th class="p-5 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-slate-150 divide-y text-xs text-slate-600"
                        >
                            <tr
                                v-for="g in filteredAndSortedGuards"
                                :key="g.id"
                                class="group hover:bg-slate-50/50"
                            >
                                <td
                                    v-if="isAllCompaniesMode"
                                    class="p-5 font-bold text-slate-700"
                                >
                                    {{ g.tenant?.name || 'System' }}
                                </td>
                                <td
                                    class="p-5 font-mono font-black text-slate-800 transition-colors group-hover:text-indigo-600"
                                >
                                    {{ g.full_name }}
                                </td>
                                <td
                                    class="p-5 font-mono font-bold text-indigo-600"
                                >
                                    {{ g.employee_id }}
                                </td>
                                <td class="p-5 font-mono text-slate-500">
                                    {{ g.phone }}
                                </td>
                                <td class="text-slate-450 p-5 font-mono">
                                    {{ formatDate(g.last_seen_at) }}
                                </td>
                                <td class="p-5 text-center">
                                    <span
                                        class="inline-flex rounded-full border px-2.5 py-0.5 font-mono text-[9px] font-black uppercase tracking-wider"
                                        :class="
                                            g.is_active
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-600'
                                                : 'text-red-650 border-red-200 bg-red-50'
                                        "
                                    >
                                        {{
                                            g.is_active ? 'Active' : 'Inactive'
                                        }}
                                    </span>
                                </td>
                                <td class="p-5 text-center">
                                    <div
                                        class="flex items-center justify-center space-x-2"
                                    >
                                        <button
                                            @click="openAssignPatrol(g)"
                                            class="text-violet-750 rounded-lg border border-violet-100/60 bg-violet-50 px-3 py-1.5 font-mono text-[10px] font-black uppercase tracking-wider transition-all hover:bg-violet-100 active:scale-95"
                                        >
                                            Assign Route
                                        </button>
                                        <button
                                            @click="openEditGuard(g)"
                                            class="text-indigo-650 rounded-lg border border-indigo-100/60 bg-indigo-50 px-3 py-1.5 font-mono text-[10px] font-black uppercase tracking-wider transition-all hover:bg-indigo-100 active:scale-95"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            @click="deleteGuard(g.id)"
                                            class="rounded-lg border border-red-100/60 bg-red-50 px-3 py-1.5 font-mono text-[10px] font-black uppercase tracking-wider text-red-600 transition-all hover:bg-red-100 active:scale-95"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredAndSortedGuards.length === 0">
                                <td
                                    :colspan="isAllCompaniesMode ? 7 : 6"
                                    class="p-16 text-center font-medium text-slate-400"
                                >
                                    No security guards match the filter
                                    criteria.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- FORM MODAL -->
        <GuardModal
            :show="showAddGuardModal"
            :guard="editingGuard"
            @close="showAddGuardModal = false"
            @submit="submitAddGuard"
        />
        <AssignPatrolModal
            :show="showAssignModal"
            :guard="assigningGuard"
            :routes="routes"
            @close="showAssignModal = false"
            @assigned="fetchGuards"
        />
    </AdminLayout>
</template>
