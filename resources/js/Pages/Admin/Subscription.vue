<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';

interface PlanDetails {
    name: string;
    guards_limit: number;
    locations_limit: number;
    checkpoints_limit: number;
    price_monthly: number;
}

interface SubscriptionLog {
    id: number;
    tenant_id: number;
    changed_by: number;
    previous_plan: string;
    new_plan: string;
    previous_until: string | null;
    new_until: string | null;
    created_at: string;
    operator?: {
        id: number;
        name: string;
        email: string;
    };
}

interface Tenant {
    id: number;
    name: string;
    subscription_plan: string;
    subscription_until: string | null;
    subscription_logs?: SubscriptionLog[];
}

interface Usage {
    guards_count: number;
    locations_count: number;
    checkpoints_count: number;
}

const tenant = ref<Tenant | null>(null);
const planDetails = ref<PlanDetails | null>(null);
const usage = ref<Usage>({ guards_count: 0, locations_count: 0, checkpoints_count: 0 });
const isLoading = ref(true);

async function fetchSubscriptionData() {
    try {
        const res = await axios.get('/admin/api/subscription');
        tenant.value = res.data.tenant;
        planDetails.value = res.data.plan_details;
        usage.value = res.data.usage;
    } catch (e) {
        console.error('Failed to load subscription data', e);
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    fetchSubscriptionData();
});

function calculatePercentage(current: number, max: number): number {
    if (max >= 99999) return 0;
    return Math.min(100, Math.max(0, (current / max) * 100));
}

function formatDate(dateStr: string | null): string {
    if (!dateStr) return 'Lifetime / Permanent';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}
</script>

<template>
    <Head title="Subscription & Limits" />

    <AdminLayout title="Subscription & Plan Limits">
        <div v-if="isLoading" class="flex flex-col items-center justify-center py-20 text-slate-400 gap-3">
            <div class="w-8 h-8 border-4 border-indigo-500/20 border-t-indigo-600 rounded-full animate-spin"></div>
            <span class="text-xs font-bold font-mono uppercase tracking-wider">Loading plan usage...</span>
        </div>

        <div v-else-if="!tenant" class="bg-indigo-50 border border-indigo-150 p-6 rounded-2xl flex items-start gap-3 text-xs text-indigo-750 font-medium max-w-4xl shadow-sm">
            <span class="text-base leading-none">ℹ️</span>
            <span>You are currently in <strong>All Companies</strong> mode. Please select a specific company context from the dropdown selector at the top to view its allocated resource quotas, active usage, and subscription billing history details.</span>
        </div>

        <div v-else-if="tenant && planDetails" class="space-y-6 max-w-4xl">
            <!-- CURRENT PLAN HEADER CARD -->
            <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-purple-955 text-white rounded-3xl p-6 md:p-8 shadow-xl relative overflow-hidden">
                <div class="absolute right-0 top-0 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl -mr-16 -mt-16"></div>
                <div class="absolute left-1/3 bottom-0 w-48 h-48 bg-purple-500/10 rounded-full blur-2xl"></div>

                <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="space-y-2">
                        <span class="text-[10px] font-black uppercase tracking-widest text-indigo-300 font-mono bg-indigo-500/20 px-3 py-1 rounded-full">
                            Active Subscription
                        </span>
                        <h2 class="text-2xl md:text-3xl font-black font-mono tracking-tight text-white uppercase">
                            {{ planDetails.name }}
                        </h2>
                        <p class="text-xs text-slate-300 font-medium">
                            Company context: <span class="text-indigo-200 font-bold">{{ tenant.name }}</span>
                        </p>
                        <p class="text-[11px] text-slate-400 font-mono">
                            Valid until: <span class="text-slate-200 font-semibold">{{ formatDate(tenant.subscription_until) }}</span>
                        </p>
                    </div>

                    <div class="bg-white/10 backdrop-blur-md border border-white/10 p-5 rounded-2xl text-center md:min-w-[180px] shadow-sm">
                        <span class="block text-[9px] font-black uppercase tracking-wider text-indigo-200 font-mono">Plan Monthly Cost</span>
                        <span class="block text-3xl font-black font-mono mt-1">€{{ planDetails.price_monthly }}</span>
                        <span class="block text-[9px] text-slate-300 mt-1 uppercase font-semibold">billed monthly</span>
                    </div>
                </div>
            </div>

            <!-- LIMITS PROGRESS SECTION -->
            <div class="bg-white border border-slate-200/80 rounded-3xl p-6 shadow-sm space-y-6">
                <div>
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-800 font-mono">Quota & Resource Allocation</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">Enforced system limitations for your subscription level.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- GUARDS METRIC -->
                    <div class="bg-slate-50 border border-slate-150 p-5 rounded-2xl flex flex-col justify-between space-y-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="block text-[9px] font-black uppercase tracking-widest text-slate-400 font-mono">Active Guards</span>
                                <span class="text-2xl font-black font-mono text-slate-800 mt-1 block">
                                    {{ usage.guards_count }}
                                    <span class="text-xs text-slate-450 font-normal">/ {{ planDetails.guards_limit >= 99999 ? 'Unlimited' : planDetails.guards_limit }}</span>
                                </span>
                            </div>
                            <span class="text-lg">👮</span>
                        </div>
                        <div class="space-y-1.5" v-if="planDetails.guards_limit < 99999">
                            <div class="w-full bg-slate-200 h-2 rounded-full overflow-hidden">
                                <div class="bg-indigo-600 h-full rounded-full transition-all duration-500" :style="{ width: calculatePercentage(usage.guards_count, planDetails.guards_limit) + '%' }"></div>
                            </div>
                            <span class="text-[9px] font-mono text-slate-400 block text-right font-bold">{{ calculatePercentage(usage.guards_count, planDetails.guards_limit).toFixed(0) }}% Used</span>
                        </div>
                        <div v-else class="text-[9px] font-mono text-emerald-650 font-bold uppercase tracking-wider">
                            Unlimited guards allowed
                        </div>
                    </div>

                    <!-- LOCATIONS METRIC -->
                    <div class="bg-slate-50 border border-slate-150 p-5 rounded-2xl flex flex-col justify-between space-y-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="block text-[9px] font-black uppercase tracking-widest text-slate-440 font-mono">Sites & Locations</span>
                                <span class="text-2xl font-black font-mono text-slate-800 mt-1 block">
                                    {{ usage.locations_count }}
                                    <span class="text-xs text-slate-450 font-normal">/ {{ planDetails.locations_limit >= 99999 ? 'Unlimited' : planDetails.locations_limit }}</span>
                                </span>
                            </div>
                            <span class="text-lg">🏢</span>
                        </div>
                        <div class="space-y-1.5" v-if="planDetails.locations_limit < 99999">
                            <div class="w-full bg-slate-200 h-2 rounded-full overflow-hidden">
                                <div class="bg-indigo-650 h-full rounded-full transition-all duration-500" :style="{ width: calculatePercentage(usage.locations_count, planDetails.locations_limit) + '%' }"></div>
                            </div>
                            <span class="text-[9px] font-mono text-slate-400 block text-right font-bold">{{ calculatePercentage(usage.locations_count, planDetails.locations_limit).toFixed(0) }}% Used</span>
                        </div>
                        <div v-else class="text-[9px] font-mono text-emerald-650 font-bold uppercase tracking-wider">
                            Unlimited locations allowed
                        </div>
                    </div>

                    <!-- CHECKPOINTS METRIC -->
                    <div class="bg-slate-50 border border-slate-150 p-5 rounded-2xl flex flex-col justify-between space-y-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="block text-[9px] font-black uppercase tracking-widest text-slate-440 font-mono">QR / NFC Checkpoints</span>
                                <span class="text-2xl font-black font-mono text-slate-800 mt-1 block">
                                    {{ usage.checkpoints_count }}
                                    <span class="text-xs text-slate-455 font-normal">/ {{ planDetails.checkpoints_limit >= 99999 ? 'Unlimited' : planDetails.checkpoints_limit }}</span>
                                </span>
                            </div>
                            <span class="text-lg">📍</span>
                        </div>
                        <div class="space-y-1.5" v-if="planDetails.checkpoints_limit < 99999">
                            <div class="w-full bg-slate-200 h-2 rounded-full overflow-hidden">
                                <div class="bg-indigo-600 h-full rounded-full transition-all duration-500" :style="{ width: calculatePercentage(usage.checkpoints_count, planDetails.checkpoints_limit) + '%' }"></div>
                            </div>
                            <span class="text-[9px] font-mono text-slate-400 block text-right font-bold">{{ calculatePercentage(usage.checkpoints_count, planDetails.checkpoints_limit).toFixed(0) }}% Used</span>
                        </div>
                        <div v-else class="text-[9px] font-mono text-emerald-650 font-bold uppercase tracking-wider">
                            Unlimited checkpoints allowed
                        </div>
                    </div>
                </div>
            </div>
                       <!-- PLAN FEATURES INFO CAROUSEL/GRID -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs space-y-2">
                    <span class="text-indigo-600 font-bold text-sm">✓ GPS Geofencing</span>
                    <p class="text-[11px] text-slate-500 leading-relaxed">Real-time GPS validation on all checkpoints, preventing off-site logging fraud.</p>
                </div>
                <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs space-y-2">
                    <span class="text-indigo-600 font-bold text-sm">✓ Photo & Voice Evidence</span>
                    <p class="text-[11px] text-slate-500 leading-relaxed">Require guards to upload photographic or audio validation on target checkpoints.</p>
                </div>
                <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs space-y-2">
                    <span class="text-indigo-600 font-bold text-sm">✓ Live Incident Tracking</span>
                    <p class="text-[11px] text-slate-500 leading-relaxed">Receive instant notifications of incidents and emergency SOS alerts triggered by guards.</p>
                </div>
            </div>

            <!-- PLAN HISTORY & AUDIT LOGS -->
            <div class="bg-white border border-slate-200/80 rounded-3xl p-6 shadow-sm space-y-6">
                <div>
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-800 font-mono">Plan Billing & Upgrade History</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5 font-sans">Audit log of subscription modifications and quota limits updates for your company.</p>
                </div>

                <div v-if="!tenant.subscription_logs || tenant.subscription_logs.length === 0" class="text-xs text-slate-450 italic py-2">
                    No billing changes or subscription plan history has been recorded yet.
                </div>
                <div v-else class="relative pl-6 border-l-2 border-slate-200 space-y-5">
                    <div v-for="log in tenant.subscription_logs" :key="log.id" class="relative">
                        <!-- bullet -->
                        <div class="absolute -left-[31px] top-2 w-3.5 h-3.5 rounded-full border-2 border-indigo-650 bg-white shadow-xs"></div>
                        <div class="bg-slate-50 border border-slate-150 rounded-2xl p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-[10px] font-mono font-black uppercase tracking-wider text-slate-400">Subscription Updated:</span>
                                    <span class="px-2.5 py-0.5 rounded-md border text-[9px] font-mono font-black uppercase tracking-wider bg-slate-100 text-slate-650 border-slate-200">
                                        {{ log.previous_plan.toUpperCase() }}
                                    </span>
                                    <span class="text-slate-400 font-mono">→</span>
                                    <span class="px-2.5 py-0.5 rounded-md border text-[9px] font-mono font-black uppercase tracking-wider bg-indigo-50 text-indigo-700 border-indigo-150">
                                        {{ log.new_plan.toUpperCase() }}
                                    </span>
                                </div>
                                <div class="text-[11px] text-slate-500 font-medium">
                                    <span>Plan Expiry: </span>
                                    <span class="font-mono text-slate-600">{{ formatDate(log.previous_until) }}</span>
                                    <span class="text-slate-400 font-mono"> → </span>
                                    <span class="font-mono font-bold text-slate-800 bg-white border border-slate-200/60 px-1.5 py-0.5 rounded-md">{{ formatDate(log.new_until) }}</span>
                                </div>
                            </div>
                            <div class="text-left md:text-right text-[10px] font-mono text-slate-450 space-y-0.5">
                                <div>Updated by: <span class="font-bold text-slate-750">{{ log.operator?.name || 'System Administrator' }}</span></div>
                                <div>{{ new Date(log.created_at).toLocaleString() }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
