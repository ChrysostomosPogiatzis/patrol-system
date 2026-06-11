<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';

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
        expandedTenantIds.value = expandedTenantIds.value.filter(tId => tId !== id);
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
    formUntil.value = tenant.subscription_until ? tenant.subscription_until.substring(0, 10) : '';
}

async function submitPlanChange() {
    if (!selectedTenant.value) return;
    isSubmitting.value = true;
    try {
        await axios.put(`/admin/api/superadmin/tenants/${selectedTenant.value.id}/plan`, {
            subscription_plan: formPlan.value,
            subscription_until: formUntil.value || null,
        });
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
        await axios.put(`/admin/api/superadmin/plans/${editingPackage.value.id}`, {
            name: formPkgName.value,
            price_monthly: formPkgPrice.value,
            guards_limit: formPkgGuards.value,
            locations_limit: formPkgLocations.value,
            checkpoints_limit: formPkgCheckpoints.value,
        });
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
        <div v-if="isLoading" class="flex flex-col items-center justify-center py-20 text-slate-450 gap-3">
            <div class="w-8 h-8 border-4 border-indigo-500/20 border-t-indigo-600 rounded-full animate-spin"></div>
            <span class="text-xs font-bold font-mono uppercase tracking-wider">Fetching billing accounts...</span>
        </div>

        <div v-else class="space-y-6">
            <!-- HEADER WITH TABS -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center pb-3 border-b border-slate-200 gap-4">
                <div>
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-800 font-mono">Global Billing & Subscription Accounts</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">Control active plans, monthly billing limits, and pricing packages.</p>
                </div>
                <!-- Tabs switcher -->
                <div class="flex bg-slate-200/60 p-1 rounded-2xl border border-slate-350/40">
                    <button 
                        @click="activeTab = 'accounts'" 
                        class="px-4 py-2 text-[10px] font-black uppercase tracking-widest font-mono rounded-xl transition-all"
                        :class="activeTab === 'accounts' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                    >
                        Company Accounts
                    </button>
                    <button 
                        @click="activeTab = 'packages'" 
                        class="px-4 py-2 text-[10px] font-black uppercase tracking-widest font-mono rounded-xl transition-all"
                        :class="activeTab === 'packages' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                    >
                        Pricing Packages
                    </button>
                </div>
            </div>

            <!-- ACCOUNTS VIEW -->
            <div v-if="activeTab === 'accounts'" class="space-y-6">
                <!-- ACCOUNTS LIST -->
                <div class="bg-white border border-slate-200/80 rounded-3xl overflow-hidden shadow-sm">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-black uppercase tracking-widest font-mono text-slate-400">
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
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="p-4 pl-6">
                                        <div class="font-bold text-slate-800">{{ t.name }}</div>
                                        <div class="text-[10px] text-slate-400 font-mono mt-0.5">slug: {{ t.slug }}</div>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2.5 py-1 rounded-lg border text-[10px] font-mono font-black uppercase tracking-wider bg-indigo-50 text-indigo-650 border-indigo-150">
                                            {{ availablePlans[t.subscription_plan]?.name || t.subscription_plan }}
                                        </span>
                                    </td>
                                    <td class="p-4 font-mono font-bold text-slate-700">
                                        €{{ getPlanPrice(t.subscription_plan) }}
                                    </td>
                                    <td class="p-4">
                                        <div class="flex items-center gap-3 font-mono text-[10px] text-slate-505 font-bold uppercase">
                                            <span>👮 {{ t.guards_count }} guards</span>
                                            <span>🏢 {{ t.locations_count }} sites</span>
                                            <span>📍 {{ t.checkpoints_count }} checkpoints</span>
                                        </div>
                                    </td>
                                    <td class="p-4 font-mono text-slate-600">
                                        {{ formatDate(t.subscription_until) }}
                                    </td>
                                    <td class="p-4 pr-6 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button 
                                                @click="toggleHistory(t.id)"
                                                class="border border-slate-200 hover:bg-slate-50 text-slate-750 font-black text-[10px] uppercase font-mono tracking-widest px-3 py-2 rounded-xl transition-all active:scale-95"
                                            >
                                                {{ expandedTenantIds.includes(t.id) ? 'Hide Logs' : 'History' }}
                                            </button>
                                            <button 
                                                @click="openPlanModal(t)"
                                                class="bg-indigo-600 hover:bg-indigo-500 text-white font-black text-[10px] uppercase font-mono tracking-widest px-3.5 py-2 rounded-xl transition-all shadow-sm active:scale-95"
                                            >
                                                Change Plan
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="expandedTenantIds.includes(t.id)">
                                    <td colspan="6" class="bg-slate-50/70 p-6 border-b border-slate-100">
                                        <div class="space-y-3 max-w-4xl">
                                            <div class="text-[10px] font-black uppercase tracking-widest text-slate-450 font-mono mb-2">Subscription Change Audit Log</div>
                                            <div v-if="!t.subscription_logs || t.subscription_logs.length === 0" class="text-xs text-slate-450 italic">
                                                No subscription changes logged yet.
                                            </div>
                                            <div v-else class="relative pl-6 border-l-2 border-slate-200 space-y-4">
                                                <div v-for="log in t.subscription_logs" :key="log.id" class="relative">
                                                    <!-- bullet -->
                                                    <div class="absolute -left-[31px] top-1.5 w-3.5 h-3.5 rounded-full border-2 border-indigo-650 bg-white"></div>
                                                    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs flex flex-col md:flex-row justify-between gap-4">
                                                        <div>
                                                            <div class="flex items-center gap-2 flex-wrap">
                                                                <span class="font-bold text-slate-700 uppercase tracking-wide text-[10px]">Plan Change:</span>
                                                                <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-mono text-[9px] uppercase font-bold">{{ availablePlans[log.previous_plan]?.name || log.previous_plan }}</span>
                                                                <span class="text-slate-400">→</span>
                                                                <span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-750 font-mono text-[9px] uppercase font-bold">{{ availablePlans[log.new_plan]?.name || log.new_plan }}</span>
                                                            </div>
                                                            <div class="text-[11px] text-slate-500 mt-1">
                                                                <span>Validity: </span>
                                                                <span class="font-mono">{{ formatDate(log.previous_until) }}</span>
                                                                <span class="text-slate-400"> → </span>
                                                                <span class="font-mono font-bold text-slate-700">{{ formatDate(log.new_until) }}</span>
                                                            </div>
                                                        </div>
                                                        <div class="text-right text-[10px] font-mono text-slate-400 flex flex-col justify-center">
                                                            <div>Operator: <span class="font-bold text-slate-600">{{ log.operator?.name || 'System / Admin' }}</span></div>
                                                            <div class="mt-0.5">{{ new Date(log.created_at).toLocaleString() }}</div>
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
                    class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-6"
                >
                    <div class="bg-white border border-slate-200 rounded-3xl p-6 w-full max-w-sm space-y-4 shadow-2xl animate-fade-in">
                        <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                            <h4 class="text-xs font-black uppercase tracking-widest text-slate-800 font-mono">
                                Configure Subscription
                            </h4>
                            <button @click="selectedTenant = null" class="text-slate-400 hover:text-slate-600 text-lg">
                                ×
                            </button>
                        </div>

                        <div class="space-y-4 text-xs text-slate-700">
                            <div class="space-y-1">
                                <label class="block text-[10px] text-slate-400 uppercase tracking-widest font-black font-mono">Company Account</label>
                                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-800">
                                    {{ selectedTenant.name }}
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="block text-[10px] text-slate-400 uppercase tracking-widest font-black font-mono">Select Plan Level</label>
                                <select v-model="formPlan" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-800 font-bold focus:outline-none min-h-[48px]">
                                    <option v-for="(pDetails, pKey) in availablePlans" :key="pKey" :value="pKey">
                                        {{ pDetails.name }} (€{{ pDetails.price_monthly }}/mo)
                                    </option>
                                </select>
                            </div>

                            <div class="space-y-1">
                                <label class="block text-[10px] text-slate-400 uppercase tracking-widest font-black font-mono">Subscription Until</label>
                                <div class="flex gap-2">
                                    <input v-model="formUntil" type="date" class="flex-1 bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-800 font-bold focus:outline-none min-h-[48px]" />
                                    <button type="button" @click="formUntil = ''" class="px-4 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-black uppercase font-mono tracking-wider transition-colors">
                                        Lifetime
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex space-x-3 pt-2">
                            <button 
                                @click="selectedTenant = null" 
                                class="flex-1 py-3 bg-slate-100 hover:bg-slate-205 text-slate-700 text-xs font-black uppercase tracking-wider font-mono rounded-xl transition-all min-h-[48px]"
                            >
                                Cancel
                            </button>
                            <button 
                                @click="submitPlanChange" 
                                :disabled="isSubmitting"
                                class="flex-1 py-3 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-black uppercase tracking-wider font-mono rounded-xl shadow-md transition-all active:scale-95 min-h-[48px]"
                            >
                                {{ isSubmitting ? 'Updating...' : 'Save Plan' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PACKAGES VIEW -->
            <div v-else-if="activeTab === 'packages'" class="space-y-6">
                <div v-if="isFetchingPackages" class="flex flex-col items-center justify-center py-20 text-slate-450 gap-3">
                    <div class="w-8 h-8 border-4 border-indigo-500/20 border-t-indigo-600 rounded-full animate-spin"></div>
                    <span class="text-xs font-bold font-mono uppercase tracking-wider">Loading packages...</span>
                </div>
                <div v-else class="bg-white border border-slate-200/80 rounded-3xl overflow-hidden shadow-sm">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-black uppercase tracking-widest font-mono text-slate-400">
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
                            <tr v-for="pkg in packages" :key="pkg.id" class="hover:bg-slate-50/50 transition-colors">
                                <td class="p-4 pl-6">
                                    <div class="font-bold text-slate-800">{{ pkg.name }}</div>
                                </td>
                                <td class="p-4 font-mono font-bold text-slate-500 uppercase">
                                    {{ pkg.plan_key }}
                                </td>
                                <td class="p-4 font-mono font-black text-slate-800 text-sm">
                                    €{{ pkg.price_monthly }}
                                </td>
                                <td class="p-4 font-mono text-slate-600">
                                    {{ pkg.guards_limit >= 99999 ? 'Unlimited' : pkg.guards_limit }}
                                </td>
                                <td class="p-4 font-mono text-slate-600">
                                    {{ pkg.locations_limit >= 99999 ? 'Unlimited' : pkg.locations_limit }}
                                </td>
                                <td class="p-4 font-mono text-slate-600">
                                    {{ pkg.checkpoints_limit >= 99999 ? 'Unlimited' : pkg.checkpoints_limit }}
                                </td>
                                <td class="p-4 pr-6 text-right">
                                    <button 
                                        @click="openEditPackage(pkg)"
                                        class="bg-indigo-600 hover:bg-indigo-500 text-white font-black text-[10px] uppercase font-mono tracking-widest px-3.5 py-2 rounded-xl transition-all shadow-sm active:scale-95"
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
                    class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-6"
                >
                    <div class="bg-white border border-slate-200 rounded-3xl p-6 w-full max-w-sm space-y-4 shadow-2xl animate-fade-in">
                        <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                            <h4 class="text-xs font-black uppercase tracking-widest text-slate-800 font-mono">
                                Customize Plan Tier
                            </h4>
                            <button @click="editingPackage = null" class="text-slate-400 hover:text-slate-600 text-lg">
                                ×
                            </button>
                        </div>

                        <div class="space-y-3.5 text-xs text-slate-700">
                            <div class="space-y-1">
                                <label class="block text-[10px] text-slate-400 uppercase tracking-widest font-black font-mono">Plan Name</label>
                                <input v-model="formPkgName" type="text" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-800 font-bold focus:outline-none min-h-[48px]" />
                            </div>

                            <div class="space-y-1">
                                <label class="block text-[10px] text-slate-400 uppercase tracking-widest font-black font-mono">Monthly Cost (€)</label>
                                <input v-model="formPkgPrice" type="number" step="0.01" class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-800 font-bold focus:outline-none min-h-[48px]" />
                            </div>

                            <div class="space-y-1">
                                <label class="block text-[10px] text-slate-400 uppercase tracking-widest font-black font-mono">Guards Limit</label>
                                <div class="flex gap-2">
                                    <input v-model="formPkgGuards" type="number" class="flex-1 bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-800 font-bold focus:outline-none min-h-[48px]" />
                                    <button type="button" @click="formPkgGuards = 99999" class="px-3 bg-slate-100 hover:bg-slate-200 text-slate-650 rounded-xl font-mono text-[10px] font-black uppercase tracking-wider transition-colors">
                                        Unlimited
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="block text-[10px] text-slate-400 uppercase tracking-widest font-black font-mono">Locations Limit</label>
                                <div class="flex gap-2">
                                    <input v-model="formPkgLocations" type="number" class="flex-1 bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-800 font-bold focus:outline-none min-h-[48px]" />
                                    <button type="button" @click="formPkgLocations = 99999" class="px-3 bg-slate-100 hover:bg-slate-200 text-slate-650 rounded-xl font-mono text-[10px] font-black uppercase tracking-wider transition-colors">
                                        Unlimited
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="block text-[10px] text-slate-400 uppercase tracking-widest font-black font-mono">Checkpoints Limit</label>
                                <div class="flex gap-2">
                                    <input v-model="formPkgCheckpoints" type="number" class="flex-1 bg-slate-50 border border-slate-200 p-3 rounded-xl text-slate-800 font-bold focus:outline-none min-h-[48px]" />
                                    <button type="button" @click="formPkgCheckpoints = 99999" class="px-3 bg-slate-100 hover:bg-slate-200 text-slate-650 rounded-xl font-mono text-[10px] font-black uppercase tracking-wider transition-colors">
                                        Unlimited
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex space-x-3 pt-2">
                            <button 
                                @click="editingPackage = null" 
                                class="flex-1 py-3 bg-slate-100 hover:bg-slate-205 text-slate-700 text-xs font-black uppercase tracking-wider font-mono rounded-xl transition-all min-h-[48px]"
                            >
                                Cancel
                            </button>
                            <button 
                                @click="submitPackageChange" 
                                :disabled="isSavingPackage"
                                class="flex-1 py-3 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-black uppercase tracking-wider font-mono rounded-xl shadow-md transition-all active:scale-95 min-h-[48px]"
                            >
                                {{ isSavingPackage ? 'Saving...' : 'Save Settings' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
