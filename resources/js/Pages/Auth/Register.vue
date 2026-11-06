<template>
  <div class="min-h-screen flex flex-col justify-center items-center bg-gray-50 dark:bg-[#0B1120] text-gray-900 dark:text-slate-100 px-4 py-8 font-sans transition-colors">
    <div class="w-full max-w-lg space-y-4">
      <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm p-6 sm:p-7">
        <!-- Judul -->
        <div class="mb-6 text-center">
          <!-- Logo: huruf S (Supply) sebagai jalur barang: titik pemasok → titik gudang/hub → panah ke toko -->
          <div class="w-12 h-12 mx-auto mb-5 rounded-[14px] bg-[#1769e0] flex items-center justify-center" role="img" aria-label="SCM">
            <svg viewBox="0 0 48 48" class="w-10 h-10 text-white" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M35 11H19.5a7 7 0 0 0 0 14h9a7 7 0 0 1 0 14H14" stroke-width="4.5" />
              <path d="M19 33.5 13.5 39l5.5 5.5" stroke-width="4.5" />
              <circle cx="35" cy="11" r="4.5" fill="currentColor" stroke="none" />
              <circle cx="24" cy="25" r="4.5" fill="currentColor" stroke="#1769e0" stroke-width="2.5" />
            </svg>
          </div>
          <h1 class="text-lg font-semibold tracking-tight text-gray-950 dark:text-white">
            {{ isSuccess ? 'Pendaftaran berhasil' : 'Daftar akun' }}
          </h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">
            {{ isSuccess ? 'Akun Anda menunggu persetujuan (ACC) dari admin.' : 'Lengkapi data berikut untuk membuat akun SCM.' }}
          </p>
        </div>

        <!-- Berhasil daftar -->
        <router-link
          v-if="isSuccess"
          to="/login"
          class="w-full h-10 rounded-lg bg-[#1769e0] hover:bg-[#1257c2] text-white text-sm font-medium transition flex items-center justify-center"
        >
          Kembali ke halaman masuk
        </router-link>

        <!-- Formulir pendaftaran -->
        <template v-else>
          <div
            v-if="errorMessage"
            class="mb-4 rounded-lg border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-950/40 px-3 py-2.5 text-xs text-red-700 dark:text-red-300 flex items-start justify-between gap-3"
          >
            <span>{{ errorMessage }}</span>
            <button type="button" @click="errorMessage = ''" class="text-red-400 hover:text-red-700 dark:hover:text-red-200 cursor-pointer leading-none" aria-label="Tutup">&times;</button>
          </div>

          <form @submit.prevent="handleSubmit" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label for="reg-name" class="block text-xs font-medium text-gray-700 dark:text-slate-300 mb-1.5">Nama lengkap</label>
                <input
                  id="reg-name"
                  v-model="form.name"
                  type="text"
                  required
                  placeholder="Budi Santoso"
                  class="w-full h-10 px-3 rounded-lg bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-[#1769e0]/20 focus:border-[#1769e0] dark:focus:border-[#4a8df0] transition"
                />
              </div>

              <div>
                <label for="reg-email" class="block text-xs font-medium text-gray-700 dark:text-slate-300 mb-1.5">Email</label>
                <input
                  id="reg-email"
                  v-model="form.email"
                  type="email"
                  required
                  autocomplete="email"
                  placeholder="nama@email.com"
                  class="w-full h-10 px-3 rounded-lg bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-[#1769e0]/20 focus:border-[#1769e0] dark:focus:border-[#4a8df0] transition"
                />
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label for="reg-wa" class="block text-xs font-medium text-gray-700 dark:text-slate-300 mb-1.5">No. WhatsApp</label>
                <input
                  id="reg-wa"
                  v-model="form.whatsapp"
                  type="tel"
                  required
                  placeholder="08xxxxxxxxxx"
                  class="w-full h-10 px-3 rounded-lg bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-[#1769e0]/20 focus:border-[#1769e0] dark:focus:border-[#4a8df0] transition"
                />
              </div>

              <div>
                <label for="reg-role" class="block text-xs font-medium text-gray-700 dark:text-slate-300 mb-1.5">Peran</label>
                <div class="relative">
                  <select
                    id="reg-role"
                    v-model="form.role"
                    required
                    class="w-full h-10 pl-3 pr-8 rounded-lg bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#1769e0]/20 focus:border-[#1769e0] dark:focus:border-[#4a8df0] transition appearance-none cursor-pointer"
                  >
                    <option value="scm">SCM</option>
                    <option value="ar">AR</option>
                    <option value="telemarketing">Telemarketing</option>
                  </select>
                  <ChevronDownIcon class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                </div>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label for="reg-password" class="block text-xs font-medium text-gray-700 dark:text-slate-300 mb-1.5">Password</label>
                <div class="relative">
                  <input
                    id="reg-password"
                    v-model="form.password"
                    :type="showPassword ? 'text' : 'password'"
                    required
                    autocomplete="new-password"
                    placeholder="Min. 8 karakter, huruf &amp; angka"
                    class="w-full h-10 pl-3 pr-10 rounded-lg bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-[#1769e0]/20 focus:border-[#1769e0] dark:focus:border-[#4a8df0] transition"
                  />
                  <button
                    type="button"
                    @click="showPassword = !showPassword"
                    class="absolute inset-y-0 right-0 w-10 flex items-center justify-center text-gray-400 hover:text-gray-700 dark:hover:text-white cursor-pointer"
                    tabindex="-1"
                    :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                  >
                    <EyeIcon v-if="!showPassword" class="h-4 w-4" />
                    <EyeOffIcon v-else class="h-4 w-4" />
                  </button>
                </div>
              </div>

              <div>
                <label for="reg-password-confirm" class="block text-xs font-medium text-gray-700 dark:text-slate-300 mb-1.5">Konfirmasi password</label>
                <input
                  id="reg-password-confirm"
                  v-model="form.password_confirmation"
                  :type="showPassword ? 'text' : 'password'"
                  required
                  autocomplete="new-password"
                  placeholder="Ulangi password"
                  class="w-full h-10 px-3 rounded-lg bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-[#1769e0]/20 focus:border-[#1769e0] dark:focus:border-[#4a8df0] transition"
                />
              </div>
            </div>

            <button
              type="submit"
              :disabled="loading"
              class="w-full h-10 rounded-lg bg-[#1769e0] hover:bg-[#1257c2] text-white text-sm font-medium transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
            >
              <span v-if="loading" class="h-4 w-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
              <span>{{ loading ? 'Mendaftarkan...' : 'Daftar' }}</span>
            </button>
          </form>
        </template>
      </div>

      <p v-if="!isSuccess" class="text-center text-sm text-gray-500 dark:text-slate-400">
        Sudah punya akun?
        <router-link to="/login" class="font-medium text-[#1769e0] dark:text-[#4a8df0] hover:underline">Masuk</router-link>
      </p>
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

  // Sama dengan aturan server: minimal 8 karakter, berisi huruf dan angka
  if (form.password.length < 8 || !/[A-Za-z]/.test(form.password) || !/[0-9]/.test(form.password)) {
    errorMessage.value = 'Password minimal 8 karakter dan harus berisi huruf dan angka.';
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
