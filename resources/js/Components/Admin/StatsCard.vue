<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        title: string;
        value: string | number;
        color?: 'indigo' | 'emerald' | 'amber' | 'sky' | 'rose';
    }>(),
    {
        color: 'indigo',
    },
);

const colorClasses = computed(() => {
    switch (props.color) {
        case 'emerald':
            return {
                bg: 'bg-emerald-500/10 dark:bg-emerald-500/10',
                text: 'text-emerald-500 dark:text-emerald-400',
                border: 'border-emerald-500/20 dark:border-emerald-500/30',
                gradient: 'from-emerald-500 to-teal-600',
                glow: 'shadow-emerald-500/10',
            };
        case 'amber':
            return {
                bg: 'bg-amber-500/10 dark:bg-amber-500/10',
                text: 'text-amber-500 dark:text-amber-400',
                border: 'border-amber-500/20 dark:border-amber-500/30',
                gradient: 'from-amber-500 to-orange-600',
                glow: 'shadow-amber-500/10',
            };
        case 'sky':
            return {
                bg: 'bg-sky-500/10 dark:bg-sky-500/10',
                text: 'text-sky-500 dark:text-sky-400',
                border: 'border-sky-500/20 dark:border-sky-500/30',
                gradient: 'from-sky-500 to-indigo-600',
                glow: 'shadow-sky-500/10',
            };
        case 'rose':
            return {
                bg: 'bg-rose-500/10 dark:bg-rose-500/10',
                text: 'text-rose-500 dark:text-rose-400',
                border: 'border-rose-500/20 dark:border-rose-500/30',
                gradient: 'from-rose-500 to-red-600',
                glow: 'shadow-rose-500/10',
            };
        case 'indigo':
        default:
            return {
                bg: 'bg-indigo-500/10 dark:bg-indigo-500/10',
                text: 'text-indigo-500 dark:text-indigo-400',
                border: 'border-indigo-500/20 dark:border-indigo-500/30',
                gradient: 'from-indigo-500 to-purple-600',
                glow: 'shadow-indigo-500/10',
            };
    }
});
</script>

<template>
    <div
        class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm transition-all duration-300 hover:scale-[1.02] hover:shadow-md"
        :class="colorClasses.glow"
    >
        <div class="space-y-1">
            <span
                class="text-slate-450 block text-[10px] font-black uppercase tracking-widest"
            >
                {{ title }}
            </span>
            <span
                class="font-mono text-3xl font-black leading-none text-slate-800"
            >
                {{ value }}
            </span>
        </div>
        <div
            class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-md"
            :class="colorClasses.gradient"
        >
            <slot />
        </div>
    </div>
</template>
