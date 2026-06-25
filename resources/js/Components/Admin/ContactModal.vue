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
    notify_on: ['sos_triggered'] as string[],
});

watch(
    () => props.show,
    (newVal) => {
        if (newVal) {
            if (props.contact) {
                form.value = {
                    name: props.contact.name,
                    role_label: props.contact.role_label,
                    phone: props.contact.phone || '',
                    email: props.contact.email || '',
                    notify_channels: props.contact.notify_channels || ['email'],
                    notify_on: props.contact.notify_on || ['sos_triggered'],
                };
            } else {
                form.value = {
                    name: '',
                    role_label: 'Operator',
                    phone: '',
                    email: '',
                    notify_channels: ['email'],
                    notify_on: ['sos_triggered'],
                };
            }
        }
    },
);

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
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-6 backdrop-blur-sm transition-all duration-300"
    >
        <div
            class="max-h-[90vh] w-full max-w-sm space-y-4 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
        >
            <div
                class="flex items-center justify-between border-b border-slate-100 pb-2 dark:border-slate-800"
            >
                <h4
                    class="font-mono text-sm font-black uppercase tracking-widest text-slate-800 dark:text-slate-100"
                >
                    {{ contact ? 'Edit Alert Contact' : 'Add Alert Contact' }}
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

            <div class="dark:text-slate-355 space-y-3.5 text-xs text-slate-700">
                <div class="space-y-1">
                    <label
                        class="text-slate-450 block text-[10px] font-black uppercase tracking-widest dark:text-slate-500"
                        >Contact Name *</label
                    >
                    <input
                        v-model="form.name"
                        type="text"
                        class="dark:bg-slate-955 min-h-[48px] w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-slate-800 focus:outline-none dark:border-slate-800 dark:text-slate-100"
                        placeholder="Operations Manager"
                    />
                </div>
                <div class="space-y-1">
                    <label
                        class="text-slate-450 block text-[10px] font-black uppercase tracking-widest dark:text-slate-500"
                        >Role Label *</label
                    >
                    <input
                        v-model="form.role_label"
                        type="text"
                        class="dark:bg-slate-955 min-h-[48px] w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-slate-800 focus:outline-none dark:border-slate-800 dark:text-slate-100"
                        placeholder="Supervisor"
                    />
                </div>
                <div class="space-y-1">
                    <label
                        class="text-slate-450 block text-[10px] font-black uppercase tracking-widest dark:text-slate-500"
                        >Phone Number (SMS alerts)</label
                    >
                    <input
                        v-model="form.phone"
                        type="text"
                        class="dark:bg-slate-955 min-h-[48px] w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-slate-800 focus:outline-none dark:border-slate-800 dark:text-slate-100"
                        placeholder="+35799887766"
                    />
                </div>
                <div class="space-y-1">
                    <label
                        class="text-slate-450 block text-[10px] font-black uppercase tracking-widest dark:text-slate-500"
                        >Email Address</label
                    >
                    <input
                        v-model="form.email"
                        type="email"
                        class="dark:bg-slate-955 min-h-[48px] w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-slate-800 focus:outline-none dark:border-slate-800 dark:text-slate-100"
                        placeholder="supervisor@company.com"
                    />
                </div>

                <!-- Channels -->
                <div class="space-y-1.5">
                    <label
                        class="text-slate-450 block text-[10px] font-black uppercase tracking-widest dark:text-slate-500"
                        >Notification Channels *</label
                    >
                    <div class="flex flex-wrap gap-2">
                        <label
                            class="flex cursor-pointer items-center rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-800 dark:bg-slate-950/45"
                        >
                            <input
                                type="checkbox"
                                value="email"
                                v-model="form.notify_channels"
                                class="accent-indigo-650 h-4 w-4 rounded"
                            />
                            <span
                                class="text-slate-650 dark:text-slate-350 ms-2 text-xs font-bold"
                                >Email</span
                            >
                        </label>
                        <label
                            class="flex cursor-pointer items-center rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-800 dark:bg-slate-950/45"
                        >
                            <input
                                type="checkbox"
                                value="sms"
                                v-model="form.notify_channels"
                                class="accent-indigo-650 h-4 w-4 rounded"
                            />
                            <span
                                class="text-slate-655 dark:text-slate-355 ms-2 text-xs font-bold"
                                >SMS</span
                            >
                        </label>
                    </div>
                </div>

                <!-- Triggers -->
                <div class="space-y-1.5">
                    <label
                        class="text-slate-450 block text-[10px] font-black uppercase tracking-widest dark:text-slate-500"
                        >Alert On Trigger Events *</label
                    >
                    <div class="space-y-2">
                        <label
                            class="dark:bg-slate-955 flex cursor-pointer items-center rounded-xl border border-slate-200 bg-slate-50 p-2.5 dark:border-slate-800"
                        >
                            <input
                                type="checkbox"
                                value="sos_triggered"
                                v-model="form.notify_on"
                                class="accent-indigo-650 h-4 w-4 rounded"
                            />
                            <span
                                class="text-slate-650 ms-2 text-xs font-bold dark:text-slate-300"
                                >SOS Panic Triggered</span
                            >
                        </label>
                        <label
                            class="dark:bg-slate-955 flex cursor-pointer items-center rounded-xl border border-slate-200 bg-slate-50 p-2.5 dark:border-slate-800"
                        >
                            <input
                                type="checkbox"
                                value="patrol_completed"
                                v-model="form.notify_on"
                                class="accent-indigo-650 h-4 w-4 rounded"
                            />
                            <span
                                class="text-slate-650 ms-2 text-xs font-bold dark:text-slate-300"
                                >Patrol Completed</span
                            >
                        </label>
                        <label
                            class="dark:bg-slate-955 flex cursor-pointer items-center rounded-xl border border-slate-200 bg-slate-50 p-2.5 dark:border-slate-800"
                        >
                            <input
                                type="checkbox"
                                value="incident_created"
                                v-model="form.notify_on"
                                class="accent-indigo-650 h-4 w-4 rounded"
                            />
                            <span
                                class="text-slate-650 ms-2 text-xs font-bold dark:text-slate-300"
                                >Incident Reported</span
                            >
                        </label>
                    </div>
                </div>
            </div>

            <div
                class="border-slate-105 flex space-x-3 border-t pt-3 dark:border-slate-800"
            >
                <button
                    @click="$emit('close')"
                    class="dark:hover:bg-slate-750 dark:text-slate-350 min-h-[48px] flex-1 rounded-xl bg-slate-100 py-3 text-xs font-black uppercase tracking-wider text-slate-700 transition-all hover:bg-slate-200 dark:bg-slate-800"
                >
                    Cancel
                </button>
                <button
                    @click="handleSubmit"
                    class="to-purple-650 min-h-[48px] flex-1 rounded-xl bg-gradient-to-r from-indigo-500 py-3 text-xs font-black uppercase tracking-wider text-white shadow-md transition-all hover:from-indigo-600 hover:to-purple-700 active:scale-95"
                >
                    {{ contact ? 'Save Changes' : 'Add Contact' }}
                </button>
            </div>
        </div>
    </div>
</template>
