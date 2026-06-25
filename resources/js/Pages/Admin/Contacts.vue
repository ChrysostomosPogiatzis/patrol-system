<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';

// Component Import
import ContactModal from '@/Components/Admin/ContactModal.vue';

interface Contact {
    id: number;
    name: string;
    role_label: string;
    phone?: string;
    email?: string;
    notify_channels: string[];
    notify_on: string[];
    tenant?: {
        id: number;
        name: string;
    };
}

const contacts = ref<Contact[]>([]);
const showAddContactModal = ref(false);
const editingContact = ref<Contact | null>(null);

const page = usePage();
const isAllCompaniesMode = computed(() => {
    const user = page.props.auth.user as any;
    return (
        user.role === 'superadmin' &&
        !(page.props.auth as any).override_tenant_id
    );
});

const searchQuery = ref('');
const sortBy = ref('name'); // name, role
const sortOrder = ref<'asc' | 'desc'>('asc');

async function fetchContacts() {
    try {
        const res = await axios.get('/admin/api/contacts');
        contacts.value = res.data.contacts;
    } catch (e) {
        console.error('Failed to load contacts:', e);
    }
}

function openAddContact() {
    editingContact.value = null;
    showAddContactModal.value = true;
}

function openEditContact(contact: Contact) {
    if (isAllCompaniesMode.value) return;
    editingContact.value = contact;
    showAddContactModal.value = true;
}

async function deleteContact(id: number) {
    if (isAllCompaniesMode.value) return;
    if (!confirm('Are you sure you want to delete this alert contact?')) return;
    try {
        await axios.delete(`/admin/api/contacts/${id}`);
        fetchContacts();
    } catch (e: any) {
        alert(e.response?.data?.message || 'Failed to delete contact.');
    }
}

async function submitAddContact(payload: any) {
    try {
        if (editingContact.value) {
            await axios.put(
                `/admin/api/contacts/${editingContact.value.id}`,
                payload,
            );
        } else {
            await axios.post('/admin/api/contacts', payload);
        }
        showAddContactModal.value = false;
        fetchContacts();
    } catch (e) {
        alert('Failed to save contact.');
    }
}

const filteredAndSortedContacts = computed(() => {
    let list = contacts.value;

    // Filtering
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(
            (c) =>
                c.name.toLowerCase().includes(q) ||
                c.role_label.toLowerCase().includes(q) ||
                (c.email && c.email.toLowerCase().includes(q)) ||
                (c.phone && c.phone.toLowerCase().includes(q)),
        );
    }

    // Sorting
    list = [...list].sort((a, b) => {
        let valA: any = '';
        let valB: any = '';

        if (sortBy.value === 'name') {
            valA = a.name;
            valB = b.name;
        } else if (sortBy.value === 'role') {
            valA = a.role_label;
            valB = b.role_label;
        }

        return sortOrder.value === 'asc'
            ? valA.localeCompare(valB)
            : valB.localeCompare(valA);
    });

    return list;
});

function toggleSort(key: string) {
    if (sortBy.value === key) {
        sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = key;
        sortOrder.value = 'asc';
    }
}

onMounted(() => {
    fetchContacts();
});
</script>

<template>
    <Head title="Alert Contacts" />

    <AdminLayout title="Alert Contacts Directory">
        <div class="space-y-4">
            <!-- Context warning banner -->
            <div
                v-if="isAllCompaniesMode"
                class="border-indigo-150 text-indigo-750 flex items-center gap-3 rounded-2xl border bg-indigo-50 p-4 text-xs font-medium"
            >
                <span class="text-base">ℹ</span>
                <span
                    >You are viewing alert contacts across
                    <strong>all companies</strong>. To register new contacts or
                    edit alert notifications configurations, please select a
                    specific company context from the dropdown at the top.</span
                >
            </div>

            <!-- Header actions -->
            <div class="flex items-center justify-between">
                <span
                    class="font-mono text-xs font-black uppercase tracking-widest text-slate-500"
                    >Emergency Alert Recipients ({{
                        filteredAndSortedContacts.length
                    }})</span
                >
                <button
                    @click="openAddContact"
                    :disabled="isAllCompaniesMode"
                    class="flex min-h-[48px] items-center space-x-2 rounded-xl px-5 py-3 text-xs font-black uppercase tracking-wider text-white shadow-md transition-all"
                    :class="
                        isAllCompaniesMode
                            ? 'bg-slate-350 cursor-not-allowed opacity-60'
                            : 'bg-indigo-600 hover:bg-indigo-500 active:scale-95'
                    "
                    :title="
                        isAllCompaniesMode
                            ? 'Select a company context to add alert recipients'
                            : ''
                    "
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2.2"
                            d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"
                        />
                    </svg>
                    <span>Add Recipient</span>
                </button>
            </div>

            <!-- Search and filters -->
            <div
                class="flex flex-col gap-4 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm md:flex-row"
            >
                <div class="relative flex-1">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search alert contacts by name, role, email, phone..."
                        class="min-h-[42px] w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs focus:border-indigo-500 focus:outline-none"
                    />
                    <span class="absolute left-3.5 top-3 text-xs text-slate-400"
                        >🔍</span
                    >
                </div>
                <div
                    class="flex items-center space-x-2 font-mono text-xs uppercase text-slate-500"
                >
                    <span class="mr-1">Sort by:</span>
                    <button
                        @click="toggleSort('name')"
                        class="min-h-[42px] cursor-pointer rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs focus:border-indigo-500 focus:outline-none"
                        :class="
                            sortBy === 'name'
                                ? 'border-indigo-250 bg-indigo-50 font-bold text-indigo-600'
                                : ''
                        "
                    >
                        Name
                        <span v-if="sortBy === 'name'">{{
                            sortOrder === 'asc' ? '▲' : '▼'
                        }}</span>
                    </button>
                    <button
                        @click="toggleSort('role')"
                        class="min-h-[42px] cursor-pointer rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs focus:border-indigo-500 focus:outline-none"
                        :class="
                            sortBy === 'role'
                                ? 'border-indigo-250 bg-indigo-50 font-bold text-indigo-600'
                                : ''
                        "
                    >
                        Role
                        <span v-if="sortBy === 'role'">{{
                            sortOrder === 'asc' ? '▲' : '▼'
                        }}</span>
                    </button>
                </div>
            </div>

            <!-- Contacts Cards Grid -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <div
                    v-for="c in filteredAndSortedContacts"
                    :key="c.id"
                    class="hover:border-slate-355 group flex flex-col justify-between space-y-4 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition-all duration-200"
                >
                    <div class="space-y-4">
                        <div>
                            <h4
                                class="flex flex-wrap items-center gap-2 font-mono text-xs font-black text-slate-800 transition-colors group-hover:text-indigo-600"
                            >
                                <span>{{ c.name }}</span>
                                <span
                                    v-if="isAllCompaniesMode"
                                    class="text-slate-450 rounded border border-slate-200 bg-slate-100 px-1.5 py-0.5 font-sans text-[9px] font-bold uppercase normal-case"
                                    >{{ c.tenant?.name || 'System' }}</span
                                >
                            </h4>
                            <span
                                class="mt-1 block text-[9px] font-black uppercase tracking-wider text-indigo-600"
                                >{{ c.role_label }}</span
                            >
                        </div>

                        <div
                            class="space-y-1.5 font-mono text-xs text-slate-500"
                        >
                            <p
                                v-if="c.phone"
                                class="flex items-center space-x-2"
                            >
                                <span class="text-[10px]">📞</span>
                                <span>{{ c.phone }}</span>
                            </p>
                            <p
                                v-if="c.email"
                                class="flex items-center space-x-2"
                            >
                                <span class="text-[10px]">✉️</span>
                                <span class="break-all text-slate-600">{{
                                    c.email
                                }}</span>
                            </p>
                        </div>

                        <div
                            class="border-slate-150 space-y-3 border-t pt-3 font-mono text-[9px]"
                        >
                            <div>
                                <span
                                    class="mb-1.5 block text-[8px] font-black uppercase text-slate-400"
                                    >Notification Channels</span
                                >
                                <span
                                    v-for="ch in c.notify_channels"
                                    :key="ch"
                                    class="border-indigo-150/80 mr-1.5 inline-block rounded-full border bg-indigo-50 px-2 py-0.5 font-bold text-indigo-600"
                                >
                                    {{ ch.toUpperCase() }}
                                </span>
                            </div>
                            <div class="pt-1">
                                <span
                                    class="mb-1.5 block text-[8px] font-black uppercase text-slate-400"
                                    >Triggers Alert On</span
                                >
                                <span
                                    v-for="evt in c.notify_on"
                                    :key="evt"
                                    class="border-amber-150 mr-1.5 mt-1 inline-block rounded-full border bg-amber-50 px-2 py-0.5 font-bold text-amber-700"
                                >
                                    {{ evt.replace('_', ' ').toUpperCase() }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions footer for contacts -->
                    <div
                        class="border-slate-150/60 mt-4 flex justify-end space-x-2 border-t pt-3"
                    >
                        <button
                            @click="openEditContact(c)"
                            :disabled="isAllCompaniesMode"
                            class="rounded-lg border px-3 py-1.5 font-mono text-[10px] font-black uppercase tracking-wider transition-all active:scale-95"
                            :class="
                                isAllCompaniesMode
                                    ? 'border-slate-150 cursor-not-allowed bg-slate-50 text-slate-400'
                                    : 'text-indigo-650 border-indigo-100/60 bg-indigo-50 hover:bg-indigo-100'
                            "
                            :title="
                                isAllCompaniesMode
                                    ? 'Select a company to edit contact details'
                                    : ''
                            "
                        >
                            Edit
                        </button>
                        <button
                            @click="deleteContact(c.id)"
                            :disabled="isAllCompaniesMode"
                            class="rounded-lg border px-3 py-1.5 font-mono text-[10px] font-black uppercase tracking-wider transition-all active:scale-95"
                            :class="
                                isAllCompaniesMode
                                    ? 'border-slate-150 cursor-not-allowed bg-slate-50 text-slate-400'
                                    : 'text-red-650 border-red-100/60 bg-red-50 hover:bg-red-100'
                            "
                            :title="
                                isAllCompaniesMode
                                    ? 'Select a company to delete contacts'
                                    : ''
                            "
                        >
                            Delete
                        </button>
                    </div>
                </div>

                <div
                    v-if="filteredAndSortedContacts.length === 0"
                    class="col-span-3 rounded-2xl border border-dashed border-slate-200 bg-white p-16 text-center text-xs font-medium text-slate-400"
                >
                    No matching alert contacts found.
                </div>
            </div>
        </div>

        <!-- FORM MODAL -->
        <ContactModal
            :show="showAddContactModal"
            :contact="editingContact"
            @close="showAddContactModal = false"
            @submit="submitAddContact"
        />
    </AdminLayout>
</template>
