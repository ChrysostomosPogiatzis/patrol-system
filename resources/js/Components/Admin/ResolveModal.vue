<script setup lang="ts">
import { ref, watch } from 'vue';

const props = defineProps<{
    show: boolean;
    type: 'incident' | 'sos' | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'confirm', note: string): void;
}>();

const resolutionNote = ref('');

watch(
    () => props.show,
    (newVal) => {
        if (newVal) {
            resolutionNote.value = '';
        }
    },
);

function handleConfirm() {
    if (!resolutionNote.value.trim()) return;
    emit('confirm', resolutionNote.value);
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-6 backdrop-blur-sm transition-all duration-300"
    >
        <div
            class="w-full max-w-sm scale-100 transform space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl transition-transform duration-300 dark:border-slate-800 dark:bg-slate-900"
        >
            <h4
                class="font-mono text-sm font-black uppercase tracking-widest text-slate-800 dark:text-slate-100"
            >
                Resolve
                {{
                    type === 'incident' ? 'Incident Report' : 'SOS Panic Alert'
                }}
            </h4>
            <p
                class="dark:text-slate-450 text-[11px] leading-relaxed text-slate-500"
            >
                Provide resolution notes. Once resolved, this status will update
                across all guard mobile terminals.
            </p>

            <textarea
                v-model="resolutionNote"
                rows="4"
                class="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-800 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-600"
                placeholder="Enter corrective action details..."
            ></textarea>

            <div class="flex space-x-3 pt-2">
                <button
                    @click="$emit('close')"
                    class="dark:hover:bg-slate-750 dark:text-slate-350 min-h-[48px] flex-1 rounded-xl bg-slate-100 py-3 text-xs font-black uppercase tracking-wider text-slate-700 transition-all hover:bg-slate-200 active:scale-95 dark:bg-slate-800"
                >
                    Cancel
                </button>
                <button
                    @click="handleConfirm"
                    :disabled="!resolutionNote.trim()"
                    class="min-h-[48px] flex-1 rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 py-3 text-xs font-black uppercase tracking-wider text-white shadow-md transition-all active:scale-95 disabled:opacity-40"
                >
                    Confirm
                </button>
            </div>
        </div>
    </div>
</template>
