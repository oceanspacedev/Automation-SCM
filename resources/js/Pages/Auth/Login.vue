<template>
  <div class="min-h-screen flex flex-col justify-center items-center bg-white px-4 py-12 sm:px-6 font-sans">
    <div class="w-full max-w-[380px] space-y-6">
      <!-- Logo & Header -->
      <div class="text-center space-y-2">
        <img
          src="/images/scm-logo.png"
          alt="SCM Supply Chain Management"
          class="h-16 w-auto max-w-[160px] mx-auto object-contain"
        />
        <h1 class="text-2xl font-bold tracking-tight text-neutral-900">
          Masuk ke Akun
        </h1>
        <p class="text-sm text-neutral-500">
          {{ activeTab === 'email' ? 'Masukkan email dan password untuk melanjutkan' : (otpStep === 'phone' ? 'Masukkan nomor WhatsApp Anda' : 'Masukkan kode OTP yang dikirim via WhatsApp') }}
        </p>
      </div>

      <!-- Error Message -->
      <div
        v-if="errorMessage"
        class="rounded-lg border border-neutral-300 bg-neutral-100 p-3 text-sm text-neutral-800 flex items-center justify-between"
      >
        <span>{{ errorMessage }}</span>
        <button
          type="button"
          @click="errorMessage = ''"
          class="text-neutral-500 hover:text-neutral-900 cursor-pointer font-bold ml-2"
        >
          &times;
        </button>
      </div>

      <!-- Success Message -->
      <div
        v-if="successMessage"
        class="rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-800"
      >
        {{ successMessage }}
      </div>

      <!-- Tab Switcher -->
      <div class="flex rounded-lg border border-neutral-200 p-1 gap-1 bg-neutral-50">
        <button
          type="button"
          @click="switchTab('email')"
          :class="[
            'flex-1 h-8 text-xs font-medium rounded-md transition',
            activeTab === 'email'
              ? 'bg-white text-neutral-900 shadow-sm border border-neutral-200'
              : 'text-neutral-500 hover:text-neutral-700 cursor-pointer'
          ]"
        >
          Email & Password
        </button>
        <button
          type="button"
          @click="switchTab('wa')"
          :class="[
            'flex-1 h-8 text-xs font-medium rounded-md transition flex items-center justify-center gap-1.5',
            activeTab === 'wa'
              ? 'bg-white text-[#128C7E] shadow-sm border border-neutral-200'
              : 'text-neutral-500 hover:text-neutral-700 cursor-pointer'
          ]"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-current"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
          WhatsApp OTP
        </button>
      </div>

      <!-- EMAIL FORM -->
      <form v-if="activeTab === 'email'" @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label for="email" class="block text-sm font-medium text-neutral-700 mb-1">Email</label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            required
            autocomplete="email"
            placeholder="nama@email.com"
            class="h-10 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 placeholder:text-neutral-400 focus:outline-none focus:ring-1 focus:ring-black focus:border-black transition"
          />
        </div>

        <div>
          <label for="password" class="block text-sm font-medium text-neutral-700 mb-1">Password</label>
          <div class="relative">
            <input
              id="password"
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              required
              autocomplete="current-password"
              placeholder="••••••••"
              class="h-10 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 pr-10 text-sm text-neutral-900 placeholder:text-neutral-400 focus:outline-none focus:ring-1 focus:ring-black focus:border-black transition"
            />
            <button
              type="button"
              @click="showPassword = !showPassword"
              class="absolute inset-y-0 right-0 pr-3 flex items-center text-neutral-400 hover:text-neutral-700 cursor-pointer"
              tabindex="-1"
            >
              <EyeIcon v-if="!showPassword" class="h-4 w-4" />
              <EyeOffIcon v-else class="h-4 w-4" />
            </button>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <input
            type="checkbox"
            id="remember"
            v-model="form.remember"
            class="h-4 w-4 rounded border-neutral-300 cursor-pointer"
          />
          <label for="remember" class="text-xs text-neutral-600 cursor-pointer">Ingat saya</label>
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="h-10 w-full rounded-lg bg-[#1D70F5] hover:bg-blue-600 text-white text-sm font-medium transition flex items-center justify-center gap-2 cursor-pointer shadow-xs disabled:opacity-50"
        >
          <span v-if="loading" class="h-4 w-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
          <span>{{ loading ? 'Memproses...' : 'Masuk' }}</span>
        </button>
      </form>

      <!-- WHATSAPP OTP FORM -->
      <div v-if="activeTab === 'wa'" class="space-y-4">

        <!-- Step 1: Input nomor WA -->
        <form v-if="otpStep === 'phone'" @submit.prevent="sendOtp" class="space-y-4">
          <div>
            <label for="wa-phone" class="block text-sm font-medium text-neutral-700 mb-1">
              Nomor WhatsApp
            </label>
            <input
              id="wa-phone"
              v-model="waPhone"
              type="tel"
              required
              placeholder="Contoh: 08xxxxxxxxxx"
              class="h-10 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 placeholder:text-neutral-400 focus:outline-none focus:ring-1 focus:ring-[#25D366] focus:border-[#25D366] transition"
            />
            <p class="text-xs text-neutral-400 mt-1">Nomor yang terdaftar di akun SCM Anda</p>
          </div>

          <button
            type="submit"
            :disabled="loading || !waPhone"
            class="h-10 w-full rounded-lg bg-[#25D366] hover:bg-[#1db855] text-white text-sm font-medium transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
          >
            <span v-if="loading" class="h-4 w-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
            <span>{{ loading ? 'Mengirim OTP...' : 'Kirim Kode OTP' }}</span>
          </button>
        </form>

        <!-- Step 2: Input OTP -->
        <form v-if="otpStep === 'otp'" @submit.prevent="verifyOtp" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">
              Kode OTP
            </label>
            <p class="text-xs text-neutral-500 mb-2">
              Kode 6 digit dikirim ke WhatsApp <strong>{{ waPhone }}</strong>
            </p>
            <input
              v-model="waOtp"
              type="text"
              inputmode="numeric"
              pattern="[0-9]{6}"
              maxlength="6"
              required
              placeholder="000000"
              autofocus
              class="h-12 w-full rounded-lg border border-neutral-300 bg-white px-3 text-center text-2xl font-mono tracking-[0.5em] text-neutral-900 placeholder:text-neutral-300 placeholder:tracking-normal focus:outline-none focus:ring-1 focus:ring-[#25D366] focus:border-[#25D366] transition"
            />
          </div>

          <button
            type="submit"
            :disabled="loading || waOtp.length !== 6"
            class="h-10 w-full rounded-lg bg-[#25D366] hover:bg-[#1db855] text-white text-sm font-medium transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
          >
            <span v-if="loading" class="h-4 w-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
            <span>{{ loading ? 'Memverifikasi...' : 'Verifikasi & Masuk' }}</span>
          </button>

          <div class="flex items-center justify-between text-xs text-neutral-500">
            <button
              type="button"
              @click="otpStep = 'phone'; waOtp = ''; errorMessage = ''; successMessage = ''"
              class="underline hover:text-neutral-700 cursor-pointer"
            >
              ← Ganti nomor
            </button>
            <button
              type="button"
              @click="sendOtp"
              :disabled="resendCooldown > 0 || loading"
              class="underline hover:text-neutral-700 cursor-pointer disabled:opacity-40 disabled:cursor-default"
            >
              {{ resendCooldown > 0 ? `Kirim ulang (${resendCooldown}s)` : 'Kirim ulang OTP' }}
            </button>
          </div>
        </form>
      </div>

      <div class="text-center text-xs text-neutral-600 pt-2 border-t border-neutral-100">
        Belum punya akun?
        <router-link to="/register" class="font-semibold text-[#1D70F5] hover:underline ml-1">
          Daftar di sini
        </router-link>
      </div>

      <div class="text-center text-xs text-neutral-400">
        &copy; {{ new Date().getFullYear() }} SCM
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useAuth } from '@/composables/useAuth';
import { Eye as EyeIcon, EyeOff as EyeOffIcon } from 'lucide-vue-next';
import axios from 'axios';

const router = useRouter();
const { login } = useAuth();

// Tab: 'email' | 'wa'
const activeTab = ref('email');
// WA OTP step: 'phone' | 'otp'
const otpStep = ref('phone');

const form = reactive({ email: '', password: '', remember: false });
const waPhone = ref('');
const waOtp = ref('');

const loading = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const showPassword = ref(false);
const resendCooldown = ref(0);

function switchTab(tab) {
  activeTab.value = tab;
  errorMessage.value = '';
  successMessage.value = '';
  otpStep.value = 'phone';
  waOtp.value = '';
}

// ===== EMAIL LOGIN =====
async function handleSubmit() {
  loading.value = true;
  errorMessage.value = '';
  try {
    await login({ email: form.email, password: form.password, remember: form.remember });
    router.push('/dashboard');
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Gagal masuk. Periksa kembali email dan password.';
  } finally {
    loading.value = false;
  }
}

// ===== WHATSAPP OTP =====
async function sendOtp() {
  if (!waPhone.value) return;
  loading.value = true;
  errorMessage.value = '';
  successMessage.value = '';
  try {
    const res = await axios.post('/api/auth/wa-otp/send', { whatsapp: waPhone.value });
    if (res.data?.success) {
      otpStep.value = 'otp';
      waOtp.value = '';
      successMessage.value = res.data.message || 'Kode OTP dikirim ke WhatsApp Anda.';
      startResendCooldown();
    } else {
      errorMessage.value = res.data?.message || 'Gagal mengirim OTP.';
    }
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Terjadi kesalahan. Coba lagi.';
  } finally {
    loading.value = false;
  }
}

async function verifyOtp() {
  if (waOtp.value.length !== 6) return;
  loading.value = true;
  errorMessage.value = '';
  successMessage.value = '';
  try {
    const res = await axios.post('/api/auth/wa-otp/verify', {
      whatsapp: waPhone.value,
      otp: waOtp.value,
    });
    if (res.data?.success) {
      router.push('/dashboard');
    } else {
      errorMessage.value = res.data?.message || 'Kode OTP salah.';
    }
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Terjadi kesalahan. Coba lagi.';
  } finally {
    loading.value = false;
  }
}

function startResendCooldown(seconds = 60) {
  resendCooldown.value = seconds;
  const timer = setInterval(() => {
    resendCooldown.value--;
    if (resendCooldown.value <= 0) clearInterval(timer);
  }, 1000);
}
</script>
