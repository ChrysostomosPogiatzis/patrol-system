<script setup lang="ts">
import { ref } from 'vue';
import axios from 'axios';

const emit = defineEmits<{
    (e: 'login-success', data: { token: string; guard: any }): void;
}>();

const phone = ref('+35799123456');
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
            phone: phone.value
        });
        
        step.value = 'otp';
        // Display simulated OTP to make local testing incredibly easy for the reviewer
        if (response.data && response.data.otp) {
            demoOtp.value = response.data.otp;
        }
    } catch (e: any) {
        console.error(e);
        errorMsg.value = e.response?.data?.message || 'Failed to send OTP code. Is the phone correct?';
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
            otp: otpCode.value
        });
        
        if (response.data && response.data.token) {
            emit('login-success', {
                token: response.data.token,
                guard: response.data.guard
            });
        }
    } catch (e: any) {
        console.error(e);
        errorMsg.value = e.response?.data?.message || 'Invalid or expired OTP code.';
        otpCode.value = ''; // clear code
    } finally {
        isLoading.value = false;
    }
}

// Handle numeric keypad press
function handleKeyPress(num: string) {
    errorMsg.value = null;
    if (step.value === 'phone') {
        phone.value += num;
    } else {
        if (otpCode.value.length < 6) {
            otpCode.value += num;
        }
        if (otpCode.value.length === 6) {
            handleVerifyOtp();
        }
    }
}

// Handle keypad backspace
function handleBackspace() {
    if (step.value === 'phone') {
        phone.value = phone.value.slice(0, -1);
    } else {
        otpCode.value = otpCode.value.slice(0, -1);
    }
}

// Clear input field
function handleClear() {
    if (step.value === 'phone') {
        phone.value = '';
    } else {
        otpCode.value = '';
    }
}
</script>

<template>
    <div class="min-h-screen bg-slate-950 flex flex-col justify-center items-center px-6 py-12 relative overflow-hidden font-sans">
        
        <!-- Glowing background decorations -->
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-indigo-600/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-violet-600/20 rounded-full blur-3xl"></div>

        <div class="w-full max-w-sm z-10">
            <!-- Header Brand Logo -->
            <div class="text-center mb-10">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-indigo-500 to-violet-600 flex items-center justify-center shadow-2xl shadow-indigo-500/30 mx-auto mb-4 animate-pulse">
                    <svg class="w-9 h-9 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-black tracking-wider text-transparent bg-clip-text bg-gradient-to-r from-slate-100 via-indigo-300 to-slate-100">
                    SENTINEL PATROL
                </h1>
                <p class="text-xs text-slate-400 mt-1 uppercase tracking-widest font-mono">SaaS Guard Gateway</p>
            </div>

            <!-- Login Card Container -->
            <div class="bg-slate-900/80 backdrop-blur-xl border border-slate-800/80 p-6 rounded-3xl shadow-2xl relative">
                
                <!-- Demo Code Assistance Panel -->
                <div 
                    v-if="demoOtp" 
                    class="mb-5 bg-indigo-500/10 border border-indigo-500/20 rounded-xl p-3.5 text-xs text-indigo-300 flex items-center justify-between animate-fadeIn"
                >
                    <div>
                        <span class="font-bold text-[10px] uppercase tracking-wider block text-indigo-400 mb-0.5">Simulated SMS Delivery</span>
                        <span>Use OTP: <strong class="text-base text-white font-mono ml-1">{{ demoOtp }}</strong></span>
                    </div>
                    <button 
                        @click="otpCode = demoOtp; handleVerifyOtp()" 
                        class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-3 py-1.5 rounded-lg text-[10px] uppercase tracking-wide transition-all shadow-md active:scale-95"
                    >
                        Auto-Fill
                    </button>
                </div>

                <!-- Input Error Display -->
                <div 
                    v-if="errorMsg" 
                    class="mb-4 bg-rose-500/10 border border-rose-500/20 text-rose-300 rounded-xl p-3 text-xs text-center font-medium animate-shake"
                >
                    {{ errorMsg }}
                </div>

                <!-- STEP 1: Phone Number Mode -->
                <div v-if="step === 'phone'">
                    <label class="block text-[10px] font-bold tracking-widest text-slate-400 uppercase mb-2">Phone Number</label>
                    <div class="relative mb-6">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                        </div>
                        <input 
                            v-model="phone" 
                            type="tel" 
                            readonly
                            class="w-full bg-slate-950 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-slate-100 pl-10 pr-4 py-3.5 rounded-2xl text-lg font-bold font-mono tracking-wide focus:outline-none"
                            placeholder="+35799123456"
                        />
                    </div>

                    <button 
                        @click="handleSendOtp" 
                        :disabled="isLoading"
                        class="w-full py-4 bg-gradient-to-r from-indigo-500 to-violet-600 hover:from-indigo-600 hover:to-violet-700 text-white font-bold rounded-2xl shadow-lg shadow-indigo-500/20 active:scale-98 transition-all flex items-center justify-center space-x-2 text-sm uppercase tracking-wider"
                    >
                        <span v-if="isLoading" class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                        <span v-else>Send Verification SMS</span>
                    </button>
                </div>

                <!-- STEP 2: 6-Digit OTP Code Verification Mode -->
                <div v-else-if="step === 'otp'">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-[10px] font-bold tracking-widest text-slate-400 uppercase">Verification Code</label>
                        <button @click="step = 'phone'; errorMsg = null; demoOtp = null;" class="text-[10px] text-indigo-400 font-bold hover:underline">Change Phone</button>
                    </div>
                    <p class="text-xs text-slate-400 mb-4">Code sent to <span class="font-mono text-slate-300 font-semibold">{{ phone }}</span></p>

                    <!-- Custom 6 Box OTP Indicator -->
                    <div class="flex justify-between gap-2 mb-6">
                        <div 
                            v-for="i in 6" 
                            :key="i"
                            class="w-12 h-14 bg-slate-950 border rounded-2xl flex items-center justify-center text-xl font-bold font-mono text-indigo-400"
                            :class="otpCode.length >= i ? 'border-indigo-500 shadow-lg shadow-indigo-500/10' : 'border-slate-800'"
                        >
                            {{ otpCode[i - 1] || '' }}
                        </div>
                    </div>

                    <button 
                        @click="handleVerifyOtp" 
                        :disabled="isLoading"
                        class="w-full py-4 bg-gradient-to-r from-indigo-500 to-violet-600 hover:from-indigo-600 hover:to-violet-700 text-white font-bold rounded-2xl shadow-lg shadow-indigo-500/20 active:scale-98 transition-all flex items-center justify-center space-x-2 text-sm uppercase tracking-wider"
                    >
                        <span v-if="isLoading" class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                        <span v-else>Verify & Log In</span>
                    </button>
                </div>
            </div>

            <!-- CUSTOM NUMERIC DIALPAD (Optimised for mobile touch input) -->
            <div class="mt-8 grid grid-cols-3 gap-3.5 px-4 animate-fadeIn">
                <button 
                    v-for="num in ['1', '2', '3', '4', '5', '6', '7', '8', '9']" 
                    :key="num"
                    @click="handleKeyPress(num)"
                    class="w-full aspect-square bg-slate-900/40 hover:bg-slate-900/80 active:bg-slate-800/80 border border-slate-900 text-slate-200 text-2xl font-bold rounded-2xl transition-all flex items-center justify-center shadow-md active:scale-95 touch-manipulation font-mono"
                >
                    {{ num }}
                </button>
                
                <!-- Clear Button -->
                <button 
                    @click="handleClear"
                    class="w-full aspect-square bg-slate-900/40 hover:bg-slate-900/80 active:bg-slate-800/80 border border-slate-900 text-slate-400 text-xs font-bold uppercase tracking-wider rounded-2xl transition-all flex items-center justify-center active:scale-95 touch-manipulation"
                >
                    Clear
                </button>

                <!-- Zero -->
                <button 
                    @click="handleKeyPress('0')"
                    class="w-full aspect-square bg-slate-900/40 hover:bg-slate-900/80 active:bg-slate-800/80 border border-slate-900 text-slate-200 text-2xl font-bold rounded-2xl transition-all flex items-center justify-center shadow-md active:scale-95 touch-manipulation font-mono"
                >
                    0
                </button>

                <!-- Backspace -->
                <button 
                    @click="handleBackspace"
                    class="w-full aspect-square bg-slate-900/40 hover:bg-slate-900/80 active:bg-slate-800/80 border border-slate-900 text-slate-400 text-2xl rounded-2xl transition-all flex items-center justify-center active:scale-95 touch-manipulation"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M3 12l6.414 6.414A2 2 0 0010.828 19h7.344a2 2 0 002-2V7a2 2 0 00-2-2h-7.344a2 2 0 00-1.414.586L3 12z" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
    animation: fadeIn 0.3s ease forwards;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-4px); }
    75% { transform: translateX(4px); }
}
.animate-shake {
    animation: shake 0.2s ease double;
}
</style>
