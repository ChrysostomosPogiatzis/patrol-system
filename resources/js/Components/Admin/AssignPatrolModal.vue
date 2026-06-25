<script setup lang="ts">
import axios from 'axios';
import { computed, ref, watch } from 'vue';

interface Route {
    id: number;
    name: string;
    description?: string;
    expected_duration_mins?: number;
    route_checkpoints?: any[];
}

interface Guard {
    id: number;
    full_name: string;
    employee_id: string;
}

interface Assignment {
    id: number;
    route_id: number;
    route: Route;
    schedule_start: string | null;
    schedule_end: string | null;
    cron_schedule: string | null;
    is_active: boolean;
}

const props = defineProps<{
    show: boolean;
    guard: Guard | null;
    routes: Route[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'assigned'): void;
}>();

const assignments = ref<Assignment[]>([]);
const loading = ref(false);
const saving = ref(false);
const errorMsg = ref('');
const successMsg = ref('');

// Form state
const selectedRouteId = ref<number | null>(null);
const scheduleStart = ref('');
const scheduleEnd = ref('');
const cronSchedule = ref('');
const showAdvanced = ref(false);

// Available routes not yet assigned
const unassignedRoutes = computed(() => {
    const assignedRouteIds = assignments.value.map((a) => a.route_id);
    return props.routes.filter((r) => !assignedRouteIds.includes(r.id));
});

async function fetchAssignments() {
    if (!props.guard) return;
    loading.value = true;
    try {
        const res = await axios.get(
            `/admin/api/guards/${props.guard.id}/assignments`,
        );
        assignments.value = res.data.assignments;
    } catch (e) {
        console.error('Failed to fetch assignments:', e);
    } finally {
        loading.value = false;
    }
}

async function assignRoute() {
    if (!selectedRouteId.value || !props.guard) return;
    saving.value = true;
    errorMsg.value = '';
    successMsg.value = '';
    try {
        await axios.post('/admin/api/assignments', {
            guard_id: props.guard.id,
            route_id: selectedRouteId.value,
            schedule_start: scheduleStart.value || null,
            schedule_end: scheduleEnd.value || null,
            cron_schedule: cronSchedule.value || null,
        });
        successMsg.value = 'Patrol route assigned successfully!';
        selectedRouteId.value = null;
        scheduleStart.value = '';
        scheduleEnd.value = '';
        cronSchedule.value = '';
        showAdvanced.value = false;
        await fetchAssignments();
        emit('assigned');
    } catch (e: any) {
        errorMsg.value = e.response?.data?.message || 'Failed to assign route.';
    } finally {
        saving.value = false;
    }
}

async function removeAssignment(id: number) {
    if (!confirm('Remove this patrol assignment?')) return;
    try {
        await axios.delete(`/admin/api/assignments/${id}`);
        await fetchAssignments();
        emit('assigned');
    } catch (e: any) {
        alert(e.response?.data?.message || 'Failed to remove assignment.');
    }
}

function formatDate(dateStr: string | null) {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleString([], {
            dateStyle: 'short',
            timeStyle: 'short',
        });
    } catch {
        return dateStr;
    }
}

watch(
    () => props.show,
    (val) => {
        if (val && props.guard) {
            errorMsg.value = '';
            successMsg.value = '';
            selectedRouteId.value = null;
            scheduleStart.value = '';
            scheduleEnd.value = '';
            cronSchedule.value = '';
            showAdvanced.value = false;
            fetchAssignments();
        }
    },
);
</script>

<template>
    <Teleport to="body">
        <Transition name="modal-fade">
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                @click.self="$emit('close')"
            >
                <!-- Backdrop -->
                <div
                    class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
                    @click="$emit('close')"
                ></div>

                <!-- Modal Card -->
                <div
                    class="relative z-10 flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-3xl border border-slate-200/60 bg-white shadow-2xl"
                >
                    <!-- Header -->
                    <div
                        class="flex flex-shrink-0 items-center justify-between bg-gradient-to-r from-indigo-600 to-violet-600 px-7 py-5"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/20"
                            >
                                <svg
                                    class="h-5 w-5 text-white"
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
                            </div>
                            <div>
                                <h3
                                    class="text-sm font-black tracking-wide text-white"
                                >
                                    Patrol Assignments
                                </h3>
                                <p
                                    class="mt-0.5 text-xs font-medium text-indigo-200"
                                >
                                    {{ guard?.full_name }}
                                    <span class="opacity-70"
                                        >· #{{ guard?.employee_id }}</span
                                    >
                                </p>
                            </div>
                        </div>
                        <button
                            @click="$emit('close')"
                            class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/15 transition-colors hover:bg-white/25"
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
                                    stroke-width="2.5"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>

                    <!-- Body (scrollable) -->
                    <div class="flex-1 space-y-6 overflow-y-auto p-7">
                        <!-- Assign New Route Form -->
                        <div class="space-y-4">
                            <h4
                                class="font-mono text-[10px] font-black uppercase tracking-widest text-slate-400"
                            >
                                Assign New Patrol Route
                            </h4>

                            <!-- No routes available -->
                            <div
                                v-if="routes.length === 0"
                                class="flex items-center gap-2 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs font-medium text-amber-700"
                            >
                                <span>⚠️</span>
                                <span
                                    >No patrol routes found. Create patrol
                                    routes first from the
                                    <strong>Routes</strong> section.</span
                                >
                            </div>

                            <div
                                v-else-if="unassignedRoutes.length === 0"
                                class="flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-medium text-emerald-700"
                            >
                                <span>✅</span>
                                <span
                                    >All available patrol routes are already
                                    assigned to this guard.</span
                                >
                            </div>

                            <div v-else class="space-y-3">
                                <!-- Route selector -->
                                <div>
                                    <label
                                        class="mb-1.5 block font-mono text-[10px] font-black uppercase tracking-widest text-slate-500"
                                        >Select Route</label
                                    >
                                    <select
                                        v-model="selectedRouteId"
                                        class="w-full cursor-pointer rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm transition-all focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    >
                                        <option :value="null">
                                            — Choose a patrol route —
                                        </option>
                                        <option
                                            v-for="r in unassignedRoutes"
                                            :key="r.id"
                                            :value="r.id"
                                        >
                                            {{ r.name }}
                                            {{
                                                r.expected_duration_mins
                                                    ? `(${r.expected_duration_mins} min)`
                                                    : ''
                                            }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Selected route preview -->
                                <div
                                    v-if="selectedRouteId"
                                    class="border-indigo-150 rounded-xl border bg-indigo-50 p-3"
                                >
                                    <p
                                        class="text-xs font-medium text-indigo-600"
                                    >
                                        {{
                                            routes.find(
                                                (r) => r.id === selectedRouteId,
                                            )?.description || 'No description.'
                                        }}
                                    </p>
                                    <p
                                        class="mt-1 font-mono text-[10px] text-indigo-400"
                                    >
                                        {{
                                            routes.find(
                                                (r) => r.id === selectedRouteId,
                                            )?.route_checkpoints?.length || 0
                                        }}
                                        checkpoint(s) ·
                                        {{
                                            routes.find(
                                                (r) => r.id === selectedRouteId,
                                            )?.expected_duration_mins || 30
                                        }}
                                        min expected
                                    </p>
                                </div>

                                <!-- Advanced scheduling toggle -->
                                <button
                                    @click="showAdvanced = !showAdvanced"
                                    class="flex items-center gap-1 font-mono text-[10px] font-black uppercase tracking-widest text-indigo-500 transition-colors hover:text-indigo-700"
                                >
                                    <svg
                                        class="h-3 w-3 transition-transform"
                                        :class="showAdvanced ? 'rotate-90' : ''"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2.5"
                                            d="M9 5l7 7-7 7"
                                        />
                                    </svg>
                                    Advanced Scheduling (optional)
                                </button>

                                <div
                                    v-if="showAdvanced"
                                    class="grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2"
                                >
                                    <div>
                                        <label
                                            class="mb-1.5 block font-mono text-[10px] font-black uppercase tracking-widest text-slate-500"
                                            >Schedule Start</label
                                        >
                                        <input
                                            v-model="scheduleStart"
                                            type="datetime-local"
                                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs transition-all focus:border-indigo-500 focus:outline-none"
                                        />
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1.5 block font-mono text-[10px] font-black uppercase tracking-widest text-slate-500"
                                            >Schedule End</label
                                        >
                                        <input
                                            v-model="scheduleEnd"
                                            type="datetime-local"
                                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs transition-all focus:border-indigo-500 focus:outline-none"
                                        />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label
                                            class="mb-1.5 block font-mono text-[10px] font-black uppercase tracking-widest text-slate-500"
                                            >Cron Schedule
                                            <span
                                                class="font-normal normal-case text-slate-400"
                                                >(e.g. 0 22 * * 1-5)</span
                                            ></label
                                        >
                                        <input
                                            v-model="cronSchedule"
                                            type="text"
                                            placeholder="e.g. 0 22 * * 1-5  (Mon-Fri at 10pm)"
                                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 font-mono text-xs transition-all focus:border-indigo-500 focus:outline-none"
                                        />
                                    </div>
                                </div>

                                <!-- Feedback messages -->
                                <p
                                    v-if="errorMsg"
                                    class="rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-xs font-medium text-red-600"
                                >
                                    {{ errorMsg }}
                                </p>
                                <p
                                    v-if="successMsg"
                                    class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-xs font-medium text-emerald-600"
                                >
                                    {{ successMsg }}
                                </p>

                                <button
                                    @click="assignRoute"
                                    :disabled="!selectedRouteId || saving"
                                    class="flex w-full items-center justify-center gap-2 rounded-xl py-3 text-xs font-black uppercase tracking-wider transition-all"
                                    :class="
                                        !selectedRouteId || saving
                                            ? 'cursor-not-allowed bg-slate-100 text-slate-400'
                                            : 'bg-indigo-600 text-white shadow-md hover:bg-indigo-500 active:scale-[0.99]'
                                    "
                                >
                                    <svg
                                        v-if="!saving"
                                        class="h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2.2"
                                            d="M12 4v16m8-8H4"
                                        />
                                    </svg>
                                    <svg
                                        v-else
                                        class="h-4 w-4 animate-spin"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle
                                            class="opacity-25"
                                            cx="12"
                                            cy="12"
                                            r="10"
                                            stroke="currentColor"
                                            stroke-width="4"
                                        />
                                        <path
                                            class="opacity-75"
                                            fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                        />
                                    </svg>
                                    {{
                                        saving
                                            ? 'Assigning...'
                                            : 'Assign Patrol Route'
                                    }}
                                </button>
                            </div>
                        </div>

                        <!-- Divider -->
                        <div class="border-slate-150 border-t"></div>

                        <!-- Current Assignments List -->
                        <div class="space-y-3">
                            <h4
                                class="font-mono text-[10px] font-black uppercase tracking-widest text-slate-400"
                            >
                                Current Assignments
                                <span
                                    class="ml-2 rounded-full bg-indigo-100 px-2 py-0.5 text-[9px] text-indigo-600"
                                    >{{ assignments.length }}</span
                                >
                            </h4>

                            <!-- Loading state -->
                            <div
                                v-if="loading"
                                class="flex items-center justify-center py-8 text-slate-400"
                            >
                                <svg
                                    class="mr-2 h-5 w-5 animate-spin"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    />
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                    />
                                </svg>
                                <span class="text-xs font-medium"
                                    >Loading assignments...</span
                                >
                            </div>

                            <!-- Empty state -->
                            <div
                                v-else-if="assignments.length === 0"
                                class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center"
                            >
                                <svg
                                    class="mx-auto mb-2 h-8 w-8 text-slate-300"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.5"
                                        d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"
                                    />
                                </svg>
                                <p class="text-xs font-medium text-slate-400">
                                    No patrol routes assigned yet
                                </p>
                            </div>

                            <!-- Assignment cards -->
                            <div v-else class="space-y-2.5">
                                <div
                                    v-for="a in assignments"
                                    :key="a.id"
                                    class="group flex items-start justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 transition-colors hover:border-indigo-200"
                                >
                                    <div class="flex min-w-0 items-start gap-3">
                                        <div
                                            class="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl border border-indigo-100 bg-indigo-50"
                                        >
                                            <svg
                                                class="h-4 w-4 text-indigo-500"
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
                                        </div>
                                        <div class="min-w-0">
                                            <p
                                                class="truncate font-mono text-sm font-black text-slate-800 transition-colors group-hover:text-indigo-600"
                                            >
                                                {{ a.route?.name }}
                                            </p>
                                            <div
                                                class="mt-1.5 flex flex-wrap gap-2"
                                            >
                                                <span
                                                    v-if="a.schedule_start"
                                                    class="border-slate-150 rounded-lg border bg-slate-50 px-2 py-0.5 font-mono text-[9px] text-slate-500"
                                                >
                                                    📅
                                                    {{
                                                        formatDate(
                                                            a.schedule_start,
                                                        )
                                                    }}
                                                    →
                                                    {{
                                                        formatDate(
                                                            a.schedule_end,
                                                        )
                                                    }}
                                                </span>
                                                <span
                                                    v-if="a.cron_schedule"
                                                    class="rounded-lg border border-violet-100 bg-violet-50 px-2 py-0.5 font-mono text-[9px] text-violet-600"
                                                >
                                                    🔁 {{ a.cron_schedule }}
                                                </span>
                                                <span
                                                    class="rounded-full border px-2 py-0.5 font-mono text-[9px] font-black uppercase"
                                                    :class="
                                                        a.is_active
                                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-600'
                                                            : 'border-slate-200 bg-slate-50 text-slate-500'
                                                    "
                                                >
                                                    {{
                                                        a.is_active
                                                            ? 'Active'
                                                            : 'Inactive'
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <button
                                        @click="removeAssignment(a.id)"
                                        class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg border border-red-100/60 bg-red-50 text-red-500 transition-all hover:bg-red-100 active:scale-95"
                                        title="Remove assignment"
                                    >
                                        <svg
                                            class="h-3.5 w-3.5"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2.5"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                            />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div
                        class="flex flex-shrink-0 justify-end border-t border-slate-100 bg-slate-50/50 px-7 py-4"
                    >
                        <button
                            @click="$emit('close')"
                            class="hover:border-slate-350 rounded-xl border border-slate-200 bg-white px-6 py-2.5 font-mono text-xs font-black uppercase tracking-wider text-slate-600 transition-all active:scale-95"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.2s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}
.modal-fade-enter-active .relative,
.modal-fade-leave-active .relative {
    transition: transform 0.2s ease;
}
.modal-fade-enter-from .relative {
    transform: scale(0.97) translateY(8px);
}
</style>
