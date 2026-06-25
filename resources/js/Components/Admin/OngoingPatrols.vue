<script setup lang="ts">
import { ref } from 'vue';

interface Guard {
    id: number;
    full_name: string;
    phone: string;
}

interface Route {
    id: number;
    name: string;
}

interface Patrol {
    id: number;
    started_at: string;
    status: string;
    total_checkpoints: number;
    completed_checkpoints: number;
    route?: Route;
    security_guard?: Guard;
}

defineProps<{
    patrols: Patrol[];
}>();

const expandedPatrolId = ref<number | null>(null);

function toggleExpand(id: number) {
    if (expandedPatrolId.value === id) {
        expandedPatrolId.value = null;
    } else {
        expandedPatrolId.value = id;
    }
}

function formatTime(timeStr: string) {
    if (!timeStr) return '';
    try {
        return new Date(timeStr).toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch (e) {
        return timeStr;
    }
}
</script>

<template>
    <div
        class="space-y-4 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"
    >
        <div
            class="flex items-center justify-between border-b border-slate-100 pb-2"
        >
            <h3
                class="text-xs font-black uppercase tracking-widest text-slate-500"
            >
                Ongoing Patrol Shifts
            </h3>
            <span
                class="rounded-full bg-indigo-50 px-2 py-0.5 font-mono text-[9px] font-bold text-indigo-600"
            >
                LIVE
            </span>
        </div>

        <div
            v-if="patrols.length === 0"
            class="border-slate-150 rounded-2xl border-2 border-dashed py-12 text-center"
        >
            <div
                class="mx-auto mb-2.5 flex h-10 w-10 items-center justify-center rounded-full bg-slate-50"
            >
                <svg
                    class="h-5 w-5 text-slate-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                    />
                </svg>
            </div>
            <p class="text-slate-455 text-xs font-medium">
                No active patrols are running right now.
            </p>
        </div>

        <div v-else class="max-h-[500px] space-y-4 overflow-y-auto pr-1">
            <div
                v-for="patrol in patrols"
                :key="patrol.id"
                class="border-slate-150 group cursor-pointer space-y-3 rounded-xl border bg-slate-50 p-4 transition-all duration-200 hover:border-indigo-500/30"
                @click="toggleExpand(patrol.id)"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <h4
                            class="flex items-center gap-1.5 text-xs font-bold text-slate-800 transition-colors group-hover:text-indigo-600"
                        >
                            <span>{{ patrol.route?.name }}</span>
                            <span
                                v-if="(patrol as any).tenant"
                                class="text-purple-650 rounded border border-purple-200 bg-purple-50 px-1 py-0.5 text-[8px] font-black uppercase tracking-wider"
                            >
                                {{ (patrol as any).tenant.name }}
                            </span>
                        </h4>
                        <span
                            class="mt-0.5 block font-mono text-[9px] text-slate-400"
                        >
                            Started: {{ formatTime(patrol.started_at) }}
                        </span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span
                            class="text-indigo-650 rounded border border-indigo-100/60 bg-indigo-50 px-2 py-0.5 font-mono text-[8px] font-black uppercase tracking-wider"
                        >
                            {{ patrol.status.replace('_', ' ') }}
                        </span>
                        <!-- Chevron icon -->
                        <svg
                            class="h-3.5 w-3.5 text-slate-400 transition-transform duration-200"
                            :class="
                                expandedPatrolId === patrol.id
                                    ? 'rotate-180'
                                    : ''
                            "
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2.5"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </div>
                </div>

                <div
                    class="flex items-center justify-between text-[10px] text-slate-500"
                >
                    <span class="font-medium">
                        Guard:
                        <strong class="text-slate-700">{{
                            patrol.security_guard?.full_name
                        }}</strong>
                    </span>
                    <span class="font-mono font-bold text-indigo-600">
                        {{ patrol.completed_checkpoints }}/{{
                            patrol.total_checkpoints
                        }}
                        Checked
                    </span>
                </div>

                <div
                    class="bg-slate-150 h-2 w-full overflow-hidden rounded-full"
                >
                    <div
                        class="to-purple-650 h-full rounded-full bg-gradient-to-r from-indigo-500 transition-all duration-500"
                        :style="{
                            width: `${(patrol.completed_checkpoints / patrol.total_checkpoints) * 100}%`,
                        }"
                    ></div>
                </div>

                <!-- Expanded Checkpoint Logs Progress -->
                <div
                    v-if="expandedPatrolId === patrol.id"
                    class="mt-4 cursor-default space-y-3 border-t border-slate-200/80 pt-4"
                    @click.stop
                >
                    <h5
                        class="text-slate-455 font-mono text-[9px] font-black uppercase tracking-widest"
                    >
                        Shift Checkpoints Progress
                    </h5>

                    <div class="max-h-[260px] space-y-2 overflow-y-auto pr-1">
                        <div
                            v-for="log in (patrol as any).checkpoint_logs"
                            :key="log.id"
                            class="border-slate-150 space-y-2 rounded-xl border bg-white p-3 text-[11px]"
                        >
                            <div class="flex items-start justify-between">
                                <div>
                                    <span class="font-bold text-slate-800">{{
                                        log.checkpoint?.name
                                    }}</span>
                                    <span
                                        class="block font-mono text-[9px] text-slate-400"
                                    >
                                        Position #{{ log.position }} • Method:
                                        {{
                                            log.checkpoint?.scan_method || 'QR'
                                        }}
                                    </span>
                                </div>
                                <span
                                    class="rounded px-2 py-0.5 font-mono text-[8px] font-black uppercase tracking-wider"
                                    :class="{
                                        'text-emerald-650 border border-emerald-200 bg-emerald-50':
                                            log.status === 'scanned',
                                        'text-amber-650 border border-amber-200 bg-amber-50':
                                            log.status === 'skipped',
                                        'border border-slate-200 bg-slate-50 text-slate-500':
                                            log.status === 'pending',
                                    }"
                                >
                                    {{ log.status }}
                                </span>
                            </div>

                            <!-- Geofence Breach Warning Badge -->
                            <div
                                v-if="
                                    log.status === 'scanned' &&
                                    log.gps_within_fence === false
                                "
                                class="text-red-650 flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-[9px] font-bold"
                            >
                                <span
                                    >⚠️ Geofence Breach: Scanned outside
                                    boundary</span
                                >
                                <span
                                    v-if="
                                        log.gps_distance_metres !== null &&
                                        log.gps_distance_metres !== undefined
                                    "
                                    class="font-mono"
                                >
                                    ({{ Math.round(log.gps_distance_metres) }}m
                                    away)
                                </span>
                            </div>

                            <!-- Scanned / Skipped Details -->
                            <div
                                v-if="
                                    log.status === 'scanned' ||
                                    log.status === 'skipped'
                                "
                                class="space-y-2 border-t border-slate-100 pt-2 text-[10px] text-slate-600"
                            >
                                <div
                                    class="flex justify-between font-mono text-[9px] text-slate-400"
                                >
                                    <span
                                        >Time:
                                        {{
                                            formatTime(
                                                log.scanned_at ||
                                                    log.skipped_at ||
                                                    log.updated_at ||
                                                    log.created_at,
                                            )
                                        }}</span
                                    >
                                    <span
                                        v-if="log.recorded_offline"
                                        class="text-amber-600"
                                        >Recorded Offline</span
                                    >
                                </div>

                                <p
                                    v-if="log.note || log.skip_reason"
                                    class="border-slate-150/60 rounded border bg-slate-50 p-2 italic leading-relaxed text-slate-700"
                                >
                                    <span
                                        class="mb-0.5 block font-mono text-[8px] font-black uppercase not-italic text-slate-400"
                                    >
                                        {{
                                            log.status === 'scanned'
                                                ? 'Guard Note'
                                                : 'Skip Reason'
                                        }}
                                    </span>
                                    "{{ log.note || log.skip_reason }}"
                                </p>

                                <!-- Attached photos -->
                                <div
                                    v-if="
                                        log.media &&
                                        log.media.filter(
                                            (m: any) => m.kind !== 'signature',
                                        ).length > 0
                                    "
                                    class="space-y-1.5"
                                >
                                    <span
                                        class="block font-mono text-[8px] font-black uppercase text-slate-400"
                                        >Attached Evidence</span
                                    >
                                    <div class="flex flex-wrap gap-2">
                                        <a
                                            v-for="m in log.media.filter(
                                                (m: any) =>
                                                    m.kind !== 'signature',
                                            )"
                                            :key="m.id"
                                            :href="m.file_url"
                                            target="_blank"
                                            class="shadow-xs relative block h-14 w-14 overflow-hidden rounded-lg border border-slate-200 transition-colors hover:border-indigo-500"
                                        >
                                            <img
                                                :src="m.file_url"
                                                class="h-full w-full object-cover"
                                            />
                                        </a>
                                    </div>
                                </div>

                                <!-- Checkpoint Signature -->
                                <div
                                    v-if="
                                        log.media &&
                                        log.media.find(
                                            (m: any) => m.kind === 'signature',
                                        )
                                    "
                                    class="space-y-1.5"
                                >
                                    <span
                                        class="block font-mono text-[8px] font-black uppercase text-slate-400"
                                        >Checkpoint Signature</span
                                    >
                                    <div class="flex">
                                        <a
                                            :href="
                                                log.media.find(
                                                    (m: any) =>
                                                        m.kind === 'signature',
                                                )?.file_url
                                            "
                                            target="_blank"
                                            class="border-slate-150 shadow-xs inline-block overflow-hidden rounded-lg border bg-white p-1 transition-colors hover:border-indigo-500"
                                        >
                                            <img
                                                :src="
                                                    log.media.find(
                                                        (m: any) =>
                                                            m.kind ===
                                                            'signature',
                                                    )?.file_url
                                                "
                                                class="max-h-[36px] max-w-[120px] object-contain"
                                            />
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
