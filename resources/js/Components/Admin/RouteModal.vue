<script setup lang="ts">
import { ref, watch } from 'vue';

interface Location {
    id: number;
    name: string;
}

interface Checkpoint {
    id: number;
    name: string;
    location?: Location;
}

interface Route {
    id: number;
    name: string;
    description?: string;
    enforce_order: boolean;
    allow_skip: boolean;
    expected_duration_mins?: number;
    route_checkpoints?: {
        checkpoint_id?: number;
        id?: number;
        position?: number;
        checkpoint?: any;
    }[];
}

const props = defineProps<{
    show: boolean;
    checkpoints: Checkpoint[];
    editRoute?: Route | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'submit', form: any): void;
}>();

const form = ref({
    name: '',
    description: '',
    enforce_order: true,
    allow_skip: true,
    expected_duration_mins: 30,
    checkpoints: [] as number[],
});

watch(
    () => props.show,
    (newVal) => {
        if (newVal) {
            if (props.editRoute) {
                form.value = {
                    name: props.editRoute.name,
                    description: props.editRoute.description || '',
                    enforce_order: !!props.editRoute.enforce_order,
                    allow_skip: !!props.editRoute.allow_skip,
                    expected_duration_mins:
                        props.editRoute.expected_duration_mins || 30,
                    checkpoints: props.editRoute.route_checkpoints
                        ? props.editRoute.route_checkpoints.map(
                              (rc: any) => rc.checkpoint_id ?? rc.id,
                          )
                        : [],
                };
            } else {
                form.value = {
                    name: '',
                    description: '',
                    enforce_order: true,
                    allow_skip: true,
                    expected_duration_mins: 30,
                    checkpoints: [],
                };
            }
        }
    },
);

function toggleCheckpoint(id: number) {
    const idx = form.value.checkpoints.indexOf(id);
    if (idx === -1) {
        // Append to end — preserves the click order as the patrol sequence
        form.value.checkpoints.push(id);
    } else {
        form.value.checkpoints.splice(idx, 1);
    }
}

function handleSubmit() {
    if (!form.value.name) {
        alert('Please fill out the route name.');
        return;
    }
    if (form.value.checkpoints.length === 0) {
        alert('Please select at least one checkpoint.');
        return;
    }
    emit('submit', { ...form.value });
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-6 backdrop-blur-sm transition-all duration-300"
    >
        <div
            class="max-h-[90vh] w-full max-w-md space-y-4 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
        >
            <div
                class="flex items-center justify-between border-b border-slate-100 pb-2 dark:border-slate-800"
            >
                <h4
                    class="font-mono text-sm font-black uppercase tracking-widest text-slate-800 dark:text-slate-100"
                >
                    {{
                        editRoute ? 'Edit Patrol Route' : 'Create Patrol Route'
                    }}
                </h4>
                <button
                    @click="$emit('close')"
                    class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
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
            </div>

            <div class="dark:text-slate-350 space-y-3.5 text-xs text-slate-700">
                <div class="space-y-1">
                    <label
                        class="text-slate-450 block text-[10px] font-black uppercase tracking-widest dark:text-slate-500"
                        >Route Name *</label
                    >
                    <input
                        v-model="form.name"
                        type="text"
                        class="min-h-[48px] w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-slate-800 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                        placeholder="Dock Patrol Shift"
                    />
                </div>
                <div class="space-y-1">
                    <label
                        class="text-slate-455 block text-[10px] font-black uppercase tracking-widest dark:text-slate-500"
                        >Description</label
                    >
                    <textarea
                        v-model="form.description"
                        rows="2"
                        class="dark:placeholder:text-slate-655 w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-slate-800 placeholder:text-slate-400 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                        placeholder="Covering key assets..."
                    ></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label
                            class="text-slate-450 block text-[10px] font-black uppercase tracking-widest dark:text-slate-500"
                            >Sequence Order</label
                        >
                        <select
                            v-model="form.enforce_order"
                            class="text-slate-805 min-h-[48px] w-full rounded-xl border border-slate-200 bg-slate-50 p-3 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                        >
                            <option :value="true">
                                Strict Sequence (1, 2, 3)
                            </option>
                            <option :value="false">Flexible Order</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label
                            class="text-slate-450 block text-[10px] font-black uppercase tracking-widest dark:text-slate-500"
                            >Allow Checkpoint Skip</label
                        >
                        <select
                            v-model="form.allow_skip"
                            class="text-slate-805 min-h-[48px] w-full rounded-xl border border-slate-200 bg-slate-50 p-3 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                        >
                            <option :value="true">Yes</option>
                            <option :value="false">No</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-1">
                    <label
                        class="text-slate-450 block text-[10px] font-black uppercase tracking-widest dark:text-slate-500"
                        >Expected Duration (mins)</label
                    >
                    <input
                        v-model.number="form.expected_duration_mins"
                        type="number"
                        class="min-h-[48px] w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-slate-800 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                    />
                </div>

                <!-- Checkpoint Sequence Selector -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between pl-1">
                        <label
                            class="text-slate-450 block text-[10px] font-black uppercase tracking-widest dark:text-slate-500"
                            >Assign Checkpoints (Check to add in order) *</label
                        >
                        <span
                            v-if="form.checkpoints.length > 0"
                            class="rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-600"
                        >
                            {{ form.checkpoints.length }} selected
                        </span>
                    </div>
                    <div
                        class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50"
                    >
                        <div
                            v-for="cp in checkpoints"
                            :key="cp.id"
                            @click="toggleCheckpoint(cp.id)"
                            class="flex cursor-pointer items-center gap-3 border-b border-slate-100 px-3 py-2.5 transition-colors duration-150 last:border-b-0"
                            :class="
                                form.checkpoints.includes(cp.id)
                                    ? 'bg-indigo-50 hover:bg-indigo-100'
                                    : 'hover:bg-slate-100'
                            "
                        >
                            <!-- Sequence badge -->
                            <div
                                class="flex h-7 w-7 flex-shrink-0 select-none items-center justify-center rounded-full text-[11px] font-black transition-all duration-200"
                                :class="
                                    form.checkpoints.includes(cp.id)
                                        ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200'
                                        : 'bg-slate-200 text-slate-400'
                                "
                            >
                                {{
                                    form.checkpoints.includes(cp.id)
                                        ? form.checkpoints.indexOf(cp.id) + 1
                                        : '–'
                                }}
                            </div>

                            <!-- Checkpoint info -->
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-xs font-semibold"
                                    :class="
                                        form.checkpoints.includes(cp.id)
                                            ? 'text-indigo-800'
                                            : 'text-slate-700'
                                    "
                                >
                                    {{ cp.name }}
                                </p>
                                <p
                                    class="truncate text-[10px]"
                                    :class="
                                        form.checkpoints.includes(cp.id)
                                            ? 'text-indigo-500'
                                            : 'text-slate-400'
                                    "
                                >
                                    {{ cp.location?.name ?? 'No location' }}
                                </p>
                            </div>

                            <!-- Checkmark icon -->
                            <svg
                                v-if="form.checkpoints.includes(cp.id)"
                                class="h-4 w-4 flex-shrink-0 text-indigo-500"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2.5"
                                    d="M5 13l4 4L19 7"
                                />
                            </svg>
                        </div>
                        <div
                            v-if="checkpoints.length === 0"
                            class="py-6 text-center text-xs text-slate-400"
                        >
                            No checkpoints available to assign.
                        </div>
                    </div>

                    <!-- Selected order summary -->
                    <div
                        v-if="form.checkpoints.length > 0"
                        class="flex flex-wrap gap-1.5 pt-1"
                    >
                        <span
                            v-for="(cpId, idx) in form.checkpoints"
                            :key="cpId"
                            class="inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-white px-2 py-1 text-[10px] font-bold text-indigo-700 shadow-sm"
                        >
                            <span
                                class="flex h-4 w-4 items-center justify-center rounded-full bg-indigo-600 text-[9px] font-black text-white"
                                >{{ idx + 1 }}</span
                            >
                            {{ checkpoints.find((c) => c.id === cpId)?.name }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex space-x-3 pt-3">
                <button
                    @click="$emit('close')"
                    class="dark:bg-slate-880 dark:hover:bg-slate-750 dark:text-slate-350 min-h-[48px] flex-1 rounded-xl bg-slate-100 py-3 text-xs font-black uppercase tracking-wider text-slate-700 transition-all hover:bg-slate-200"
                >
                    Cancel
                </button>
                <button
                    @click="handleSubmit"
                    class="to-purple-650 min-h-[48px] flex-1 rounded-xl bg-gradient-to-r from-indigo-500 py-3 text-xs font-black uppercase tracking-wider text-white shadow-md transition-all hover:from-indigo-600 hover:to-purple-700 active:scale-95"
                >
                    {{ editRoute ? 'Save Changes' : 'Create Route' }}
                </button>
            </div>
        </div>
    </div>
</template>
