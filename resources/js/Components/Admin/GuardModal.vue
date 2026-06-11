<script setup lang="ts">
import { ref, watch } from 'vue';

interface Guard {
    id: number;
    full_name: string;
    phone: string;
    employee_id: string;
}

const props = defineProps<{
    show: boolean;
    guard?: Guard | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'submit', form: any): void;
}>();

const form = ref({
    full_name: '',
    phone: '',
    employee_id: '',
    pin: '1234'
});

watch(() => props.show, (newVal) => {
    if (newVal) {
        if (props.guard) {
            form.value = {
                full_name: props.guard.full_name,
                phone: props.guard.phone,
                employee_id: props.guard.employee_id,
                pin: '1234' // default representation meaning unchanged
            };
        } else {
            form.value = {
                full_name: '',
                phone: '',
                employee_id: '',
                pin: ''
            };
        }
    }
});

function handleSubmit() {
    if (!form.value.full_name || !form.value.phone || !form.value.employee_id) {
        alert('Please fill out all required fields.');
        return;
    }
    // Only require PIN code if creating a new guard
    if (!props.guard && !form.value.pin) {
        alert('Please assign a PIN code for the new guard.');
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
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 w-full max-w-sm space-y-4 shadow-2xl">
            <div class="flex justify-between items-center pb-2 border-b border-slate-100 dark:border-slate-800">
                <h4 class="text-sm font-black uppercase tracking-widest text-slate-850 dark:text-slate-100 font-mono">
                    {{ guard ? 'Edit Security Guard' : 'Register Security Guard' }}
                </h4>
                <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="space-y-3.5 text-xs text-slate-700 dark:text-slate-350">
                <div class="space-y-1">
                    <label class="block text-[10px] text-slate-450 dark:text-slate-500 uppercase tracking-widest font-black">Guard Full Name *</label>
                    <input v-model="form.full_name" type="text" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 p-3 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-none min-h-[48px]" placeholder="John Doe" />
                </div>
                <div class="space-y-1">
                    <label class="block text-[10px] text-slate-450 dark:text-slate-500 uppercase tracking-widest font-black">Phone Number (Login ID) *</label>
                    <input v-model="form.phone" type="text" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 p-3 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-none min-h-[48px]" placeholder="e.g. +35799123456" />
                </div>
                <div class="space-y-1">
                    <label class="block text-[10px] text-slate-450 dark:text-slate-500 uppercase tracking-widest font-black">Employee ID *</label>
                    <input v-model="form.employee_id" type="text" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 p-3 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-none min-h-[48px]" placeholder="GD-007" />
                </div>
                <div class="space-y-1">
                    <label class="block text-[10px] text-slate-450 dark:text-slate-500 uppercase tracking-widest font-black">
                        Pin Code (Offline Verification) {{ guard ? '(Leave blank to keep current)' : '*' }}
                    </label>
                    <input v-model="form.pin" type="password" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 p-3 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-none min-h-[48px]" placeholder="••••" />
                </div>
            </div>

            <div class="flex space-x-3 pt-3">
                <button 
                    @click="$emit('close')" 
                    class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-750 text-slate-700 dark:text-slate-350 text-xs font-black uppercase tracking-wider rounded-xl transition-all min-h-[48px]"
                >
                    Cancel
                </button>
                <button 
                    @click="handleSubmit" 
                    class="flex-1 py-3 bg-gradient-to-r from-indigo-500 to-purple-650 hover:from-indigo-600 hover:to-purple-700 text-white text-xs font-black uppercase tracking-wider rounded-xl shadow-md transition-all active:scale-95 min-h-[48px]"
                >
                    {{ guard ? 'Save Changes' : 'Register' }}
                </button>
            </div>
        </div>
    </div>
</template>
