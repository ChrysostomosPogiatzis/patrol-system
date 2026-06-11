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
    route_checkpoints?: { checkpoint_id?: number; id?: number; position?: number; checkpoint?: any }[];
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
    checkpoints: [] as number[]
});

watch(() => props.show, (newVal) => {
    if (newVal) {
        if (props.editRoute) {
            form.value = {
                name: props.editRoute.name,
                description: props.editRoute.description || '',
                enforce_order: !!props.editRoute.enforce_order,
                allow_skip: !!props.editRoute.allow_skip,
                expected_duration_mins: props.editRoute.expected_duration_mins || 30,
                checkpoints: props.editRoute.route_checkpoints
                    ? props.editRoute.route_checkpoints.map((rc: any) => rc.checkpoint_id ?? rc.id)
                    : []
            };
        } else {
            form.value = {
                name: '',
                description: '',
                enforce_order: true,
                allow_skip: true,
                expected_duration_mins: 30,
                checkpoints: []
            };
        }
    }
});

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
        class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-6 transition-all duration-300"
    >
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 w-full max-w-md space-y-4 shadow-2xl overflow-y-auto max-h-[90vh]">
            <div class="flex justify-between items-center pb-2 border-b border-slate-100 dark:border-slate-800">
                <h4 class="text-sm font-black uppercase tracking-widest text-slate-800 dark:text-slate-100 font-mono">
                    {{ editRoute ? 'Edit Patrol Route' : 'Create Patrol Route' }}
                </h4>
                <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="space-y-3.5 text-xs text-slate-700 dark:text-slate-350">
                <div class="space-y-1">
                    <label class="block text-[10px] text-slate-450 dark:text-slate-500 uppercase tracking-widest font-black">Route Name *</label>
                    <input v-model="form.name" type="text" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 p-3 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-none min-h-[48px]" placeholder="Dock Patrol Shift" />
                </div>
                <div class="space-y-1">
                    <label class="block text-[10px] text-slate-455 dark:text-slate-500 uppercase tracking-widest font-black">Description</label>
                    <textarea v-model="form.description" rows="2" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 p-3 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-none placeholder:text-slate-400 dark:placeholder:text-slate-655" placeholder="Covering key assets..."></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="block text-[10px] text-slate-450 dark:text-slate-500 uppercase tracking-widest font-black">Sequence Order</label>
                        <select v-model="form.enforce_order" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 p-3 rounded-xl text-slate-805 dark:text-slate-100 focus:outline-none min-h-[48px]">
                            <option :value="true">Strict Sequence (1, 2, 3)</option>
                            <option :value="false">Flexible Order</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] text-slate-450 dark:text-slate-500 uppercase tracking-widest font-black">Allow Checkpoint Skip</label>
                        <select v-model="form.allow_skip" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 p-3 rounded-xl text-slate-805 dark:text-slate-100 focus:outline-none min-h-[48px]">
                            <option :value="true">Yes</option>
                            <option :value="false">No</option>
                        </select>
                    </div>
                </div>
                
                <div class="space-y-1">
                    <label class="block text-[10px] text-slate-450 dark:text-slate-500 uppercase tracking-widest font-black">Expected Duration (mins)</label>
                    <input v-model.number="form.expected_duration_mins" type="number" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 p-3 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-none min-h-[48px]" />
                </div>

                <!-- Checkpoint Sequence Selector -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between pl-1">
                        <label class="block text-[10px] text-slate-450 dark:text-slate-500 uppercase tracking-widest font-black">Assign Checkpoints (Check to add in order) *</label>
                        <span v-if="form.checkpoints.length > 0" class="text-[10px] font-bold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-full px-2 py-0.5">
                            {{ form.checkpoints.length }} selected
                        </span>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl overflow-hidden">
                        <div 
                            v-for="cp in checkpoints" 
                            :key="cp.id" 
                            @click="toggleCheckpoint(cp.id)"
                            class="flex items-center gap-3 px-3 py-2.5 cursor-pointer border-b border-slate-100 last:border-b-0 transition-colors duration-150"
                            :class="form.checkpoints.includes(cp.id) ? 'bg-indigo-50 hover:bg-indigo-100' : 'hover:bg-slate-100'"
                        >
                            <!-- Sequence badge -->
                            <div 
                                class="w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-black flex-shrink-0 transition-all duration-200 select-none"
                                :class="form.checkpoints.includes(cp.id)
                                    ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200'
                                    : 'bg-slate-200 text-slate-400'"
                            >
                                {{ form.checkpoints.includes(cp.id) ? form.checkpoints.indexOf(cp.id) + 1 : '–' }}
                            </div>

                            <!-- Checkpoint info -->
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold truncate" :class="form.checkpoints.includes(cp.id) ? 'text-indigo-800' : 'text-slate-700'">
                                    {{ cp.name }}
                                </p>
                                <p class="text-[10px] truncate" :class="form.checkpoints.includes(cp.id) ? 'text-indigo-500' : 'text-slate-400'">
                                    {{ cp.location?.name ?? 'No location' }}
                                </p>
                            </div>

                            <!-- Checkmark icon -->
                            <svg v-if="form.checkpoints.includes(cp.id)" class="w-4 h-4 text-indigo-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div v-if="checkpoints.length === 0" class="text-slate-400 text-xs text-center py-6">
                            No checkpoints available to assign.
                        </div>
                    </div>

                    <!-- Selected order summary -->
                    <div v-if="form.checkpoints.length > 0" class="flex flex-wrap gap-1.5 pt-1">
                        <span 
                            v-for="(cpId, idx) in form.checkpoints" 
                            :key="cpId"
                            class="inline-flex items-center gap-1 bg-white border border-indigo-200 text-indigo-700 text-[10px] font-bold rounded-lg px-2 py-1 shadow-sm"
                        >
                            <span class="bg-indigo-600 text-white rounded-full w-4 h-4 flex items-center justify-center text-[9px] font-black">{{ idx + 1 }}</span>
                            {{ checkpoints.find(c => c.id === cpId)?.name }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex space-x-3 pt-3">
                <button 
                    @click="$emit('close')" 
                    class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 dark:bg-slate-880 dark:hover:bg-slate-750 text-slate-700 dark:text-slate-350 text-xs font-black uppercase tracking-wider rounded-xl transition-all min-h-[48px]"
                >
                    Cancel
                </button>
                <button 
                    @click="handleSubmit" 
                    class="flex-1 py-3 bg-gradient-to-r from-indigo-500 to-purple-650 hover:from-indigo-600 hover:to-purple-700 text-white text-xs font-black uppercase tracking-wider rounded-xl shadow-md transition-all active:scale-95 min-h-[48px]"
                >
                    {{ editRoute ? 'Save Changes' : 'Create Route' }}
                </button>
            </div>
        </div>
    </div>
</template>
