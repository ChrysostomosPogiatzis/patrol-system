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
    pin: '1234',
});

watch(
    () => props.show,
    (newVal) => {
        if (newVal) {
            if (props.guard) {
                form.value = {
                    full_name: props.guard.full_name,
                    phone: props.guard.phone,
                    employee_id: props.guard.employee_id,
                    pin: '1234', // default representation meaning unchanged
                };
            } else {
                form.value = {
                    full_name: '',
                    phone: '',
                    employee_id: '',
                    pin: '',
                };
            }
        }
    },
);

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
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-6 backdrop-blur-sm transition-all duration-300"
    >
        <div
            class="w-full max-w-sm space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
        >
            <div
                class="flex items-center justify-between border-b border-slate-100 pb-2 dark:border-slate-800"
            >
                <h4
                    class="text-slate-850 font-mono text-sm font-black uppercase tracking-widest dark:text-slate-100"
                >
                    {{
                        guard
                            ? 'Edit Security Guard'
                            : 'Register Security Guard'
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
                        >Guard Full Name *</label
                    >
                    <input
                        v-model="form.full_name"
                        type="text"
                        class="min-h-[48px] w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-slate-800 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                        placeholder="John Doe"
                    />
                </div>
                <div class="space-y-1">
                    <label
                        class="text-slate-450 block text-[10px] font-black uppercase tracking-widest dark:text-slate-500"
                        >Phone Number (Login ID) *</label
                    >
                    <input
                        v-model="form.phone"
                        type="text"
                        class="min-h-[48px] w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-slate-800 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                        placeholder="e.g. +35799123456"
                    />
                </div>
                <div class="space-y-1">
                    <label
                        class="text-slate-450 block text-[10px] font-black uppercase tracking-widest dark:text-slate-500"
                        >Employee ID *</label
                    >
                    <input
                        v-model="form.employee_id"
                        type="text"
                        class="min-h-[48px] w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-slate-800 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                        placeholder="GD-007"
                    />
                </div>
                <div class="space-y-1">
                    <label
                        class="text-slate-450 block text-[10px] font-black uppercase tracking-widest dark:text-slate-500"
                    >
                        Pin Code (Offline Verification)
                        {{ guard ? '(Leave blank to keep current)' : '*' }}
                    </label>
                    <input
                        v-model="form.pin"
                        type="password"
                        class="min-h-[48px] w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-slate-800 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                        placeholder="••••"
                    />
                </div>
            </div>

            <div class="flex space-x-3 pt-3">
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
                    {{ guard ? 'Save Changes' : 'Register' }}
                </button>
            </div>
        </div>
    </div>
</template>
