<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, ref } from 'vue';

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
const usage = ref<Usage>({
    guards_count: 0,
    locations_count: 0,
    checkpoints_count: 0,
});
const isLoading = ref(true);

const form = ref({
    name: '',
    phone: '',
    email: '',
    address: '',
});
const isSaving = ref(false);
const successMessage = ref<string | null>(null);
const errorMessage = ref<string | null>(null);

async function fetchSubscriptionData() {
    try {
        const res = await axios.get('/admin/api/subscription');
        tenant.value = res.data.tenant;
        planDetails.value = res.data.plan_details;
        usage.value = res.data.usage;

        if (res.data.tenant) {
            form.value.name = res.data.tenant.name || '';
            form.value.phone = res.data.tenant.phone || '';
            form.value.email = res.data.tenant.email || '';
            form.value.address = res.data.tenant.address || '';
        }
    } catch (e) {
        console.error('Failed to load subscription data', e);
    } finally {
        isLoading.value = false;
    }
}

async function saveCompanySettings() {
    isSaving.value = true;
    successMessage.value = null;
    errorMessage.value = null;
    try {
        const res = await axios.put('/admin/api/company', form.value);
        successMessage.value = res.data.message;
        if (tenant.value) {
            tenant.value.name = res.data.tenant.name;
        }
    } catch (e: any) {
        errorMessage.value =
            e.response?.data?.message || 'Failed to save company settings.';
    } finally {
        isSaving.value = false;
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
    return d.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}
</script>

<template>
    <Head title="Subscription & Limits" />

    <AdminLayout title="Subscription & Plan Limits">
        <div
            v-if="isLoading"
            class="flex flex-col items-center justify-center gap-3 py-20 text-slate-400"
        >
            <div
                class="h-8 w-8 animate-spin rounded-full border-4 border-indigo-500/20 border-t-indigo-600"
            ></div>
            <span class="font-mono text-xs font-bold uppercase tracking-wider"
                >Loading plan usage...</span
            >
        </div>

        <div
            v-else-if="!tenant"
            class="border-indigo-150 text-indigo-750 flex max-w-4xl items-start gap-3 rounded-2xl border bg-indigo-50 p-6 text-xs font-medium shadow-sm"
        >
            <span class="text-base leading-none">ℹ️</span>
            <span
                >You are currently in <strong>All Companies</strong> mode.
                Please select a specific company context from the dropdown
                selector at the top to view its allocated resource quotas,
                active usage, and subscription billing history details.</span
            >
        </div>

        <div v-else-if="tenant && planDetails" class="max-w-4xl space-y-6">
            <!-- CURRENT PLAN HEADER CARD -->
            <div
                class="to-purple-955 relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 p-6 text-white shadow-xl md:p-8"
            >
                <div
                    class="absolute right-0 top-0 -mr-16 -mt-16 h-64 w-64 rounded-full bg-indigo-500/10 blur-3xl"
                ></div>
                <div
                    class="absolute bottom-0 left-1/3 h-48 w-48 rounded-full bg-purple-500/10 blur-2xl"
                ></div>

                <div
                    class="relative flex flex-col justify-between gap-6 md:flex-row md:items-center"
                >
                    <div class="space-y-2">
                        <span
                            class="rounded-full bg-indigo-500/20 px-3 py-1 font-mono text-[10px] font-black uppercase tracking-widest text-indigo-300"
                        >
                            Active Subscription
                        </span>
                        <h2
                            class="font-mono text-2xl font-black uppercase tracking-tight text-white md:text-3xl"
                        >
                            {{ planDetails.name }}
                        </h2>
                        <p class="text-xs font-medium text-slate-300">
                            Company context:
                            <span class="font-bold text-indigo-200">{{
                                tenant.name
                            }}</span>
                        </p>
                        <p class="font-mono text-[11px] text-slate-400">
                            Valid until:
                            <span class="font-semibold text-slate-200">{{
                                formatDate(tenant.subscription_until)
                            }}</span>
                        </p>
                    </div>

                    <div
                        class="rounded-2xl border border-white/10 bg-white/10 p-5 text-center shadow-sm backdrop-blur-md md:min-w-[180px]"
                    >
                        <span
                            class="block font-mono text-[9px] font-black uppercase tracking-wider text-indigo-200"
                            >Plan Monthly Cost</span
                        >
                        <span class="mt-1 block font-mono text-3xl font-black"
                            >€{{ planDetails.price_monthly }}</span
                        >
                        <span
                            class="mt-1 block text-[9px] font-semibold uppercase text-slate-300"
                            >billed monthly</span
                        >
                    </div>
                </div>
            </div>

            <!-- LIMITS PROGRESS SECTION -->
            <div
                class="space-y-6 rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm"
            >
                <div>
                    <h3
                        class="font-mono text-xs font-black uppercase tracking-widest text-slate-800"
                    >
                        Quota & Resource Allocation
                    </h3>
                    <p class="mt-0.5 text-[11px] text-slate-400">
                        Enforced system limitations for your subscription level.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <!-- GUARDS METRIC -->
                    <div
                        class="border-slate-150 flex flex-col justify-between space-y-4 rounded-2xl border bg-slate-50 p-5"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <span
                                    class="block font-mono text-[9px] font-black uppercase tracking-widest text-slate-400"
                                    >Active Guards</span
                                >
                                <span
                                    class="mt-1 block font-mono text-2xl font-black text-slate-800"
                                >
                                    {{ usage.guards_count }}
                                    <span
                                        class="text-slate-450 text-xs font-normal"
                                        >/
                                        {{
                                            planDetails.guards_limit >= 99999
                                                ? 'Unlimited'
                                                : planDetails.guards_limit
                                        }}</span
                                    >
                                </span>
                            </div>
                            <span class="text-lg">👮</span>
                        </div>
                        <div
                            class="space-y-1.5"
                            v-if="planDetails.guards_limit < 99999"
                        >
                            <div
                                class="h-2 w-full overflow-hidden rounded-full bg-slate-200"
                            >
                                <div
                                    class="h-full rounded-full bg-indigo-600 transition-all duration-500"
                                    :style="{
                                        width:
                                            calculatePercentage(
                                                usage.guards_count,
                                                planDetails.guards_limit,
                                            ) + '%',
                                    }"
                                ></div>
                            </div>
                            <span
                                class="block text-right font-mono text-[9px] font-bold text-slate-400"
                                >{{
                                    calculatePercentage(
                                        usage.guards_count,
                                        planDetails.guards_limit,
                                    ).toFixed(0)
                                }}% Used</span
                            >
                        </div>
                        <div
                            v-else
                            class="text-emerald-650 font-mono text-[9px] font-bold uppercase tracking-wider"
                        >
                            Unlimited guards allowed
                        </div>
                    </div>

                    <!-- LOCATIONS METRIC -->
                    <div
                        class="border-slate-150 flex flex-col justify-between space-y-4 rounded-2xl border bg-slate-50 p-5"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <span
                                    class="text-slate-440 block font-mono text-[9px] font-black uppercase tracking-widest"
                                    >Sites & Locations</span
                                >
                                <span
                                    class="mt-1 block font-mono text-2xl font-black text-slate-800"
                                >
                                    {{ usage.locations_count }}
                                    <span
                                        class="text-slate-450 text-xs font-normal"
                                        >/
                                        {{
                                            planDetails.locations_limit >= 99999
                                                ? 'Unlimited'
                                                : planDetails.locations_limit
                                        }}</span
                                    >
                                </span>
                            </div>
                            <span class="text-lg">🏢</span>
                        </div>
                        <div
                            class="space-y-1.5"
                            v-if="planDetails.locations_limit < 99999"
                        >
                            <div
                                class="h-2 w-full overflow-hidden rounded-full bg-slate-200"
                            >
                                <div
                                    class="bg-indigo-650 h-full rounded-full transition-all duration-500"
                                    :style="{
                                        width:
                                            calculatePercentage(
                                                usage.locations_count,
                                                planDetails.locations_limit,
                                            ) + '%',
                                    }"
                                ></div>
                            </div>
                            <span
                                class="block text-right font-mono text-[9px] font-bold text-slate-400"
                                >{{
                                    calculatePercentage(
                                        usage.locations_count,
                                        planDetails.locations_limit,
                                    ).toFixed(0)
                                }}% Used</span
                            >
                        </div>
                        <div
                            v-else
                            class="text-emerald-650 font-mono text-[9px] font-bold uppercase tracking-wider"
                        >
                            Unlimited locations allowed
                        </div>
                    </div>

                    <!-- CHECKPOINTS METRIC -->
                    <div
                        class="border-slate-150 flex flex-col justify-between space-y-4 rounded-2xl border bg-slate-50 p-5"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <span
                                    class="text-slate-440 block font-mono text-[9px] font-black uppercase tracking-widest"
                                    >QR / NFC Checkpoints</span
                                >
                                <span
                                    class="mt-1 block font-mono text-2xl font-black text-slate-800"
                                >
                                    {{ usage.checkpoints_count }}
                                    <span
                                        class="text-slate-455 text-xs font-normal"
                                        >/
                                        {{
                                            planDetails.checkpoints_limit >=
                                            99999
                                                ? 'Unlimited'
                                                : planDetails.checkpoints_limit
                                        }}</span
                                    >
                                </span>
                            </div>
                            <span class="text-lg">📍</span>
                        </div>
                        <div
                            class="space-y-1.5"
                            v-if="planDetails.checkpoints_limit < 99999"
                        >
                            <div
                                class="h-2 w-full overflow-hidden rounded-full bg-slate-200"
                            >
                                <div
                                    class="h-full rounded-full bg-indigo-600 transition-all duration-500"
                                    :style="{
                                        width:
                                            calculatePercentage(
                                                usage.checkpoints_count,
                                                planDetails.checkpoints_limit,
                                            ) + '%',
                                    }"
                                ></div>
                            </div>
                            <span
                                class="block text-right font-mono text-[9px] font-bold text-slate-400"
                                >{{
                                    calculatePercentage(
                                        usage.checkpoints_count,
                                        planDetails.checkpoints_limit,
                                    ).toFixed(0)
                                }}% Used</span
                            >
                        </div>
                        <div
                            v-else
                            class="text-emerald-650 font-mono text-[9px] font-bold uppercase tracking-wider"
                        >
                            Unlimited checkpoints allowed
                        </div>
                    </div>
                </div>
            </div>

            <!-- COMPANY PROFILE SETTINGS -->
            <div
                class="space-y-6 rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm"
            >
                <div>
                    <h3
                        class="font-mono text-xs font-black uppercase tracking-widest text-slate-800"
                    >
                        Company Profile Settings
                    </h3>
                    <p class="mt-0.5 text-[11px] text-slate-400">
                        Manage your organization's general information and
                        contact details.
                    </p>
                </div>

                <form @submit.prevent="saveCompanySettings" class="space-y-4">
                    <div
                        v-if="successMessage"
                        class="text-emerald-850 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs font-medium"
                    >
                        {{ successMessage }}
                    </div>
                    <div
                        v-if="errorMessage"
                        class="text-rose-850 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-medium"
                    >
                        {{ errorMessage }}
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-1.5">
                            <label
                                class="block pl-1 text-[10px] font-bold uppercase tracking-wider text-slate-500"
                                >Company Name</label
                            >
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                placeholder="E.g. SaaS Sentinel Corp"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label
                                class="block pl-1 text-[10px] font-bold uppercase tracking-wider text-slate-500"
                                >Contact Phone</label
                            >
                            <input
                                v-model="form.phone"
                                type="text"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                placeholder="E.g. +357 99 123456"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label
                                class="block pl-1 text-[10px] font-bold uppercase tracking-wider text-slate-500"
                                >Contact Email</label
                            >
                            <input
                                v-model="form.email"
                                type="email"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                placeholder="E.g. admin@sentinel.com"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label
                                class="block pl-1 text-[10px] font-bold uppercase tracking-wider text-slate-500"
                                >Office Address</label
                            >
                            <input
                                v-model="form.address"
                                type="text"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                placeholder="E.g. 123 Spyrou Kyprianou Ave, Limassol"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button
                            type="submit"
                            :disabled="isSaving"
                            class="hover:scale-102 active:scale-98 flex items-center space-x-2 rounded-xl bg-indigo-600 px-5 py-3 text-xs font-bold uppercase tracking-wider text-white shadow-md transition-all hover:bg-indigo-700"
                        >
                            <span
                                v-if="isSaving"
                                class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"
                            ></span>
                            <span>Save Settings</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- PLAN FEATURES INFO CAROUSEL/GRID -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <div
                    class="shadow-xs space-y-2 rounded-2xl border border-slate-200/80 bg-white p-5"
                >
                    <span class="text-sm font-bold text-indigo-600"
                        >✓ GPS Geofencing</span
                    >
                    <p class="text-[11px] leading-relaxed text-slate-500">
                        Real-time GPS validation on all checkpoints, preventing
                        off-site logging fraud.
                    </p>
                </div>
                <div
                    class="shadow-xs space-y-2 rounded-2xl border border-slate-200/80 bg-white p-5"
                >
                    <span class="text-sm font-bold text-indigo-600"
                        >✓ Photo & Voice Evidence</span
                    >
                    <p class="text-[11px] leading-relaxed text-slate-500">
                        Require guards to upload photographic or audio
                        validation on target checkpoints.
                    </p>
                </div>
                <div
                    class="shadow-xs space-y-2 rounded-2xl border border-slate-200/80 bg-white p-5"
                >
                    <span class="text-sm font-bold text-indigo-600"
                        >✓ Live Incident Tracking</span
                    >
                    <p class="text-[11px] leading-relaxed text-slate-500">
                        Receive instant notifications of incidents and emergency
                        SOS alerts triggered by guards.
                    </p>
                </div>
            </div>

            <!-- PLAN HISTORY & AUDIT LOGS -->
            <div
                class="space-y-6 rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm"
            >
                <div>
                    <h3
                        class="font-mono text-xs font-black uppercase tracking-widest text-slate-800"
                    >
                        Plan Billing & Upgrade History
                    </h3>
                    <p class="mt-0.5 font-sans text-[11px] text-slate-400">
                        Audit log of subscription modifications and quota limits
                        updates for your company.
                    </p>
                </div>

                <div
                    v-if="
                        !tenant.subscription_logs ||
                        tenant.subscription_logs.length === 0
                    "
                    class="text-slate-450 py-2 text-xs italic"
                >
                    No billing changes or subscription plan history has been
                    recorded yet.
                </div>
                <div
                    v-else
                    class="relative space-y-5 border-l-2 border-slate-200 pl-6"
                >
                    <div
                        v-for="log in tenant.subscription_logs"
                        :key="log.id"
                        class="relative"
                    >
                        <!-- bullet -->
                        <div
                            class="border-indigo-650 shadow-xs absolute -left-[31px] top-2 h-3.5 w-3.5 rounded-full border-2 bg-white"
                        ></div>
                        <div
                            class="border-slate-150 flex flex-col justify-between gap-4 rounded-2xl border bg-slate-50 p-5 md:flex-row md:items-center"
                        >
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="font-mono text-[10px] font-black uppercase tracking-wider text-slate-400"
                                        >Subscription Updated:</span
                                    >
                                    <span
                                        class="text-slate-650 rounded-md border border-slate-200 bg-slate-100 px-2.5 py-0.5 font-mono text-[9px] font-black uppercase tracking-wider"
                                    >
                                        {{ log.previous_plan.toUpperCase() }}
                                    </span>
                                    <span class="font-mono text-slate-400"
                                        >→</span
                                    >
                                    <span
                                        class="border-indigo-150 rounded-md border bg-indigo-50 px-2.5 py-0.5 font-mono text-[9px] font-black uppercase tracking-wider text-indigo-700"
                                    >
                                        {{ log.new_plan.toUpperCase() }}
                                    </span>
                                </div>
                                <div
                                    class="text-[11px] font-medium text-slate-500"
                                >
                                    <span>Plan Expiry: </span>
                                    <span class="font-mono text-slate-600">{{
                                        formatDate(log.previous_until)
                                    }}</span>
                                    <span class="font-mono text-slate-400">
                                        →
                                    </span>
                                    <span
                                        class="rounded-md border border-slate-200/60 bg-white px-1.5 py-0.5 font-mono font-bold text-slate-800"
                                        >{{ formatDate(log.new_until) }}</span
                                    >
                                </div>
                            </div>
                            <div
                                class="text-slate-450 space-y-0.5 text-left font-mono text-[10px] md:text-right"
                            >
                                <div>
                                    Updated by:
                                    <span class="text-slate-750 font-bold">{{
                                        log.operator?.name ||
                                        'System Administrator'
                                    }}</span>
                                </div>
                                <div>
                                    {{
                                        new Date(
                                            log.created_at,
                                        ).toLocaleString()
                                    }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
