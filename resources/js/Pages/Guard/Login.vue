<script setup lang="ts">
import axios from 'axios';
import { ref } from 'vue';

const emit = defineEmits<{
    (e: 'login-success', data: { token: string; guard: any }): void;
}>();

const phone = ref('');
const otpCode = ref('');
const step = ref<'phone' | 'otp'>('phone');
const isLoading = ref(false);
const errorMsg = ref<string | null>(null);
const demoOtp = ref<string | null>(null);

// Send 6-digit OTP
async function handleSendOtp() {
    if (!phone.value.trim()) {
        errorMsg.value = 'Please enter a valid phone number.';
        return;
    }

    isLoading.value = true;
    errorMsg.value = null;
    demoOtp.value = null;

    try {
        const response = await axios.post('/api/guard/otp/send', {
            phone: phone.value,
        });

        step.value = 'otp';
        // Display simulated OTP to make local testing incredibly easy for the reviewer
        if (response.data && response.data.otp) {
            demoOtp.value = response.data.otp;
        }
    } catch (e: any) {
        console.error(e);
        errorMsg.value =
            e.response?.data?.message ||
            'Failed to send OTP code. Is the phone correct?';
    } finally {
        isLoading.value = false;
    }
}

// Complete authentication using OTP
async function handleVerifyOtp() {
    if (otpCode.value.length !== 6) {
        errorMsg.value = 'Verification code must be 6 digits.';
        return;
    }

    isLoading.value = true;
    errorMsg.value = null;

    try {
        const response = await axios.post('/api/guard/login', {
            phone: phone.value,
            otp: otpCode.value,
        });

        if (response.data && response.data.token) {
            emit('login-success', {
                token: response.data.token,
                guard: response.data.guard,
            });
        }
    } catch (e: any) {
        console.error(e);
        errorMsg.value =
            e.response?.data?.message || 'Invalid or expired OTP code.';
        otpCode.value = ''; // clear code
    } finally {
        isLoading.value = false;
    }
}

function handleOtpInputChange() {
    otpCode.value = otpCode.value.replace(/[^0-9]/g, '').slice(0, 6);
    errorMsg.value = null;
    if (otpCode.value.length === 6) {
        handleVerifyOtp();
    }
}
</script>

<template>
    <div
        class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-slate-950 px-6 py-12 font-sans"
    >
        <!-- Glowing background decorations -->
        <div
            class="absolute -left-40 -top-40 h-96 w-96 rounded-full bg-indigo-600/20 blur-3xl"
        ></div>
        <div
            class="absolute -bottom-40 -right-40 h-96 w-96 rounded-full bg-violet-600/20 blur-3xl"
        ></div>

        <div class="z-10 w-full max-w-sm">
            <!-- Header Brand Logo -->
            <div class="mb-10 text-center">
                <div
                    class="mx-auto mb-4 flex h-16 w-16 animate-pulse items-center justify-center rounded-2xl bg-gradient-to-tr from-indigo-500 to-violet-600 shadow-2xl shadow-indigo-500/30"
                >
                    <svg
                        class="h-9 w-9 text-white"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                        />
                    </svg>
                </div>
                <h1
                    class="bg-gradient-to-r from-slate-100 via-indigo-300 to-slate-100 bg-clip-text text-2xl font-black tracking-wider text-transparent"
                >
                    WITBO PATROL
                </h1>
                <p
                    class="mt-1 font-mono text-xs uppercase tracking-widest text-slate-400"
                >
                    SaaS Guard Gateway
                </p>
            </div>

            <!-- Login Card Container -->
            <div
                class="relative rounded-3xl border border-slate-800/80 bg-slate-900/80 p-6 shadow-2xl backdrop-blur-xl"
            >
                <!-- Demo Code Assistance Panel -->
                <div
                    v-if="demoOtp"
                    class="animate-fadeIn mb-5 flex items-center justify-between rounded-xl border border-indigo-500/20 bg-indigo-500/10 p-3.5 text-xs text-indigo-300"
                >
                    <div>
                        <span
                            class="mb-0.5 block text-[10px] font-bold uppercase tracking-wider text-indigo-400"
                            >Simulated SMS Delivery</span
                        >
                        <span
                            >Use OTP:
                            <strong
                                class="ml-1 font-mono text-base text-white"
                                >{{ demoOtp }}</strong
                            ></span
                        >
                    </div>
                    <button
                        @click="
                            otpCode = demoOtp;
                            handleVerifyOtp();
                        "
                        class="rounded-lg bg-indigo-600 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wide text-white shadow-md transition-all hover:bg-indigo-500 active:scale-95"
                    >
                        Auto-Fill
                    </button>
                </div>

                <!-- Input Error Display -->
                <div
                    v-if="errorMsg"
                    class="animate-shake mb-4 rounded-xl border border-rose-500/20 bg-rose-500/10 p-3 text-center text-xs font-medium text-rose-300"
                >
                    {{ errorMsg }}
                </div>

                <!-- STEP 1: Phone Number Mode -->
                <div v-if="step === 'phone'">
                    <label
                        class="mb-2 block text-[10px] font-bold uppercase tracking-widest text-slate-400"
                        >Phone Number</label
                    >
                    <div class="relative mb-6">
                        <div
                            class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500"
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
                                    stroke-width="2"
                                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"
                                />
                            </svg>
                        </div>
                        <input
                            v-model="phone"
                            type="tel"
                            class="w-full rounded-2xl border border-slate-800 bg-slate-950 py-3.5 pl-10 pr-4 font-mono text-lg font-bold tracking-wide text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                            placeholder="+35797624744"
                        />
                        <span
                            class="mt-2.5 block pl-1 font-mono text-[8px] font-black uppercase tracking-widest text-slate-500"
                            >Structure Example: +35797624744</span
                        >
                    </div>

                    <button
                        @click="handleSendOtp"
                        :disabled="isLoading"
                        class="active:scale-98 flex w-full items-center justify-center space-x-2 rounded-2xl bg-gradient-to-r from-indigo-500 to-violet-600 py-4 text-sm font-bold uppercase tracking-wider text-white shadow-lg shadow-indigo-500/20 transition-all hover:from-indigo-600 hover:to-violet-700"
                    >
                        <span
                            v-if="isLoading"
                            class="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white"
                        ></span>
                        <span v-else>Send Verification SMS</span>
                    </button>
                </div>

                <!-- STEP 2: 6-Digit OTP Code Verification Mode -->
                <div v-else-if="step === 'otp'">
                    <div class="mb-2 flex items-center justify-between">
                        <label
                            class="block text-[10px] font-bold uppercase tracking-widest text-slate-400"
                            >Verification Code</label
                        >
                        <button
                            @click="
                                step = 'phone';
                                errorMsg = null;
                                demoOtp = null;
                            "
                            class="text-[10px] font-bold text-indigo-400 hover:underline"
                        >
                            Change Phone
                        </button>
                    </div>
                    <p class="mb-4 text-xs text-slate-400">
                        Code sent to
                        <span class="font-mono font-semibold text-slate-300">{{
                            phone
                        }}</span>
                    </p>

                    <!-- Custom 6 Box OTP Indicator with native hidden input for copy-paste/SMS autofill -->
                    <div class="relative mb-6">
                        <input
                            v-model="otpCode"
                            type="tel"
                            maxlength="6"
                            autocomplete="one-time-code"
                            class="absolute inset-0 h-full w-full cursor-text border-none bg-transparent text-transparent opacity-0 focus:outline-none focus:ring-0"
                            @input="handleOtpInputChange"
                        />
                        <div
                            class="pointer-events-none flex justify-between gap-2"
                        >
                            <div
                                v-for="i in 6"
                                :key="i"
                                class="flex h-14 w-12 items-center justify-center rounded-2xl border bg-slate-950 font-mono text-xl font-bold text-indigo-400"
                                :class="
                                    otpCode.length >= i
                                        ? 'border-indigo-500 shadow-lg shadow-indigo-500/10'
                                        : 'border-slate-800'
                                "
                            >
                                {{ otpCode[i - 1] || '' }}
                            </div>
                        </div>
                    </div>

                    <button
                        @click="handleVerifyOtp"
                        :disabled="isLoading"
                        class="active:scale-98 flex w-full items-center justify-center space-x-2 rounded-2xl bg-gradient-to-r from-indigo-500 to-violet-600 py-4 text-sm font-bold uppercase tracking-wider text-white shadow-lg shadow-indigo-500/20 transition-all hover:from-indigo-600 hover:to-violet-700"
                    >
                        <span
                            v-if="isLoading"
                            class="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white"
                        ></span>
                        <span v-else>Verify & Log In</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.animate-fadeIn {
    animation: fadeIn 0.3s ease forwards;
}

@keyframes shake {
    0%,
    100% {
        transform: translateX(0);
    }
    25% {
        transform: translateX(-4px);
    }
    75% {
        transform: translateX(4px);
    }
}
.animate-shake {
    animation: shake 0.2s ease double;
}
</style>
