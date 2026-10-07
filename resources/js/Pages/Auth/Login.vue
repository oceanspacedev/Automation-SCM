<template>
  <div class="min-h-screen flex flex-col justify-center items-center bg-[#F9FAFB] dark:bg-[#0B1120] text-neutral-900 dark:text-slate-100 px-4 py-3 sm:py-6 font-sans transition-colors relative">

    <!-- Subtle Quick Theme Toggle (Top Right) -->
    <button
      type="button"
      @click="toggleDarkMode"
      class="absolute top-4 right-4 p-2 rounded-lg border border-neutral-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 text-neutral-600 dark:text-slate-300 hover:bg-neutral-100 dark:hover:bg-slate-800 transition cursor-pointer shadow-2xs"
      :title="isDark ? 'Beralih ke Mode Terang' : 'Beralih ke Mode Gelap'"
      aria-label="Toggle Dark Mode"
    >
      <Sun v-if="isDark" class="w-4 h-4 text-amber-400" />
      <Moon v-else class="w-4 h-4 text-neutral-600" />
    </button>

    <div class="w-full max-w-[350px] space-y-3">

      <!-- SCM Logo Brand -->
      <div class="text-center pt-0.5">
        <img
          src="/images/scm-logo.png"
          alt="SCM"
          class="h-9 sm:h-10 w-auto mx-auto object-contain drop-shadow-2xs"
        />
      </div>

      <!-- Main Login Card (Compact, Clean Dark Mode) -->
      <div class="bg-white dark:bg-slate-900 rounded-xl shadow-[0_4px_20px_rgba(0,0,0,0.06)] dark:shadow-[0_4px_25px_rgba(0,0,0,0.35)] border border-neutral-200/80 dark:border-slate-800 p-5 sm:p-6 transition-colors">

        <!-- Header Title (Clean without top icon box) -->
        <div class="text-center mb-4">
            <h2 class="text-base sm:text-lg font-bold tracking-tight text-neutral-900 dark:text-white">
              {{ loginMode === 'email' ? 'Sign in' : (otpStep === 'phone' ? 'Masuk via WhatsApp' : 'Verifikasi OTP WhatsApp') }}
            </h2>
            <p v-if="loginMode === 'wa'" class="text-[11px] text-neutral-500 dark:text-slate-400 mt-0.5">
              {{ otpStep === 'phone' ? 'Masukkan nomor WA terdaftar di SCM' : `Kode OTP dikirim ke ${waPhone}` }}
            </p>
          </div>

          <!-- Alert Error Message -->
          <div
            v-if="errorMessage"
            class="mb-3 rounded-lg border border-red-200 dark:border-red-900/50 bg-red-50/90 dark:bg-red-950/40 p-2.5 text-[11px] text-red-800 dark:text-red-300 flex items-start justify-between gap-1.5 shadow-2xs"
          >
            <div class="flex items-start gap-1.5">
              <span class="font-bold text-red-600 dark:text-red-400">✕</span>
              <span>{{ errorMessage }}</span>
            </div>
            <button
              type="button"
              @click="errorMessage = ''"
              class="text-red-400 hover:text-red-700 dark:text-red-400 dark:hover:text-red-200 cursor-pointer text-sm leading-none font-bold shrink-0 ml-1"
            >
              &times;
            </button>
          </div>

          <!-- Alert Success Message (e.g. OTP Sent) -->
          <div
            v-if="successMessage"
            class="mb-3 rounded-lg border border-emerald-200 dark:border-emerald-900/60 bg-emerald-50/90 dark:bg-emerald-950/50 p-2.5 text-[11px] text-emerald-800 dark:text-emerald-300 flex items-start justify-between gap-2 shadow-2xs"
          >
            <div class="flex items-start gap-2">
              <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" />
              <span class="leading-snug">{{ successMessage }}</span>
            </div>
            <button
              type="button"
              @click="successMessage = ''"
              class="text-emerald-500 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-200 cursor-pointer text-sm leading-none font-bold shrink-0 ml-1"
            >
              &times;
            </button>
          </div>

        <!-- =================== MODE 1: EMAIL & PASSWORD FORM =================== -->
        <form v-if="loginMode === 'email'" @submit.prevent="handleEmailSubmit" class="space-y-3">
          <!-- Username / Email Field -->
          <div>
            <label for="username-or-email" class="block text-xs font-semibold text-neutral-800 dark:text-slate-200 mb-1">
              Username atau email<span class="text-red-500 font-bold ml-0.5">*</span>
            </label>
            <input
              id="username-or-email"
              v-model="form.login"
              type="text"
              required
              autocomplete="username email"
              placeholder="admin@example.com"
              class="w-full h-9.5 px-3 rounded-lg bg-[#EBF3FC] dark:bg-slate-800/90 border border-[#D5E3F5] dark:border-slate-700 text-neutral-900 dark:text-white text-xs sm:text-sm placeholder:text-neutral-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-[#1D70F5]/30 focus:border-[#1D70F5] transition"
            />
          </div>

          <!-- Password Field with Segmented Eye Toggle -->
          <div>
            <label for="password" class="block text-xs font-semibold text-neutral-800 dark:text-slate-200 mb-1">
              Password<span class="text-red-500 font-bold ml-0.5">*</span>
            </label>
            <div class="flex rounded-lg border border-[#D5E3F5] dark:border-slate-700 bg-[#EBF3FC] dark:bg-slate-800/90 overflow-hidden focus-within:ring-2 focus-within:ring-[#1D70F5]/30 focus-within:border-[#1D70F5] transition">
              <input
                id="password"
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                required
                autocomplete="current-password"
                placeholder="••••••••"
                class="h-9.5 flex-1 bg-transparent px-3 text-xs sm:text-sm text-neutral-900 dark:text-white placeholder:text-neutral-400 dark:placeholder:text-slate-500 focus:outline-none"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="w-10 h-9.5 flex items-center justify-center border-l border-[#D5E3F5] dark:border-slate-700 text-neutral-500 dark:text-slate-400 hover:text-neutral-800 dark:hover:text-white bg-transparent transition cursor-pointer"
                tabindex="-1"
                aria-label="Toggle password visibility"
              >
                <Eye v-if="!showPassword" class="h-4 w-4" />
                <EyeOff v-else class="h-4 w-4" />
              </button>
            </div>
          </div>

          <!-- Remember Me Checkbox -->
          <div class="flex items-center gap-2 pt-0.5">
            <input
              id="remember"
              v-model="form.remember"
              type="checkbox"
              class="h-3.5 w-3.5 rounded border-neutral-300 dark:border-slate-700 dark:bg-slate-800 text-[#1D70F5] focus:ring-[#1D70F5] cursor-pointer"
            />
            <label for="remember" class="text-xs font-medium text-neutral-700 dark:text-slate-300 cursor-pointer select-none">
              Remember me
            </label>
          </div>

          <!-- Blue Sign In Button -->
          <button
            type="submit"
            :disabled="loading || isLoginSuccess"
            class="w-full h-9.5 rounded-lg bg-[#1D70F5] hover:bg-[#155FD1] active:scale-[0.99] text-white font-semibold text-xs sm:text-sm transition flex items-center justify-center gap-2 cursor-pointer shadow-xs disabled:opacity-60"
          >
            <span v-if="loading && !isLoginSuccess" class="h-3.5 w-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
            <Check v-else-if="isLoginSuccess" class="h-4 w-4 text-white" />
            <span>{{ isLoginSuccess ? 'Berhasil masuk...' : (loading ? 'Signing in...' : 'Sign in') }}</span>
          </button>

          <!-- Divider: ATAU MASUK DENGAN -->
          <div class="relative flex items-center justify-center my-3 pt-0.5">
            <div class="border-t border-neutral-200/90 dark:border-slate-800 w-full"></div>
            <span class="bg-white dark:bg-slate-900 px-2.5 text-[10px] font-bold tracking-wider text-neutral-400 dark:text-slate-500 uppercase whitespace-nowrap absolute">
              ATAU MASUK DENGAN
            </span>
          </div>

          <!-- WhatsApp Button -->
          <button
            type="button"
            @click="switchToWhatsApp"
            class="w-full h-9.5 rounded-lg border border-neutral-200 dark:border-slate-700 bg-white dark:bg-slate-800/90 hover:bg-neutral-50 dark:hover:bg-slate-800 active:scale-[0.99] text-neutral-800 dark:text-slate-200 text-xs sm:text-sm font-semibold transition flex items-center justify-center gap-2 shadow-2xs cursor-pointer"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              class="h-4.5 w-4.5 fill-[#25D366]"
            >
              <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
            <span>WhatsApp</span>
          </button>
        </form>

        <!-- =================== MODE 2: WHATSAPP OTP FLOW =================== -->
        <div v-else class="space-y-3">

          <!-- Step 1: Input Nomor WhatsApp -->
          <form v-if="otpStep === 'phone'" @submit.prevent="handleSendOtp" class="space-y-3">
            <div>
              <label for="wa-phone" class="block text-xs font-semibold text-neutral-800 dark:text-slate-200 mb-1">
                Nomor WhatsApp<span class="text-red-500 font-bold ml-0.5">*</span>
              </label>
              <input
                id="wa-phone"
                v-model="waPhone"
                type="tel"
                required
                autofocus
                placeholder="Contoh: 081234567890"
                class="w-full h-9.5 px-3 rounded-lg bg-[#EBF3FC] dark:bg-slate-800/90 border border-[#D5E3F5] dark:border-slate-700 text-neutral-900 dark:text-white text-xs sm:text-sm placeholder:text-neutral-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-[#25D366]/30 focus:border-[#25D366] transition"
              />
              <p class="text-[11px] text-neutral-500 dark:text-slate-400 mt-1">
                Nomor aktif WhatsApp & terdaftar di sistem SCM.
              </p>
            </div>

            <button
              type="submit"
              :disabled="loading || !waPhone"
              class="w-full h-9.5 rounded-lg bg-[#25D366] hover:bg-[#1ebc59] active:scale-[0.99] text-white font-semibold text-xs sm:text-sm transition flex items-center justify-center gap-2 cursor-pointer shadow-xs disabled:opacity-50"
            >
              <span v-if="loading" class="h-3.5 w-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
              <span>{{ loading ? 'Mengirim OTP...' : 'Kirim Kode OTP' }}</span>
            </button>

            <button
              type="button"
              @click="switchToEmail"
              class="w-full text-center text-xs text-neutral-500 dark:text-slate-400 hover:text-neutral-800 dark:hover:text-white pt-0.5 cursor-pointer font-medium"
            >
              ← Kembali ke Sign in Email
            </button>
          </form>

          <!-- Step 2: Input & Verifikasi Kode OTP -->
          <form v-if="otpStep === 'otp'" @submit.prevent="handleVerifyOtp" class="space-y-3">
            <div>
              <label class="block text-xs font-semibold text-neutral-800 dark:text-slate-200 mb-1">
                Kode OTP (6 Digit)<span class="text-red-500 font-bold ml-0.5">*</span>
              </label>

              <input
                v-model="waOtp"
                type="text"
                inputmode="numeric"
                maxlength="6"
                required
                autofocus
                placeholder="000000"
                @input="onOtpInput"
                class="h-10 w-full rounded-lg bg-[#EBF3FC] dark:bg-slate-800/90 border border-[#D5E3F5] dark:border-slate-700 text-center text-xl font-bold font-mono tracking-[0.4em] text-neutral-900 dark:text-white placeholder:text-neutral-300 dark:placeholder:text-slate-600 placeholder:tracking-normal focus:outline-none focus:ring-2 focus:ring-[#1D70F5]/30 focus:border-[#1D70F5] transition"
              />
            </div>

            <button
              type="submit"
              :disabled="loading || cleanOtpLength !== 6 || isLoginSuccess"
              class="w-full h-9.5 rounded-lg bg-[#1D70F5] hover:bg-[#155FD1] active:scale-[0.99] text-white font-semibold text-xs sm:text-sm transition flex items-center justify-center gap-2 cursor-pointer shadow-xs disabled:opacity-50"
            >
              <span v-if="loading && !isLoginSuccess" class="h-3.5 w-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
              <Check v-else-if="isLoginSuccess" class="h-4 w-4 text-white" />
              <span>{{ isLoginSuccess ? 'Berhasil masuk...' : (loading ? 'Memverifikasi...' : 'Verifikasi & Masuk') }}</span>
            </button>

            <!-- Navigation & Resend Links -->
            <div class="flex items-center justify-between text-[11px] text-neutral-600 dark:text-slate-400 pt-0.5">
              <button
                type="button"
                @click="otpStep = 'phone'; waOtp = ''; errorMessage = ''; successMessage = ''"
                class="text-neutral-500 dark:text-slate-400 hover:text-neutral-900 dark:hover:text-white cursor-pointer underline"
              >
                ← Ganti nomor
              </button>
              <button
                type="button"
                @click="handleSendOtp"
                :disabled="resendCooldown > 0 || loading"
                class="font-semibold text-neutral-700 dark:text-slate-300 hover:text-neutral-900 dark:hover:text-white cursor-pointer disabled:opacity-40 disabled:cursor-default"
              >
                {{ resendCooldown > 0 ? `Kirim ulang (${resendCooldown}s)` : 'Kirim ulang OTP' }}
              </button>
            </div>

            <button
              type="button"
              @click="switchToEmail"
              class="w-full text-center text-xs text-neutral-500 dark:text-slate-400 hover:text-neutral-800 dark:hover:text-white pt-1 cursor-pointer font-medium block"
            >
              ← Kembali ke Sign in Email
            </button>
          </form>

      </div>

    </div>

      <!-- Registration Link & Footer (Compact) -->
      <div class="text-center text-[11px] text-neutral-500 dark:text-slate-400 space-y-1 pt-0.5">
        <p>
          Belum punya akun?
          <router-link to="/register" class="font-bold text-[#1D70F5] hover:text-[#155FD1] hover:underline ml-1">
            Daftar di sini
          </router-link>
        </p>
        <p class="text-neutral-400 dark:text-slate-500 text-[10px]">
          &copy; {{ new Date().getFullYear() }} SCM Supply Chain Management
        </p>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuth } from '@/composables/useAuth';
import { useTheme } from '@/composables/useTheme';
import { Eye, EyeOff, Moon, Sun, CheckCircle2, Check } from 'lucide-vue-next';
import axios from 'axios';

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

async function triggerSuccessRedirect() {
  errorMessage.value = '';
  successMessage.value = 'Login berhasil! Mengalihkan ke dashboard...';
  isLoginSuccess.value = true;
  loading.value = false;

  try {
    await checkAuth();
  } catch (e) {
    // proceed anyway
  }

  setTimeout(() => {
    router.push('/dashboard');
  }, 250);
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
