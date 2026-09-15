<template>
  <div class="min-h-screen flex flex-col justify-center items-center bg-white px-4 py-12 sm:px-6 font-sans">
    <div class="w-full max-w-[380px] space-y-6">
      <!-- Logo & Header (Clean Monochrome) -->
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
          Masukkan email dan password untuk melanjutkan
        </p>
      </div>

      <!-- Error Message (Polos / Monokrom tanpa warna mencolok) -->
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

      <!-- Login Form (Polos, Tanpa Warna) -->
      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label for="email" class="block text-sm font-medium text-neutral-700 mb-1">
            Email
          </label>
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
          <label for="password" class="block text-sm font-medium text-neutral-700 mb-1">
            Password
          </label>
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

        <div class="flex items-center justify-between text-sm">
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input
              type="checkbox"
              v-model="form.remember"
              class="h-4 w-4 rounded border-neutral-300 text-black focus:ring-black cursor-pointer"
            />
            <span class="text-neutral-600 text-xs sm:text-sm">Ingat saya</span>
          </label>
        </div>

        <!-- Tombol Masuk Biru -->
        <button
          type="submit"
          :disabled="loading"
          class="h-10 w-full rounded-lg bg-[#1D70F5] hover:bg-blue-600 text-white text-sm font-medium transition flex items-center justify-center gap-2 cursor-pointer shadow-xs disabled:opacity-50"
        >
          <span
            v-if="loading"
            class="h-4 w-4 border-2 border-white/30 border-t-white rounded-full animate-spin"
          ></span>
          <span>{{ loading ? 'Memproses...' : 'Masuk' }}</span>
        </button>
      </form>

      <!-- Kotak Akun Default Polos -->
      <div class="rounded-lg border border-neutral-200 bg-neutral-50/80 p-3.5 space-y-2 text-xs text-neutral-600">
        <div class="font-medium text-neutral-800">Informasi Akun Default:</div>
        <div class="space-y-0.5">
          <div>Email: <span class="font-mono text-neutral-800">admin@scm.com</span></div>
          <div>Password: <span class="font-mono text-neutral-800">password</span></div>
        </div>
        <button
          type="button"
          @click="fillDemoAccount"
          class="w-full mt-1.5 py-1.5 px-3 border border-neutral-300 bg-white hover:bg-neutral-100 text-neutral-800 rounded-md font-medium transition cursor-pointer text-xs"
        >
          Isi Otomatis Akun Default
        </button>
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

const router = useRouter();
const { login } = useAuth();

const form = reactive({
  email: '',
  password: '',
  remember: false,
});

const loading = ref(false);
const errorMessage = ref('');
const showPassword = ref(false);

const fillDemoAccount = () => {
  form.email = 'admin@scm.com';
  form.password = 'password';
};

const handleSubmit = async () => {
  loading.value = true;
  errorMessage.value = '';

  try {
    await login({
      email: form.email,
      password: form.password,
      remember: form.remember,
    });
    router.push('/dashboard');
  } catch (err) {
    errorMessage.value =
      err.response?.data?.message || 'Gagal masuk. Silakan periksa kembali email dan password.';
  } finally {
    loading.value = false;
  }
};
</script>
