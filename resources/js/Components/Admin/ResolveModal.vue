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

watch(() => props.show, (newVal) => {
    if (newVal) {
        resolutionNote.value = '';
    }
});

function handleConfirm() {
    if (!resolutionNote.value.trim()) return;
    emit('confirm', resolutionNote.value);
}
</script>

<template>
    <div 
        v-if="show"
        class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-6 transition-all duration-300"
    >
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 w-full max-w-sm space-y-4 shadow-2xl transform transition-transform duration-300 scale-100">
            <h4 class="text-sm font-black uppercase tracking-widest text-slate-800 dark:text-slate-100 font-mono">
                Resolve {{ type === 'incident' ? 'Incident Report' : 'SOS Panic Alert' }}
            </h4>
            <p class="text-[11px] text-slate-500 dark:text-slate-450 leading-relaxed">
                Provide resolution notes. Once resolved, this status will update across all guard mobile terminals.
            </p>
            
            <textarea 
                v-model="resolutionNote"
                rows="4"
                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-xs p-3 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-none placeholder:text-slate-400 dark:placeholder:text-slate-600"
                placeholder="Enter corrective action details..."
            ></textarea>
            
            <div class="flex space-x-3 pt-2">
                <button 
                    @click="$emit('close')" 
                    class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-750 text-slate-700 dark:text-slate-350 text-xs font-black uppercase tracking-wider rounded-xl transition-all active:scale-95 min-h-[48px]"
                >
                    Cancel
                </button>
                <button 
                    @click="handleConfirm" 
                    :disabled="!resolutionNote.trim()" 
                    class="flex-1 py-3 bg-gradient-to-r from-indigo-500 to-purple-600 disabled:opacity-40 text-white text-xs font-black uppercase tracking-wider rounded-xl shadow-md transition-all active:scale-95 min-h-[48px]"
                >
                    Confirm
                </button>
            </div>
        </div>
    </div>
</template>
