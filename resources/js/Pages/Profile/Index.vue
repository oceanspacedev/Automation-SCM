<template>
  <div class="space-y-4">
    <div>
      <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-950 dark:text-white">Profil</h1>
      <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">Atur foto, data akun, dan password Anda.</p>
    </div>

    <!-- Layar lebar: ringkasan akun & foto di kiri, formulir di kanan -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
    <!-- Ringkasan akun & foto profil -->
    <section class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 sm:p-6 lg:sticky lg:top-20">
      <div class="flex items-center gap-4 lg:flex-col lg:items-center lg:text-center">
        <div class="w-16 h-16 lg:w-24 lg:h-24 rounded-full overflow-hidden bg-gray-100 dark:bg-slate-800 flex items-center justify-center text-xl lg:text-3xl font-medium text-gray-600 dark:text-slate-300 shrink-0">
          <img v-if="user?.avatar_url" :src="user.avatar_url" alt="Foto profil" class="w-full h-full object-cover" />
          <span v-else>{{ initial }}</span>
        </div>
        <div class="min-w-0">
          <p class="text-sm font-semibold text-gray-950 dark:text-white truncate">{{ user?.name || '-' }}</p>
          <p class="text-xs text-gray-500 dark:text-slate-400 truncate mt-0.5">{{ user?.email || '-' }}</p>
        </div>
      </div>

      <dl class="mt-5 pt-4 border-t border-gray-100 dark:border-slate-800 space-y-2.5 text-xs">
        <div class="flex items-center justify-between gap-3">
          <dt class="text-gray-500 dark:text-slate-400">Peran</dt>
          <dd class="text-gray-900 dark:text-slate-100">{{ roleLabel }}</dd>
        </div>
        <div class="flex items-center justify-between gap-3">
          <dt class="text-gray-500 dark:text-slate-400">WhatsApp</dt>
          <dd class="text-gray-900 dark:text-slate-100">{{ displayWhatsapp(user?.whatsapp) || '-' }}</dd>
        </div>
      </dl>

      <div class="mt-5 pt-4 border-t border-gray-100 dark:border-slate-800 space-y-2">
          <div class="flex flex-wrap items-center gap-2 lg:justify-center">
            <input ref="photoInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="uploadPhoto" />
            <button
              type="button"
              @click="photoInput?.click()"
              :disabled="photo.saving"
              class="h-9 px-3.5 rounded-lg border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-gray-50 dark:hover:bg-slate-800 text-xs font-medium text-gray-700 dark:text-slate-200 transition cursor-pointer disabled:opacity-50"
            >
              {{ photo.saving ? 'Mengunggah...' : (user?.avatar_url ? 'Ganti foto' : 'Unggah foto') }}
            </button>
            <button
              v-if="user?.avatar_url"
              type="button"
              @click="removePhoto"
              :disabled="photo.saving"
              class="h-9 px-3.5 rounded-lg border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-red-50 dark:hover:bg-red-950/30 text-xs font-medium text-red-600 dark:text-red-400 transition cursor-pointer disabled:opacity-50"
            >
              Hapus
            </button>
          </div>
          <p class="text-xs text-gray-500 dark:text-slate-400 lg:text-center">Foto: JPG, PNG, atau WebP, maksimal 2 MB.</p>
      </div>
      <p v-if="photo.message" class="mt-3 text-xs lg:text-center" :class="photo.error ? 'text-red-600 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400'">{{ photo.message }}</p>
    </section>

    <div class="lg:col-span-2 space-y-4">
    <!-- Data akun -->
    <section class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 sm:p-6">
      <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Data akun</h2>
      <form @submit.prevent="saveProfile" class="mt-4 space-y-4">
        <div>
          <label for="profile-name" class="block text-xs font-medium text-gray-700 dark:text-slate-300 mb-1.5">Nama</label>
          <input id="profile-name" v-model="profileForm.name" type="text" required :class="inputClass" />
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label for="profile-email" class="block text-xs font-medium text-gray-700 dark:text-slate-300 mb-1.5">Email</label>
            <input id="profile-email" v-model="profileForm.email" type="email" required autocomplete="email" :class="inputClass" />
          </div>
          <div>
            <label for="profile-wa" class="block text-xs font-medium text-gray-700 dark:text-slate-300 mb-1.5">No. WhatsApp</label>
            <input id="profile-wa" v-model="profileForm.whatsapp" type="tel" placeholder="08xxxxxxxxxx" :class="inputClass" />
          </div>
        </div>
        <p class="text-xs text-gray-500 dark:text-slate-400">Peran dan akses menu diatur admin di Master User.</p>
        <div class="flex items-center gap-3">
          <button type="submit" :disabled="profile.saving" :class="primaryButtonClass">
            {{ profile.saving ? 'Menyimpan...' : 'Simpan' }}
          </button>
          <p v-if="profile.message" class="text-xs" :class="profile.error ? 'text-red-600 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400'">{{ profile.message }}</p>
        </div>
      </form>
    </section>

    <!-- Ubah password -->
    <section class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 sm:p-6">
      <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Ubah password</h2>
      <form @submit.prevent="savePassword" class="mt-4 space-y-4">
        <div>
          <label for="current-password" class="block text-xs font-medium text-gray-700 dark:text-slate-300 mb-1.5">Password saat ini</label>
          <input id="current-password" v-model="passwordForm.current_password" type="password" required autocomplete="current-password" :class="inputClass" />
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label for="new-password" class="block text-xs font-medium text-gray-700 dark:text-slate-300 mb-1.5">Password baru</label>
            <input id="new-password" v-model="passwordForm.password" type="password" required autocomplete="new-password" placeholder="Minimal 6 karakter" :class="inputClass" />
          </div>
          <div>
            <label for="confirm-password" class="block text-xs font-medium text-gray-700 dark:text-slate-300 mb-1.5">Konfirmasi password baru</label>
            <input id="confirm-password" v-model="passwordForm.password_confirmation" type="password" required autocomplete="new-password" :class="inputClass" />
          </div>
        </div>
        <div class="flex items-center gap-3">
          <button type="submit" :disabled="password.saving" :class="primaryButtonClass">
            {{ password.saving ? 'Menyimpan...' : 'Ubah password' }}
          </button>
          <p v-if="password.message" class="text-xs" :class="password.error ? 'text-red-600 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400'">{{ password.message }}</p>
        </div>
      </form>
    </section>
    </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue';
import axios from 'axios';
import { useAuth } from '@/composables/useAuth';

const { user, setUser } = useAuth();

const inputClass = 'w-full h-10 px-3 rounded-lg bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-1 focus:ring-gray-900 dark:focus:ring-slate-300 focus:border-gray-900 dark:focus:border-slate-300 transition';
const primaryButtonClass = 'h-9 px-4 rounded-lg bg-gray-900 hover:bg-gray-800 dark:bg-white dark:hover:bg-slate-200 text-white dark:text-gray-900 text-xs font-medium transition cursor-pointer disabled:opacity-50';

const ROLE_LABELS = { admin: 'Admin', scm: 'SCM', ar: 'AR', telemarketing: 'Telemarketing' };
const roleLabel = computed(() => ROLE_LABELS[user.value?.role] || user.value?.role || '-');
const initial = computed(() => (user.value?.name || user.value?.email || 'A').trim().charAt(0).toUpperCase());

// Nomor WhatsApp tersimpan sebagai 628…; ditampilkan sebagai 08… agar mudah dibaca
const displayWhatsapp = (phone) => (phone && phone.startsWith('62') ? `0${phone.slice(2)}` : phone || '');

const photoInput = ref(null);
const photo = reactive({ saving: false, message: '', error: false });
const profile = reactive({ saving: false, message: '', error: false });
const password = reactive({ saving: false, message: '', error: false });

const profileForm = reactive({ name: '', email: '', whatsapp: '' });
const passwordForm = reactive({ current_password: '', password: '', password_confirmation: '' });

watch(user, (value) => {
  profileForm.name = value?.name || '';
  profileForm.email = value?.email || '';
  profileForm.whatsapp = displayWhatsapp(value?.whatsapp);
}, { immediate: true });

const errorText = (err, fallback) => {
  const errors = err.response?.data?.errors;
  return errors ? Object.values(errors).flat()[0] : (err.response?.data?.message || fallback);
};

const uploadPhoto = async (event) => {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file) return;

  photo.saving = true;
  photo.message = '';
  try {
    const formData = new FormData();
    formData.append('photo', file);
    const res = await axios.post('/api/profile/photo', formData, { headers: { 'Content-Type': 'multipart/form-data' } });
    setUser(res.data.user);
    Object.assign(photo, { error: false, message: res.data.message });
  } catch (err) {
    Object.assign(photo, { error: true, message: errorText(err, 'Gagal mengunggah foto.') });
  } finally {
    photo.saving = false;
  }
};

const removePhoto = async () => {
  photo.saving = true;
  photo.message = '';
  try {
    const res = await axios.delete('/api/profile/photo');
    setUser(res.data.user);
    Object.assign(photo, { error: false, message: res.data.message });
  } catch (err) {
    Object.assign(photo, { error: true, message: errorText(err, 'Gagal menghapus foto.') });
  } finally {
    photo.saving = false;
  }
};

const saveProfile = async () => {
  profile.saving = true;
  profile.message = '';
  try {
    const res = await axios.put('/api/profile', { ...profileForm });
    setUser(res.data.user);
    Object.assign(profile, { error: false, message: res.data.message });
  } catch (err) {
    Object.assign(profile, { error: true, message: errorText(err, 'Gagal menyimpan profil.') });
  } finally {
    profile.saving = false;
  }
};

const savePassword = async () => {
  password.saving = true;
  password.message = '';
  try {
    const res = await axios.put('/api/profile/password', { ...passwordForm });
    Object.assign(passwordForm, { current_password: '', password: '', password_confirmation: '' });
    Object.assign(password, { error: false, message: res.data.message });
  } catch (err) {
    Object.assign(password, { error: true, message: errorText(err, 'Gagal mengubah password.') });
  } finally {
    password.saving = false;
  }
};
</script>
