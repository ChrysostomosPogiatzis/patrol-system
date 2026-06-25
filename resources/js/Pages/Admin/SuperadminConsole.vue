<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, ref } from 'vue';

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
    slug: string;
    subscription_plan: string;
    subscription_until: string | null;
    guards_count: number;
    locations_count: number;
    checkpoints_count: number;
    subscription_logs?: SubscriptionLog[];
}

interface SubscriptionPlan {
    id: number;
    plan_key: string;
    name: string;
    guards_limit: number;
    locations_limit: number;
    checkpoints_limit: number;
    price_monthly: number;
}

const activeTab = ref<'accounts' | 'packages'>('accounts');
const tenants = ref<Tenant[]>([]);
const availablePlans = ref<any>({});
const isLoading = ref(true);

// Packages management state
const packages = ref<SubscriptionPlan[]>([]);
const isFetchingPackages = ref(false);
const editingPackage = ref<SubscriptionPlan | null>(null);

const formPkgName = ref('');
const formPkgPrice = ref(0);
const formPkgGuards = ref(0);
const formPkgLocations = ref(0);
const formPkgCheckpoints = ref(0);
const isSavingPackage = ref(false);

// Expandable logs state
const expandedTenantIds = ref<number[]>([]);

function toggleHistory(id: number) {
    if (expandedTenantIds.value.includes(id)) {
        expandedTenantIds.value = expandedTenantIds.value.filter(
            (tId) => tId !== id,
        );
    } else {
        expandedTenantIds.value.push(id);
    }
}

// Edit plan state
const selectedTenant = ref<Tenant | null>(null);
const formPlan = ref('basic');
const formUntil = ref('');
const isSubmitting = ref(false);

async function fetchTenants() {
    isLoading.value = true;
    try {
        const res = await axios.get('/admin/api/superadmin/tenants');
        tenants.value = res.data.tenants || [];
        availablePlans.value = res.data.available_plans || {};
    } catch (e) {
        console.error('Failed to fetch tenants', e);
    } finally {
        isLoading.value = false;
    }
}

async function fetchPackages() {
    isFetchingPackages.value = true;
    try {
        const res = await axios.get('/admin/api/superadmin/plans');
        packages.value = res.data.plans || [];
    } catch (e) {
        console.error('Failed to fetch packages', e);
    } finally {
        isFetchingPackages.value = false;
    }
}

onMounted(() => {
    fetchTenants();
    fetchPackages();
});

function openPlanModal(tenant: Tenant) {
    selectedTenant.value = tenant;
    formPlan.value = tenant.subscription_plan || 'basic';
    formUntil.value = tenant.subscription_until
        ? tenant.subscription_until.substring(0, 10)
        : '';
}

async function submitPlanChange() {
    if (!selectedTenant.value) return;
    isSubmitting.value = true;
    try {
        await axios.put(
            `/admin/api/superadmin/tenants/${selectedTenant.value.id}/plan`,
            {
                subscription_plan: formPlan.value,
                subscription_until: formUntil.value || null,
            },
        );
        selectedTenant.value = null;
        fetchTenants();
    } catch (e) {
        alert('Failed to update subscription plan.');
    } finally {
        isSubmitting.value = false;
    }
}

function openEditPackage(pkg: SubscriptionPlan) {
    editingPackage.value = pkg;
    formPkgName.value = pkg.name;
    formPkgPrice.value = pkg.price_monthly;
    formPkgGuards.value = pkg.guards_limit;
    formPkgLocations.value = pkg.locations_limit;
    formPkgCheckpoints.value = pkg.checkpoints_limit;
}

async function submitPackageChange() {
    if (!editingPackage.value) return;
    isSavingPackage.value = true;
    try {
        await axios.put(
            `/admin/api/superadmin/plans/${editingPackage.value.id}`,
            {
                name: formPkgName.value,
                price_monthly: formPkgPrice.value,
                guards_limit: formPkgGuards.value,
                locations_limit: formPkgLocations.value,
                checkpoints_limit: formPkgCheckpoints.value,
            },
        );
        editingPackage.value = null;
        fetchPackages();
        fetchTenants();
    } catch (e) {
        alert('Failed to update package details.');
    } finally {
        isSavingPackage.value = false;
    }
}

function getPlanPrice(planKey: string): number {
    return availablePlans.value[planKey]?.price_monthly ?? 0;
}

function formatDate(dateStr: string | null): string {
    if (!dateStr) return 'Lifetime / Permanent';
    const d = new Date(dateStr);
    return d.toLocaleDateString();
}
</script>

<template>
    <Head title="Superadmin Billing Console" />

    <AdminLayout title="Superadmin Billing Console">
        <div
            v-if="isLoading"
            class="text-slate-450 flex flex-col items-center justify-center gap-3 py-20"
        >
            <div
                class="h-8 w-8 animate-spin rounded-full border-4 border-indigo-500/20 border-t-indigo-600"
            ></div>
            <span class="font-mono text-xs font-bold uppercase tracking-wider"
                >Fetching billing accounts...</span
            >
        </div>

        <div v-else class="space-y-6">
            <!-- HEADER WITH TABS -->
            <div
                class="flex flex-col items-start justify-between gap-4 border-b border-slate-200 pb-3 md:flex-row md:items-center"
            >
                <div>
                    <h3
                        class="font-mono text-xs font-black uppercase tracking-widest text-slate-800"
                    >
                        Global Billing & Subscription Accounts
                    </h3>
                    <p class="mt-0.5 text-[11px] text-slate-400">
                        Control active plans, monthly billing limits, and
                        pricing packages.
                    </p>
                </div>
                <!-- Tabs switcher -->
                <div
                    class="border-slate-350/40 flex rounded-2xl border bg-slate-200/60 p-1"
                >
                    <button
                        @click="activeTab = 'accounts'"
                        class="rounded-xl px-4 py-2 font-mono text-[10px] font-black uppercase tracking-widest transition-all"
                        :class="
                            activeTab === 'accounts'
                                ? 'bg-white text-slate-800 shadow-sm'
                                : 'text-slate-500 hover:text-slate-800'
                        "
                    >
                        Company Accounts
                    </button>
                    <button
                        @click="activeTab = 'packages'"
                        class="rounded-xl px-4 py-2 font-mono text-[10px] font-black uppercase tracking-widest transition-all"
                        :class="
                            activeTab === 'packages'
                                ? 'bg-white text-slate-800 shadow-sm'
                                : 'text-slate-500 hover:text-slate-800'
                        "
                    >
                        Pricing Packages
                    </button>
                </div>
            </div>

            <!-- ACCOUNTS VIEW -->
            <div v-if="activeTab === 'accounts'" class="space-y-6">
                <!-- ACCOUNTS LIST -->
                <div
                    class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm"
                >
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr
                                class="border-b border-slate-200 bg-slate-50 font-mono text-[10px] font-black uppercase tracking-widest text-slate-400"
                            >
                                <th class="p-4 pl-6">Company Account</th>
                                <th class="p-4">Subscription Plan</th>
                                <th class="p-4">Monthly Rate</th>
                                <th class="p-4">Usage Counts</th>
                                <th class="p-4">Valid Until</th>
                                <th class="p-4 pr-6 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            <template v-for="t in tenants" :key="t.id">
                                <tr
                                    class="transition-colors hover:bg-slate-50/50"
                                >
                                    <td class="p-4 pl-6">
                                        <div class="font-bold text-slate-800">
                                            {{ t.name }}
                                        </div>
                                        <div
                                            class="mt-0.5 font-mono text-[10px] text-slate-400"
                                        >
                                            slug: {{ t.slug }}
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <span
                                            class="text-indigo-650 border-indigo-150 rounded-lg border bg-indigo-50 px-2.5 py-1 font-mono text-[10px] font-black uppercase tracking-wider"
                                        >
                                            {{
                                                availablePlans[
                                                    t.subscription_plan
                                                ]?.name || t.subscription_plan
                                            }}
                                        </span>
                                    </td>
                                    <td
                                        class="p-4 font-mono font-bold text-slate-700"
                                    >
                                        €{{ getPlanPrice(t.subscription_plan) }}
                                    </td>
                                    <td class="p-4">
                                        <div
                                            class="text-slate-505 flex items-center gap-3 font-mono text-[10px] font-bold uppercase"
                                        >
                                            <span
                                                >👮
                                                {{ t.guards_count }}
                                                guards</span
                                            >
                                            <span
                                                >🏢
                                                {{ t.locations_count }}
                                                sites</span
                                            >
                                            <span
                                                >📍
                                                {{ t.checkpoints_count }}
                                                checkpoints</span
                                            >
                                        </div>
                                    </td>
                                    <td class="p-4 font-mono text-slate-600">
                                        {{ formatDate(t.subscription_until) }}
                                    </td>
                                    <td class="p-4 pr-6 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button
                                                @click="toggleHistory(t.id)"
                                                class="text-slate-750 rounded-xl border border-slate-200 px-3 py-2 font-mono text-[10px] font-black uppercase tracking-widest transition-all hover:bg-slate-50 active:scale-95"
                                            >
                                                {{
                                                    expandedTenantIds.includes(
                                                        t.id,
                                                    )
                                                        ? 'Hide Logs'
                                                        : 'History'
                                                }}
                                            </button>
                                            <button
                                                @click="openPlanModal(t)"
                                                class="rounded-xl bg-indigo-600 px-3.5 py-2 font-mono text-[10px] font-black uppercase tracking-widest text-white shadow-sm transition-all hover:bg-indigo-500 active:scale-95"
                                            >
                                                Change Plan
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="expandedTenantIds.includes(t.id)">
                                    <td
                                        colspan="6"
                                        class="border-b border-slate-100 bg-slate-50/70 p-6"
                                    >
                                        <div class="max-w-4xl space-y-3">
                                            <div
                                                class="text-slate-450 mb-2 font-mono text-[10px] font-black uppercase tracking-widest"
                                            >
                                                Subscription Change Audit Log
                                            </div>
                                            <div
                                                v-if="
                                                    !t.subscription_logs ||
                                                    t.subscription_logs
                                                        .length === 0
                                                "
                                                class="text-slate-450 text-xs italic"
                                            >
                                                No subscription changes logged
                                                yet.
                                            </div>
                                            <div
                                                v-else
                                                class="relative space-y-4 border-l-2 border-slate-200 pl-6"
                                            >
                                                <div
                                                    v-for="log in t.subscription_logs"
                                                    :key="log.id"
                                                    class="relative"
                                                >
                                                    <!-- bullet -->
                                                    <div
                                                        class="border-indigo-650 absolute -left-[31px] top-1.5 h-3.5 w-3.5 rounded-full border-2 bg-white"
                                                    ></div>
                                                    <div
                                                        class="shadow-xs flex flex-col justify-between gap-4 rounded-2xl border border-slate-200/80 bg-white p-4 md:flex-row"
                                                    >
                                                        <div>
                                                            <div
                                                                class="flex flex-wrap items-center gap-2"
                                                            >
                                                                <span
                                                                    class="text-[10px] font-bold uppercase tracking-wide text-slate-700"
                                                                    >Plan
                                                                    Change:</span
                                                                >
                                                                <span
                                                                    class="rounded bg-slate-100 px-2 py-0.5 font-mono text-[9px] font-bold uppercase text-slate-700"
                                                                    >{{
                                                                        availablePlans[
                                                                            log
                                                                                .previous_plan
                                                                        ]
                                                                            ?.name ||
                                                                        log.previous_plan
                                                                    }}</span
                                                                >
                                                                <span
                                                                    class="text-slate-400"
                                                                    >→</span
                                                                >
                                                                <span
                                                                    class="text-indigo-750 rounded bg-indigo-50 px-2 py-0.5 font-mono text-[9px] font-bold uppercase"
                                                                    >{{
                                                                        availablePlans[
                                                                            log
                                                                                .new_plan
                                                                        ]
                                                                            ?.name ||
                                                                        log.new_plan
                                                                    }}</span
                                                                >
                                                            </div>
                                                            <div
                                                                class="mt-1 text-[11px] text-slate-500"
                                                            >
                                                                <span
                                                                    >Validity:
                                                                </span>
                                                                <span
                                                                    class="font-mono"
                                                                    >{{
                                                                        formatDate(
                                                                            log.previous_until,
                                                                        )
                                                                    }}</span
                                                                >
                                                                <span
                                                                    class="text-slate-400"
                                                                >
                                                                    →
                                                                </span>
                                                                <span
                                                                    class="font-mono font-bold text-slate-700"
                                                                    >{{
                                                                        formatDate(
                                                                            log.new_until,
                                                                        )
                                                                    }}</span
                                                                >
                                                            </div>
                                                        </div>
                                                        <div
                                                            class="flex flex-col justify-center text-right font-mono text-[10px] text-slate-400"
                                                        >
                                                            <div>
                                                                Operator:
                                                                <span
                                                                    class="font-bold text-slate-600"
                                                                    >{{
                                                                        log
                                                                            .operator
                                                                            ?.name ||
                                                                        'System / Admin'
                                                                    }}</span
                                                                >
                                                            </div>
                                                            <div class="mt-0.5">
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
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- CHANGE PLAN MODAL -->
                <div
                    v-if="selectedTenant"
                    class="backdrop-blur-xs fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-6"
                >
                    <div
                        class="animate-fade-in w-full max-w-sm space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl"
                    >
                        <div
                            class="flex items-center justify-between border-b border-slate-100 pb-2"
                        >
                            <h4
                                class="font-mono text-xs font-black uppercase tracking-widest text-slate-800"
                            >
                                Configure Subscription
                            </h4>
                            <button
                                @click="selectedTenant = null"
                                class="text-lg text-slate-400 hover:text-slate-600"
                            >
                                ×
                            </button>
                        </div>

                        <div class="space-y-4 text-xs text-slate-700">
                            <div class="space-y-1">
                                <label
                                    class="block font-mono text-[10px] font-black uppercase tracking-widest text-slate-400"
                                    >Company Account</label
                                >
                                <div
                                    class="rounded-xl border border-slate-200 bg-slate-50 p-3 font-bold text-slate-800"
                                >
                                    {{ selectedTenant.name }}
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label
                                    class="block font-mono text-[10px] font-black uppercase tracking-widest text-slate-400"
                                    >Select Plan Level</label
                                >
                                <select
                                    v-model="formPlan"
                                    class="min-h-[48px] w-full rounded-xl border border-slate-200 bg-slate-50 p-3 font-bold text-slate-800 focus:outline-none"
                                >
                                    <option
                                        v-for="(
                                            pDetails, pKey
                                        ) in availablePlans"
                                        :key="pKey"
                                        :value="pKey"
                                    >
                                        {{ pDetails.name }} (€{{
                                            pDetails.price_monthly
                                        }}/mo)
                                    </option>
                                </select>
                            </div>

                            <div class="space-y-1">
                                <label
                                    class="block font-mono text-[10px] font-black uppercase tracking-widest text-slate-400"
                                    >Subscription Until</label
                                >
                                <div class="flex gap-2">
                                    <input
                                        v-model="formUntil"
                                        type="date"
                                        class="min-h-[48px] flex-1 rounded-xl border border-slate-200 bg-slate-50 p-3 font-bold text-slate-800 focus:outline-none"
                                    />
                                    <button
                                        type="button"
                                        @click="formUntil = ''"
                                        class="rounded-xl bg-slate-100 px-4 font-mono text-xs font-black uppercase tracking-wider text-slate-600 transition-colors hover:bg-slate-200"
                                    >
                                        Lifetime
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex space-x-3 pt-2">
                            <button
                                @click="selectedTenant = null"
                                class="hover:bg-slate-205 min-h-[48px] flex-1 rounded-xl bg-slate-100 py-3 font-mono text-xs font-black uppercase tracking-wider text-slate-700 transition-all"
                            >
                                Cancel
                            </button>
                            <button
                                @click="submitPlanChange"
                                :disabled="isSubmitting"
                                class="min-h-[48px] flex-1 rounded-xl bg-indigo-600 py-3 font-mono text-xs font-black uppercase tracking-wider text-white shadow-md transition-all hover:bg-indigo-500 active:scale-95"
                            >
                                {{ isSubmitting ? 'Updating...' : 'Save Plan' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PACKAGES VIEW -->
            <div v-else-if="activeTab === 'packages'" class="space-y-6">
                <div
                    v-if="isFetchingPackages"
                    class="text-slate-450 flex flex-col items-center justify-center gap-3 py-20"
                >
                    <div
                        class="h-8 w-8 animate-spin rounded-full border-4 border-indigo-500/20 border-t-indigo-600"
                    ></div>
                    <span
                        class="font-mono text-xs font-bold uppercase tracking-wider"
                        >Loading packages...</span
                    >
                </div>
                <div
                    v-else
                    class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm"
                >
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr
                                class="border-b border-slate-200 bg-slate-50 font-mono text-[10px] font-black uppercase tracking-widest text-slate-400"
                            >
                                <th class="p-4 pl-6">Package Name</th>
                                <th class="p-4">Plan Key</th>
                                <th class="p-4">Monthly Rate</th>
                                <th class="p-4">Guard Limit</th>
                                <th class="p-4">Site Limit</th>
                                <th class="p-4">Checkpoint Limit</th>
                                <th class="p-4 pr-6 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            <tr
                                v-for="pkg in packages"
                                :key="pkg.id"
                                class="transition-colors hover:bg-slate-50/50"
                            >
                                <td class="p-4 pl-6">
                                    <div class="font-bold text-slate-800">
                                        {{ pkg.name }}
                                    </div>
                                </td>
                                <td
                                    class="p-4 font-mono font-bold uppercase text-slate-500"
                                >
                                    {{ pkg.plan_key }}
                                </td>
                                <td
                                    class="p-4 font-mono text-sm font-black text-slate-800"
                                >
                                    €{{ pkg.price_monthly }}
                                </td>
                                <td class="p-4 font-mono text-slate-600">
                                    {{
                                        pkg.guards_limit >= 99999
                                            ? 'Unlimited'
                                            : pkg.guards_limit
                                    }}
                                </td>
                                <td class="p-4 font-mono text-slate-600">
                                    {{
                                        pkg.locations_limit >= 99999
                                            ? 'Unlimited'
                                            : pkg.locations_limit
                                    }}
                                </td>
                                <td class="p-4 font-mono text-slate-600">
                                    {{
                                        pkg.checkpoints_limit >= 99999
                                            ? 'Unlimited'
                                            : pkg.checkpoints_limit
                                    }}
                                </td>
                                <td class="p-4 pr-6 text-right">
                                    <button
                                        @click="openEditPackage(pkg)"
                                        class="rounded-xl bg-indigo-600 px-3.5 py-2 font-mono text-[10px] font-black uppercase tracking-widest text-white shadow-sm transition-all hover:bg-indigo-500 active:scale-95"
                                    >
                                        Edit Package
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- EDIT PACKAGE MODAL -->
                <div
                    v-if="editingPackage"
                    class="backdrop-blur-xs fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-6"
                >
                    <div
                        class="animate-fade-in w-full max-w-sm space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl"
                    >
                        <div
                            class="flex items-center justify-between border-b border-slate-100 pb-2"
                        >
                            <h4
                                class="font-mono text-xs font-black uppercase tracking-widest text-slate-800"
                            >
                                Customize Plan Tier
                            </h4>
                            <button
                                @click="editingPackage = null"
                                class="text-lg text-slate-400 hover:text-slate-600"
                            >
                                ×
                            </button>
                        </div>

                        <div class="space-y-3.5 text-xs text-slate-700">
                            <div class="space-y-1">
                                <label
                                    class="block font-mono text-[10px] font-black uppercase tracking-widest text-slate-400"
                                    >Plan Name</label
                                >
                                <input
                                    v-model="formPkgName"
                                    type="text"
                                    class="min-h-[48px] w-full rounded-xl border border-slate-200 bg-slate-50 p-3 font-bold text-slate-800 focus:outline-none"
                                />
                            </div>

                            <div class="space-y-1">
                                <label
                                    class="block font-mono text-[10px] font-black uppercase tracking-widest text-slate-400"
                                    >Monthly Cost (€)</label
                                >
                                <input
                                    v-model="formPkgPrice"
                                    type="number"
                                    step="0.01"
                                    class="min-h-[48px] w-full rounded-xl border border-slate-200 bg-slate-50 p-3 font-bold text-slate-800 focus:outline-none"
                                />
                            </div>

                            <div class="space-y-1">
                                <label
                                    class="block font-mono text-[10px] font-black uppercase tracking-widest text-slate-400"
                                    >Guards Limit</label
                                >
                                <div class="flex gap-2">
                                    <input
                                        v-model="formPkgGuards"
                                        type="number"
                                        class="min-h-[48px] flex-1 rounded-xl border border-slate-200 bg-slate-50 p-3 font-bold text-slate-800 focus:outline-none"
                                    />
                                    <button
                                        type="button"
                                        @click="formPkgGuards = 99999"
                                        class="text-slate-650 rounded-xl bg-slate-100 px-3 font-mono text-[10px] font-black uppercase tracking-wider transition-colors hover:bg-slate-200"
                                    >
                                        Unlimited
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label
                                    class="block font-mono text-[10px] font-black uppercase tracking-widest text-slate-400"
                                    >Locations Limit</label
                                >
                                <div class="flex gap-2">
                                    <input
                                        v-model="formPkgLocations"
                                        type="number"
                                        class="min-h-[48px] flex-1 rounded-xl border border-slate-200 bg-slate-50 p-3 font-bold text-slate-800 focus:outline-none"
                                    />
                                    <button
                                        type="button"
                                        @click="formPkgLocations = 99999"
                                        class="text-slate-650 rounded-xl bg-slate-100 px-3 font-mono text-[10px] font-black uppercase tracking-wider transition-colors hover:bg-slate-200"
                                    >
                                        Unlimited
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label
                                    class="block font-mono text-[10px] font-black uppercase tracking-widest text-slate-400"
                                    >Checkpoints Limit</label
                                >
                                <div class="flex gap-2">
                                    <input
                                        v-model="formPkgCheckpoints"
                                        type="number"
                                        class="min-h-[48px] flex-1 rounded-xl border border-slate-200 bg-slate-50 p-3 font-bold text-slate-800 focus:outline-none"
                                    />
                                    <button
                                        type="button"
                                        @click="formPkgCheckpoints = 99999"
                                        class="text-slate-650 rounded-xl bg-slate-100 px-3 font-mono text-[10px] font-black uppercase tracking-wider transition-colors hover:bg-slate-200"
                                    >
                                        Unlimited
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex space-x-3 pt-2">
                            <button
                                @click="editingPackage = null"
                                class="hover:bg-slate-205 min-h-[48px] flex-1 rounded-xl bg-slate-100 py-3 font-mono text-xs font-black uppercase tracking-wider text-slate-700 transition-all"
                            >
                                Cancel
                            </button>
                            <button
                                @click="submitPackageChange"
                                :disabled="isSavingPackage"
                                class="min-h-[48px] flex-1 rounded-xl bg-indigo-600 py-3 font-mono text-xs font-black uppercase tracking-wider text-white shadow-md transition-all hover:bg-indigo-500 active:scale-95"
                            >
                                {{
                                    isSavingPackage
                                        ? 'Saving...'
                                        : 'Save Settings'
                                }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
