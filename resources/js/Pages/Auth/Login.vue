<template>
  <div class="min-h-screen flex flex-col justify-center items-center bg-gray-50 dark:bg-[#0B1120] text-gray-900 dark:text-slate-100 px-4 pt-16 pb-8 font-sans transition-colors relative">
    <!-- Mode terang/gelap -->
    <button
      type="button"
      @click="toggleDarkMode"
      class="absolute top-4 right-4 p-2 rounded-lg text-gray-500 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 transition cursor-pointer"
      :title="isDark ? 'Beralih ke Mode Terang' : 'Beralih ke Mode Gelap'"
      aria-label="Ganti mode terang/gelap"
    >
      <Sun v-if="isDark" class="w-4 h-4" />
      <Moon v-else class="w-4 h-4" />
    </button>

    <div class="w-full max-w-sm space-y-4 transition duration-200 ease-out" :class="isLoginSuccess && 'opacity-0 -translate-y-1'">
      <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm p-6 sm:p-7">
        <!-- Judul -->
        <div class="mb-6">
          <h1 class="text-lg font-semibold tracking-tight text-gray-950 dark:text-white">
            {{ loginMode === 'email' ? 'Supply Chain Management' : (otpStep === 'phone' ? 'Masuk dengan WhatsApp' : 'Verifikasi kode OTP') }}
          </h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">
            {{ loginMode === 'email' ? 'Masuk untuk melanjutkan.' : (otpStep === 'phone' ? 'Masukkan nomor WhatsApp yang terdaftar.' : `Kode OTP dikirim ke ${waPhone}.`) }}
          </p>
        </div>

        <!-- Pesan -->
        <div
          v-if="errorMessage"
          class="mb-4 rounded-lg border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-950/40 px-3 py-2.5 text-xs text-red-700 dark:text-red-300 flex items-start justify-between gap-3"
        >
          <span>{{ errorMessage }}</span>
          <button type="button" @click="errorMessage = ''" class="text-red-400 hover:text-red-700 dark:hover:text-red-200 cursor-pointer leading-none" aria-label="Tutup">&times;</button>
        </div>
        <div
          v-if="successMessage"
          class="mb-4 rounded-lg border border-emerald-200 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/40 px-3 py-2.5 text-xs text-emerald-700 dark:text-emerald-300 flex items-start justify-between gap-3"
        >
          <span>{{ successMessage }}</span>
          <button type="button" @click="successMessage = ''" class="text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 cursor-pointer leading-none" aria-label="Tutup">&times;</button>
        </div>

        <!-- =================== MASUK DENGAN EMAIL & PASSWORD =================== -->
        <form v-if="loginMode === 'email'" @submit.prevent="handleEmailSubmit" class="space-y-4">
          <div>
            <label for="username-or-email" class="block text-xs font-medium text-gray-700 dark:text-slate-300 mb-1.5">Username atau email</label>
            <input
              id="username-or-email"
              v-model="form.login"
              type="text"
              required
              autocomplete="username email"
              placeholder="nama@email.com"
              class="w-full h-10 px-3 rounded-lg bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-1 focus:ring-gray-900 dark:focus:ring-slate-300 focus:border-gray-900 dark:focus:border-slate-300 transition"
            />
          </div>

          <div>
            <label for="password" class="block text-xs font-medium text-gray-700 dark:text-slate-300 mb-1.5">Password</label>
            <div class="relative">
              <input
                id="password"
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                required
                autocomplete="current-password"
                placeholder="••••••••"
                class="w-full h-10 pl-3 pr-10 rounded-lg bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-1 focus:ring-gray-900 dark:focus:ring-slate-300 focus:border-gray-900 dark:focus:border-slate-300 transition"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute inset-y-0 right-0 w-10 flex items-center justify-center text-gray-400 hover:text-gray-700 dark:hover:text-white cursor-pointer"
                tabindex="-1"
                :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
              >
                <Eye v-if="!showPassword" class="h-4 w-4" />
                <EyeOff v-else class="h-4 w-4" />
              </button>
            </div>
          </div>

          <label for="remember" class="flex items-center gap-2 text-sm text-gray-600 dark:text-slate-400 cursor-pointer select-none">
            <input
              id="remember"
              v-model="form.remember"
              type="checkbox"
              class="h-4 w-4 rounded border-gray-300 dark:border-slate-600 dark:bg-slate-800 text-gray-900 focus:ring-0 cursor-pointer"
            />
            Ingat saya
          </label>

          <button
            type="submit"
            :disabled="loading || isLoginSuccess"
            class="w-full h-10 rounded-lg bg-gray-900 hover:bg-gray-800 dark:bg-white dark:hover:bg-slate-200 text-white dark:text-gray-900 text-sm font-medium transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-60"
          >
            <span v-if="loading" class="h-4 w-4 border-2 border-white/30 dark:border-gray-900/30 border-t-white dark:border-t-gray-900 rounded-full animate-spin"></span>
            <span>{{ loading ? 'Memproses...' : 'Masuk' }}</span>
          </button>

          <div class="flex items-center gap-3 text-xs text-gray-400 dark:text-slate-500">
            <div class="h-px flex-1 bg-gray-200 dark:bg-slate-800"></div>
            <span>atau</span>
            <div class="h-px flex-1 bg-gray-200 dark:bg-slate-800"></div>
          </div>

          <button
            type="button"
            @click="switchToWhatsApp"
            class="w-full h-10 rounded-lg border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-gray-50 dark:hover:bg-slate-800 text-gray-700 dark:text-slate-200 text-sm font-medium transition cursor-pointer"
          >
            Masuk dengan WhatsApp
          </button>
        </form>

        <!-- =================== MASUK DENGAN WHATSAPP (OTP) =================== -->
        <div v-else>
          <!-- Langkah 1: nomor WhatsApp -->
          <form v-if="otpStep === 'phone'" @submit.prevent="handleSendOtp" class="space-y-4">
            <div>
              <label for="wa-phone" class="block text-xs font-medium text-gray-700 dark:text-slate-300 mb-1.5">Nomor WhatsApp</label>
              <input
                id="wa-phone"
                v-model="waPhone"
                type="tel"
                required
                autofocus
                placeholder="081234567890"
                class="w-full h-10 px-3 rounded-lg bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-1 focus:ring-gray-900 dark:focus:ring-slate-300 focus:border-gray-900 dark:focus:border-slate-300 transition"
              />
            </div>

            <button
              type="submit"
              :disabled="loading || !waPhone"
              class="w-full h-10 rounded-lg bg-gray-900 hover:bg-gray-800 dark:bg-white dark:hover:bg-slate-200 text-white dark:text-gray-900 text-sm font-medium transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
            >
              <span v-if="loading" class="h-4 w-4 border-2 border-white/30 dark:border-gray-900/30 border-t-white dark:border-t-gray-900 rounded-full animate-spin"></span>
              <span>{{ loading ? 'Mengirim kode...' : 'Kirim kode OTP' }}</span>
            </button>

            <button type="button" @click="switchToEmail" class="w-full text-center text-sm text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white cursor-pointer">
              Kembali ke masuk dengan email
            </button>
          </form>

          <!-- Langkah 2: kode OTP -->
          <form v-if="otpStep === 'otp'" @submit.prevent="handleVerifyOtp" class="space-y-4">
            <div>
              <label for="wa-otp" class="block text-xs font-medium text-gray-700 dark:text-slate-300 mb-1.5">Kode OTP (6 digit)</label>
              <input
                id="wa-otp"
                v-model="waOtp"
                type="text"
                inputmode="numeric"
                maxlength="6"
                required
                autofocus
                placeholder="000000"
                @input="onOtpInput"
                class="w-full h-11 rounded-lg bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 text-center text-lg font-semibold tracking-[0.5em] text-gray-900 dark:text-white placeholder:text-gray-300 dark:placeholder:text-slate-600 focus:outline-none focus:ring-1 focus:ring-gray-900 dark:focus:ring-slate-300 focus:border-gray-900 dark:focus:border-slate-300 transition"
              />
            </div>

            <button
              type="submit"
              :disabled="loading || cleanOtpLength !== 6 || isLoginSuccess"
              class="w-full h-10 rounded-lg bg-gray-900 hover:bg-gray-800 dark:bg-white dark:hover:bg-slate-200 text-white dark:text-gray-900 text-sm font-medium transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
            >
              <span v-if="loading" class="h-4 w-4 border-2 border-white/30 dark:border-gray-900/30 border-t-white dark:border-t-gray-900 rounded-full animate-spin"></span>
              <span>{{ loading ? 'Memverifikasi...' : 'Verifikasi & masuk' }}</span>
            </button>

            <div class="flex items-center justify-between text-sm">
              <button
                type="button"
                @click="otpStep = 'phone'; waOtp = ''; errorMessage = ''; successMessage = ''"
                class="text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white cursor-pointer"
              >
                Ganti nomor
              </button>
              <button
                type="button"
                @click="handleSendOtp"
                :disabled="resendCooldown > 0 || loading"
                class="text-gray-900 dark:text-white font-medium hover:underline cursor-pointer disabled:text-gray-400 disabled:no-underline disabled:cursor-default"
              >
                {{ resendCooldown > 0 ? `Kirim ulang (${resendCooldown}s)` : 'Kirim ulang kode' }}
              </button>
            </div>

            <button type="button" @click="switchToEmail" class="w-full text-center text-sm text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white cursor-pointer">
              Kembali ke masuk dengan email
            </button>
          </form>
        </div>
      </div>

      <p class="text-center text-sm text-gray-500 dark:text-slate-400">
        Belum punya akun?
        <router-link to="/register" class="font-medium text-gray-900 dark:text-white hover:underline">Daftar</router-link>
      </p>
      <p class="text-center text-xs text-gray-400 dark:text-slate-500">
        &copy; {{ new Date().getFullYear() }} Supply Chain Management
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuth } from '@/composables/useAuth';
import { useTheme } from '@/composables/useTheme';
import { Eye, EyeOff, Moon, Sun } from 'lucide-vue-next';
import axios from 'axios';

const route = useRoute();
const router = useRouter();
const { login, verifyWaOtp, checkAuth } = useAuth();
const { isDark, toggleDarkMode, initTheme } = useTheme();

// Login mode: 'email' (standard) or 'wa' (WhatsApp OTP)
const loginMode = ref('email');
// WA OTP step: 'phone' or 'otp'
const otpStep = ref('phone');

const form = reactive({
  login: '',
  password: '',
  remember: false,
});

const waPhone = ref('');
const waOtp = ref('');

const loading = ref(false);
const isLoginSuccess = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const showPassword = ref(false);
const resendCooldown = ref(0);
let cooldownTimer = null;

const cleanOtpLength = computed(() => {
  return (waOtp.value || '').replace(/\D/g, '').length;
});

onMounted(() => {
  initTheme();
  const savedPhone = sessionStorage.getItem('scm_wa_phone');
  if (savedPhone) {
    waPhone.value = savedPhone;
  }
});

function switchToWhatsApp() {
  loginMode.value = 'wa';
  errorMessage.value = '';
  successMessage.value = '';
}

function switchToEmail() {
  loginMode.value = 'email';
  otpStep.value = 'phone';
  waOtp.value = '';
  errorMessage.value = '';
  successMessage.value = '';
}

function onOtpInput(event) {
  waOtp.value = (event.target.value || '').replace(/\D/g, '').slice(0, 6);
  if (waOtp.value.length === 6) {
    handleVerifyOtp();
  }
}

// Berhasil masuk: tombol tetap "Memproses...", kartu memudar singkat lalu pindah ke halaman tujuan
// (halaman yang tadi diminta sebelum diarahkan ke login, atau dashboard)
async function triggerSuccessRedirect() {
  errorMessage.value = '';
  successMessage.value = '';

  try {
    await checkAuth();
  } catch (e) {
    // proceed anyway
  }

  isLoginSuccess.value = true;
  const redirect = route.query.redirect;
  const target = typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//') ? redirect : '/dashboard';

  setTimeout(() => {
    router.replace(target);
  }, 200);
}

// ===== 1. EMAIL & PASSWORD SUBMIT =====
async function handleEmailSubmit() {
  loading.value = true;
  errorMessage.value = '';
  successMessage.value = '';

  try {
    const payload = {
      login: form.login.trim(),
      password: form.password,
      remember: form.remember,
    };

    const res = await login(payload);
    if (res?.success) {
      await triggerSuccessRedirect();
    }
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Gagal masuk. Periksa kembali username/email dan password.';
  } finally {
    if (!isLoginSuccess.value) {
      loading.value = false;
    }
  }
}

// ===== 2. WHATSAPP OTP: SEND =====
async function handleSendOtp() {
  const cleanPhone = waPhone.value.trim().replace(/\s+/g, '');
  if (!cleanPhone) {
    errorMessage.value = 'Silakan masukkan nomor WhatsApp Anda.';
    return;
  }

  loading.value = true;
  errorMessage.value = '';
  successMessage.value = '';

  try {
    sessionStorage.setItem('scm_wa_phone', cleanPhone);

    const res = await axios.post('/api/auth/wa-otp/send', {
      whatsapp: cleanPhone,
    });

    if (res.data?.success) {
      otpStep.value = 'otp';
      waOtp.value = '';
      successMessage.value = res.data.message || 'Kode OTP telah dikirim ke WhatsApp Anda.';
      startResendCooldown(60);
    } else {
      errorMessage.value = res.data?.message || 'Gagal mengirim OTP ke nomor tersebut.';
    }
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Gagal mengirim OTP. Pastikan nomor aktif atau coba lagi nanti.';
  } finally {
    loading.value = false;
  }
}

// ===== 3. WHATSAPP OTP: VERIFY =====
async function handleVerifyOtp() {
  const cleanDigits = (waOtp.value || '').replace(/\D/g, '');
  if (cleanDigits.length !== 6) {
    errorMessage.value = 'Masukkan 6 digit kode OTP.';
    return;
  }

  loading.value = true;
  errorMessage.value = '';
  successMessage.value = '';

  try {
    const payload = {
      whatsapp: waPhone.value.trim(),
      otp: cleanDigits,
    };

    const res = await verifyWaOtp(payload);

    if (res?.success) {
      sessionStorage.removeItem('scm_wa_phone');
      await triggerSuccessRedirect();
    } else {
      errorMessage.value = res?.message || 'Kode OTP tidak cocok.';
    }
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Terjadi kesalahan saat memverifikasi kode OTP.';
  } finally {
    if (!isLoginSuccess.value) {
      loading.value = false;
    }
  }
}

function startResendCooldown(seconds = 60) {
  if (cooldownTimer) clearInterval(cooldownTimer);
  resendCooldown.value = seconds;
  cooldownTimer = setInterval(() => {
    resendCooldown.value--;
    if (resendCooldown.value <= 0) {
      clearInterval(cooldownTimer);
      cooldownTimer = null;
    }
  }, 1000);
}
</script>
