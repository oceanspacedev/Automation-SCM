<template>
  <div class="min-h-screen flex flex-col justify-center items-center bg-white px-4 py-12 sm:px-6 font-sans">
    <div class="w-full max-w-[420px] space-y-6">
      <!-- Logo & Header -->
      <div class="text-center space-y-2">
        <img
          src="/images/scm-logo.png"
          alt="SCM Supply Chain Management"
          class="h-16 w-auto max-w-[160px] mx-auto object-contain"
        />
        <h1 class="text-2xl font-bold tracking-tight text-neutral-900">
          {{ isSuccess ? 'Pendaftaran Berhasil' : 'Daftar Akun Baru' }}
        </h1>
        <p class="text-sm text-neutral-500">
          {{ isSuccess ? 'Akun Anda sedang menunggu persetujuan (ACC) oleh Admin' : 'Lengkapi formulir berikut untuk membuat akun SCM' }}
        </p>
      </div>

      <!-- SUCCESS SCREEN (Clean, No Slop) -->
      <div v-if="isSuccess" class="space-y-4 pt-2">
        <router-link
          to="/login"
          class="h-10 w-full rounded-lg bg-[#1D70F5] hover:bg-blue-600 text-white text-sm font-medium transition flex items-center justify-center cursor-pointer shadow-xs"
        >
          Kembali ke Halaman Login
        </router-link>
      </div>

      <!-- REGISTRATION FORM -->
      <div v-else class="space-y-4">
        <!-- Error Message -->
        <div
          v-if="errorMessage"
          class="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-800 flex items-center justify-between"
        >
          <span>{{ errorMessage }}</span>
          <button
            type="button"
            @click="errorMessage = ''"
            class="text-red-500 hover:text-red-900 cursor-pointer font-bold ml-2 text-base"
          >
            &times;
          </button>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-3.5">
          <!-- Nama Lengkap -->
          <div>
            <label for="reg-name" class="block text-xs font-medium text-neutral-700 mb-1">Nama Lengkap</label>
            <input
              id="reg-name"
              v-model="form.name"
              type="text"
              required
              placeholder="Contoh: Budi Santoso"
              class="h-10 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs text-neutral-900 placeholder:text-neutral-400 focus:outline-none focus:ring-1 focus:ring-[#1D70F5] focus:border-[#1D70F5] transition"
            />
          </div>

          <!-- Email -->
          <div>
            <label for="reg-email" class="block text-xs font-medium text-neutral-700 mb-1">Email</label>
            <input
              id="reg-email"
              v-model="form.email"
              type="email"
              required
              autocomplete="email"
              placeholder="nama@email.com"
              class="h-10 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs text-neutral-900 placeholder:text-neutral-400 focus:outline-none focus:ring-1 focus:ring-[#1D70F5] focus:border-[#1D70F5] transition"
            />
          </div>

          <!-- WhatsApp & Peran (2 Kolom) -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label for="reg-wa" class="block text-xs font-medium text-neutral-700 mb-1">No. WhatsApp</label>
              <input
                id="reg-wa"
                v-model="form.whatsapp"
                type="tel"
                required
                placeholder="08xxxxxxxxxx"
                class="h-10 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs text-neutral-900 placeholder:text-neutral-400 focus:outline-none focus:ring-1 focus:ring-[#1D70F5] focus:border-[#1D70F5] transition"
              />
            </div>

            <div>
              <label for="reg-role" class="block text-xs font-medium text-neutral-700 mb-1">Peran / Departemen</label>
              <div class="relative">
                <select
                  id="reg-role"
                  v-model="form.role"
                  required
                  class="h-10 w-full rounded-lg border border-neutral-300 bg-white px-3 pr-8 text-xs text-neutral-900 focus:outline-none focus:ring-1 focus:ring-[#1D70F5] focus:border-[#1D70F5] transition appearance-none cursor-pointer"
                >
                  <option value="scm">SCM</option>
                  <option value="ar">AR</option>
                  <option value="telemarketing">Telemarketing</option>
                </select>
                <ChevronDownIcon class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-neutral-400" />
              </div>
            </div>
          </div>

          <!-- Password -->
          <div>
            <label for="reg-password" class="block text-xs font-medium text-neutral-700 mb-1">Password</label>
            <div class="relative">
              <input
                id="reg-password"
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                required
                autocomplete="new-password"
                placeholder="Minimal 6 karakter"
                class="h-10 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 pr-10 text-xs text-neutral-900 placeholder:text-neutral-400 focus:outline-none focus:ring-1 focus:ring-[#1D70F5] focus:border-[#1D70F5] transition"
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

          <!-- Konfirmasi Password -->
          <div>
            <label for="reg-password-confirm" class="block text-xs font-medium text-neutral-700 mb-1">Konfirmasi Password</label>
            <input
              id="reg-password-confirm"
              v-model="form.password_confirmation"
              :type="showPassword ? 'text' : 'password'"
              required
              autocomplete="new-password"
              placeholder="Ulangi password"
              class="h-10 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs text-neutral-900 placeholder:text-neutral-400 focus:outline-none focus:ring-1 focus:ring-[#1D70F5] focus:border-[#1D70F5] transition"
            />
          </div>

          <!-- Submit Button -->
          <button
            type="submit"
            :disabled="loading"
            class="h-10 w-full rounded-lg bg-[#1D70F5] hover:bg-blue-600 text-white text-sm font-medium transition flex items-center justify-center gap-2 cursor-pointer shadow-xs disabled:opacity-50"
          >
            <span v-if="loading" class="h-4 w-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
            <span>{{ loading ? 'Mendaftarkan...' : 'Daftar Akun' }}</span>
          </button>
        </form>

        <div class="text-center text-xs text-neutral-600 pt-2 border-t border-neutral-100">
          Sudah punya akun?
          <router-link to="/login" class="font-semibold text-[#1D70F5] hover:underline ml-1">
            Masuk di sini
          </router-link>
        </div>
      </div>

      <div class="text-center text-xs text-neutral-400">
        &copy; {{ new Date().getFullYear() }} SCM
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { Eye as EyeIcon, EyeOff as EyeOffIcon, ChevronDown as ChevronDownIcon } from 'lucide-vue-next';
import axios from 'axios';

const form = reactive({
  name: '',
  email: '',
  whatsapp: '',
  role: 'scm',
  password: '',
  password_confirmation: '',
});

const roleLabels = {
  scm: 'SCM (Supply Chain Management)',
  ar: 'AR (Account Receivable)',
  telemarketing: 'Telemarketing',
};

const showPassword = ref(false);
const loading = ref(false);
const errorMessage = ref('');
const isSuccess = ref(false);

const handleSubmit = async () => {
  errorMessage.value = '';

  if (form.password.length < 6) {
    errorMessage.value = 'Password minimal harus 6 karakter.';
    return;
  }

  if (form.password !== form.password_confirmation) {
    errorMessage.value = 'Konfirmasi password tidak cocok dengan password.';
    return;
  }

  loading.value = true;
  try {
    const res = await axios.post('/api/register', {
      name: form.name,
      email: form.email,
      whatsapp: form.whatsapp,
      role: form.role,
      password: form.password,
      password_confirmation: form.password_confirmation,
    });

    if (res.data?.success) {
      isSuccess.value = true;
    }
  } catch (err) {
    errorMessage.value = err.response?.data?.message || err.response?.data?.error || 'Gagal melakukan pendaftaran akun. Silakan coba lagi.';
  } finally {
    loading.value = false;
  }
};
</script>
