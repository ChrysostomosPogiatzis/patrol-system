<script setup lang="ts">
import { ref, watch } from 'vue';

interface Contact {
    id: number;
    name: string;
    role_label: string;
    phone?: string;
    email?: string;
    notify_channels: string[];
    notify_on: string[];
}

const props = defineProps<{
    show: boolean;
    contact?: Contact | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'submit', form: any): void;
}>();

const form = ref({
    name: '',
    role_label: 'Operator',
    phone: '',
    email: '',
    notify_channels: ['email'] as string[],
    notify_on: ['sos_triggered'] as string[]
});

watch(() => props.show, (newVal) => {
    if (newVal) {
        if (props.contact) {
            form.value = {
                name: props.contact.name,
                role_label: props.contact.role_label,
                phone: props.contact.phone || '',
                email: props.contact.email || '',
                notify_channels: props.contact.notify_channels || ['email'],
                notify_on: props.contact.notify_on || ['sos_triggered']
            };
        } else {
            form.value = {
                name: '',
                role_label: 'Operator',
                phone: '',
                email: '',
                notify_channels: ['email'],
                notify_on: ['sos_triggered']
            };
        }
    }
});

function handleSubmit() {
    if (!form.value.name || !form.value.role_label) {
        alert('Please fill out the contact name and role.');
        return;
    }
    if (form.value.notify_channels.length === 0) {
        alert('Please select at least one notification channel.');
        return;
    }
    if (form.value.notify_on.length === 0) {
        alert('Please select at least one notification trigger event.');
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
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 w-full max-w-sm space-y-4 shadow-2xl overflow-y-auto max-h-[90vh]">
            <div class="flex justify-between items-center pb-2 border-b border-slate-100 dark:border-slate-800">
                <h4 class="text-sm font-black uppercase tracking-widest text-slate-800 dark:text-slate-100 font-mono">
                    {{ contact ? 'Edit Alert Contact' : 'Add Alert Contact' }}
                </h4>
                <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="space-y-3.5 text-xs text-slate-700 dark:text-slate-355">
                <div class="space-y-1">
                    <label class="block text-[10px] text-slate-450 dark:text-slate-500 uppercase tracking-widest font-black">Contact Name *</label>
                    <input v-model="form.name" type="text" class="w-full bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-800 p-3 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-none min-h-[48px]" placeholder="Operations Manager" />
                </div>
                <div class="space-y-1">
                    <label class="block text-[10px] text-slate-450 dark:text-slate-500 uppercase tracking-widest font-black">Role Label *</label>
                    <input v-model="form.role_label" type="text" class="w-full bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-800 p-3 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-none min-h-[48px]" placeholder="Supervisor" />
                </div>
                <div class="space-y-1">
                    <label class="block text-[10px] text-slate-450 dark:text-slate-500 uppercase tracking-widest font-black">Phone Number (SMS alerts)</label>
                    <input v-model="form.phone" type="text" class="w-full bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-800 p-3 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-none min-h-[48px]" placeholder="+35799887766" />
                </div>
                <div class="space-y-1">
                    <label class="block text-[10px] text-slate-450 dark:text-slate-500 uppercase tracking-widest font-black">Email Address</label>
                    <input v-model="form.email" type="email" class="w-full bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-800 p-3 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-none min-h-[48px]" placeholder="supervisor@company.com" />
                </div>

                <!-- Channels -->
                <div class="space-y-1.5">
                    <label class="block text-[10px] text-slate-450 dark:text-slate-500 uppercase tracking-widest font-black">Notification Channels *</label>
                    <div class="flex flex-wrap gap-2">
                        <label class="flex items-center px-3 py-2 bg-slate-50 dark:bg-slate-950/45 border border-slate-200 dark:border-slate-800 rounded-xl cursor-pointer">
                            <input type="checkbox" value="email" v-model="form.notify_channels" class="accent-indigo-650 w-4 h-4 rounded" />
                            <span class="ms-2 text-xs font-bold text-slate-650 dark:text-slate-350">Email</span>
                        </label>
                        <label class="flex items-center px-3 py-2 bg-slate-50 dark:bg-slate-950/45 border border-slate-200 dark:border-slate-800 rounded-xl cursor-pointer">
                            <input type="checkbox" value="sms" v-model="form.notify_channels" class="accent-indigo-650 w-4 h-4 rounded" />
                            <span class="ms-2 text-xs font-bold text-slate-655 dark:text-slate-355">SMS</span>
                        </label>
                    </div>
                </div>

                <!-- Triggers -->
                <div class="space-y-1.5">
                    <label class="block text-[10px] text-slate-450 dark:text-slate-500 uppercase tracking-widest font-black">Alert On Trigger Events *</label>
                    <div class="space-y-2">
                        <label class="flex items-center p-2.5 bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-800 rounded-xl cursor-pointer">
                            <input type="checkbox" value="sos_triggered" v-model="form.notify_on" class="accent-indigo-650 w-4 h-4 rounded" />
                            <span class="ms-2 text-xs font-bold text-slate-650 dark:text-slate-300">SOS Panic Triggered</span>
                        </label>
                        <label class="flex items-center p-2.5 bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-800 rounded-xl cursor-pointer">
                            <input type="checkbox" value="patrol_completed" v-model="form.notify_on" class="accent-indigo-650 w-4 h-4 rounded" />
                            <span class="ms-2 text-xs font-bold text-slate-650 dark:text-slate-300">Patrol Completed</span>
                        </label>
                        <label class="flex items-center p-2.5 bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-800 rounded-xl cursor-pointer">
                            <input type="checkbox" value="incident_created" v-model="form.notify_on" class="accent-indigo-650 w-4 h-4 rounded" />
                            <span class="ms-2 text-xs font-bold text-slate-650 dark:text-slate-300">Incident Reported</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex space-x-3 pt-3 border-t border-slate-105 dark:border-slate-800">
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
                    {{ contact ? 'Save Changes' : 'Add Contact' }}
                </button>
            </div>
        </div>
    </div>
</template>
