<template>
  <div class="space-y-4">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
      <span>Master Data</span>
      <ChevronRightIcon class="w-3.5 h-3.5 text-gray-400" />
      <span class="text-gray-800">Reset Data</span>
    </div>

    <div>
      <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-950">Reset Data</h1>
      <p class="text-xs text-gray-500 mt-0.5">
        Hapus data transaksi atau data master untuk mulai dari awal. Brand, user, dan jadwal tidak ikut terhapus.
      </p>
    </div>

    <p v-if="isForbidden" class="max-w-3xl rounded-xl border border-gray-200 bg-white px-5 py-4 text-xs text-gray-600">
      Hanya Admin yang bisa mereset data.
    </p>

    <section v-else class="max-w-3xl rounded-xl border border-gray-200 bg-white shadow-xs">
      <div class="divide-y divide-gray-100">
        <!-- Area -->
        <div :class="rowClass">
          <p :class="rowLabelClass">Area</p>
          <div class="inline-flex flex-wrap items-center gap-1 p-1 rounded-lg border border-gray-200 bg-gray-50 text-xs w-fit">
            <button
              v-for="option in areaOptions"
              :key="option.value"
              type="button"
              @click="scope.area = option.value"
              :class="[
                'h-7 px-3 rounded-md font-medium transition cursor-pointer',
                scope.area === option.value ? 'bg-white text-gray-900 shadow-2xs border border-gray-200' : 'text-gray-500 hover:text-gray-900'
              ]"
            >
              {{ option.label }}
            </button>
          </div>
        </div>

        <!-- Brand -->
        <div :class="rowClass">
          <label for="reset-brand" :class="rowLabelClass">Brand</label>
          <select id="reset-brand" v-model="scope.brand" :class="[selectClass, 'w-full sm:w-52 px-3']">
            <option value="all">Semua brand</option>
            <option v-for="b in brands" :key="b.slug" :value="b.slug">{{ b.name }}</option>
          </select>
        </div>

        <!-- Data yang direset: transaksi lalu master -->
        <div v-for="group in datasetGroups" :key="group.label" :class="rowClass">
          <p :class="rowLabelClass">{{ group.label }}</p>
          <div class="space-y-2.5 min-w-0">
            <label
              v-for="key in group.keys"
              :key="key"
              class="flex items-start gap-2.5 text-xs"
              :class="isIncludedByDrafts(key) ? 'cursor-default' : 'cursor-pointer'"
            >
              <input
                type="checkbox"
                :value="key"
                v-model="selected"
                :disabled="isIncludedByDrafts(key) || isResetting"
                class="mt-0.5 w-4 h-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900 cursor-pointer disabled:cursor-default"
              />
              <span class="min-w-0">
                <span class="font-medium text-gray-900">{{ datasets[key] }}</span>
                <span class="text-gray-500 tabular-nums"> · {{ isLoading ? '…' : formatNumber(counts[key]) }} data</span>
                <span class="block text-[11px] text-gray-500 mt-0.5">{{ isIncludedByDrafts(key) ? 'Ikut terhapus karena Draft dipilih.' : DATASET_NOTES[key] }}</span>
              </span>
            </label>
          </div>
        </div>

        <!-- Password reset: diatur & diubah Admin dari sini, disimpan sebagai hash -->
        <div :class="rowClass">
          <p :class="rowLabelClass">Password</p>
          <div class="flex flex-wrap items-center gap-x-3 gap-y-2 min-w-0 text-xs">
            <span :class="passwordSet ? 'text-gray-700' : 'text-amber-700'">
              {{ passwordSet ? 'Password reset sudah diatur.' : 'Password reset belum diatur, reset belum bisa dipakai.' }}
            </span>
            <button type="button" @click="openPasswordForm" :class="smallButtonClass">
              {{ passwordSet ? 'Ubah password reset' : 'Atur password reset' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Ringkasan & aksi -->
      <div class="px-5 sm:px-6 py-4 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <p class="text-xs text-gray-600 min-w-0">
          <template v-if="effectiveSelection.length">
            Akan dihapus dari {{ scopeLabel }}:
            <span class="text-gray-900">{{ selectionSummary }}</span>,
            beserta riwayatnya.
          </template>
          <template v-else>Pilih data yang mau direset.</template>
        </p>
        <div class="flex flex-col items-stretch sm:items-end gap-1.5 shrink-0">
          <button
            type="button"
            @click="openPasswordPrompt"
            :disabled="!effectiveSelection.length || !passwordSet || isResetting"
            class="h-9 px-4 inline-flex items-center justify-center rounded-lg bg-red-600 hover:bg-red-700 text-xs font-medium text-white transition cursor-pointer whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Reset data
          </button>
        </div>
      </div>

      <p v-if="message" class="px-5 sm:px-6 pb-4 -mt-1 text-xs" :class="message.error ? 'text-red-600' : 'text-emerald-700'">
        {{ message.text }}
      </p>
    </section>

    <!-- Atur / ubah password reset -->
    <div
      v-if="isPasswordFormOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs"
      @click.self="closePasswordForm"
    >
      <form @submit.prevent="savePassword" class="bg-white rounded-xl max-w-sm w-full p-5 shadow-xl border border-gray-200 space-y-3 text-xs">
        <div>
          <h3 class="text-sm font-bold text-gray-950">{{ passwordSet ? 'Ubah password reset' : 'Atur password reset' }}</h3>
          <p class="mt-1 text-gray-600">Password ini diminta setiap kali data akan direset. Minimal 6 karakter.</p>
        </div>
        <div v-if="passwordSet">
          <label for="reset-current-password" class="block font-medium text-gray-900 mb-1">Password lama</label>
          <input id="reset-current-password" ref="passwordFormFirstInput" v-model="passwordForm.current_password" type="password" autocomplete="off" :class="inputClass" />
        </div>
        <div>
          <label for="reset-new-password" class="block font-medium text-gray-900 mb-1">Password baru</label>
          <input id="reset-new-password" ref="passwordFormNewInput" v-model="passwordForm.new_password" type="password" autocomplete="new-password" :class="inputClass" />
        </div>
        <div>
          <label for="reset-new-password-confirmation" class="block font-medium text-gray-900 mb-1">Ulangi password baru</label>
          <input id="reset-new-password-confirmation" v-model="passwordForm.new_password_confirmation" type="password" autocomplete="new-password" :class="inputClass" />
        </div>
        <p v-if="passwordFormError" class="text-[11px] text-red-600">{{ passwordFormError }}</p>
        <div class="flex items-center justify-end gap-2 pt-1">
          <button
            type="button"
            @click="closePasswordForm"
            :disabled="isSavingPassword"
            class="h-8 px-3 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium cursor-pointer disabled:opacity-50"
          >
            Batal
          </button>
          <button
            type="submit"
            :disabled="isSavingPassword || !passwordForm.new_password"
            class="h-8 px-4 rounded-lg bg-gray-900 hover:bg-gray-800 text-white font-medium cursor-pointer disabled:opacity-50"
          >
            {{ isSavingPassword ? 'Menyimpan...' : 'Simpan' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Password reset sebelum data benar-benar dihapus -->
    <div
      v-if="isPasswordPromptOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs"
      @click.self="closePasswordPrompt"
    >
      <form @submit.prevent="resetData" class="bg-white rounded-xl max-w-sm w-full p-5 shadow-xl border border-gray-200 space-y-3 text-xs">
        <div>
          <h3 class="text-sm font-bold text-gray-950">Masukkan password reset</h3>
          <p class="mt-1 text-gray-600">
            Akan dihapus dari {{ scopeLabel }}: <span class="text-gray-900">{{ selectionSummary }}</span>, beserta riwayatnya.
            Data yang dihapus tidak bisa dikembalikan.
          </p>
        </div>
        <div>
          <label for="reset-password" class="sr-only">Password reset</label>
          <input
            id="reset-password"
            ref="passwordInput"
            v-model="password"
            type="password"
            autocomplete="off"
            placeholder="Password reset"
            :disabled="isResetting"
            class="h-9 w-full px-3 rounded-lg border text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900"
            :class="passwordError ? 'border-red-500' : 'border-gray-300'"
          />
          <p v-if="passwordError" class="mt-1 text-[11px] text-red-600">{{ passwordError }}</p>
        </div>
        <div class="flex items-center justify-end gap-2 pt-1">
          <button
            type="button"
            @click="closePasswordPrompt"
            :disabled="isResetting"
            class="h-8 px-3 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium cursor-pointer disabled:opacity-50"
          >
            Batal
          </button>
          <button
            type="submit"
            :disabled="!password || isResetting"
            class="h-8 px-4 rounded-lg bg-red-600 hover:bg-red-700 text-white font-medium cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ isResetting ? 'Mereset...' : 'Reset' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, nextTick, onMounted } from 'vue';
import axios from 'axios';
import { ChevronRight as ChevronRightIcon } from 'lucide-vue-next';
import { AREAS } from '@/composables/useArea';
import { brandName, useBrands } from '@/composables/useBrands';

const rowClass = 'px-5 sm:px-6 py-4 grid gap-2 sm:grid-cols-[120px_minmax(0,1fr)] sm:gap-6';
const rowLabelClass = 'text-xs font-medium text-gray-700 sm:pt-2';
const selectClass = 'h-9 rounded-lg border border-gray-300 bg-white text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900 cursor-pointer';
const inputClass = 'h-9 w-full px-3 rounded-lg border border-gray-300 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900';
const smallButtonClass = 'h-8 px-3 inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 transition cursor-pointer whitespace-nowrap';

const DATASET_NOTES = {
  drafts: 'Invoice dari draft ini ikut terhapus.',
  invoices: 'Beserta riwayat email & WA-nya. Draft-nya kembali bisa di-generate.',
  data_programs: 'Beserta riwayat pencocokan. Baris yang masih ada di Google Spreadsheet akan masuk lagi saat sinkron.',
  program_submissions: 'Beserta riwayat pencocokan. File dokumen yang sudah diunggah tidak dihapus.',
  dealers: 'Toko di area yang dipilih (brand tidak berpengaruh), termasuk link agreement-nya. Isi lagi lewat Import Excel CV TOP.',
  regions: 'Region di area yang dipilih (brand tidak berpengaruh).',
  ar_contacts: 'Nomor WA AR per region di area yang dipilih (brand tidak berpengaruh).',
  master_programs: 'Daftar program brand yang dipilih, untuk semua area.',
  customers: 'Badan usaha Bill To invoice. Selalu semua, tidak tergantung area & brand.',
};

const areaOptions = [{ value: 'all', label: 'Semua area' }, ...AREAS.map((a) => ({ value: a.value, label: a.label }))];

const { brands, loadBrands } = useBrands();

const datasets = ref({
  drafts: 'Draft',
  invoices: 'Invoice',
  data_programs: 'Data Program',
  program_submissions: 'Form Program',
  dealers: 'Dealer',
  regions: 'Region',
  ar_contacts: 'AR',
  master_programs: 'Program',
  customers: 'Badan Usaha',
});
const masterDatasets = ref(['dealers', 'regions', 'ar_contacts', 'master_programs', 'customers']);
const datasetGroups = computed(() => [
  { label: 'Transaksi', keys: Object.keys(datasets.value).filter((key) => !masterDatasets.value.includes(key)) },
  { label: 'Master', keys: masterDatasets.value.filter((key) => key in datasets.value) },
]);
const counts = ref({});
const scope = reactive({ area: 'all', brand: 'all' });
const selected = ref([]);
const isPasswordPromptOpen = ref(false);
const password = ref('');
const passwordError = ref('');
const passwordInput = ref(null);
const isLoading = ref(false);
const isResetting = ref(false);
const isForbidden = ref(false);
const passwordSet = ref(true);
const isPasswordFormOpen = ref(false);
const isSavingPassword = ref(false);
const passwordFormError = ref('');
const passwordForm = reactive({ current_password: '', new_password: '', new_password_confirmation: '' });
const passwordFormFirstInput = ref(null);
const passwordFormNewInput = ref(null);
const message = ref(null);

const formatNumber = (value) => Number(value || 0).toLocaleString('id-ID');

// Invoice dibuat dari draft, jadi ikut terhapus saat Draft dipilih
const isIncludedByDrafts = (key) => key === 'invoices' && selected.value.includes('drafts');
const effectiveSelection = computed(() => Object.keys(datasets.value).filter((key) => selected.value.includes(key) || isIncludedByDrafts(key)));

const scopeLabel = computed(() => {
  const area = areaOptions.find((option) => option.value === scope.area)?.label || 'Semua area';
  const brand = scope.brand === 'all' ? 'semua brand' : brandName(scope.brand);
  return `${area} · ${brand}`;
});

const selectionSummary = computed(() => effectiveSelection.value.map((key) => `${formatNumber(counts.value[key])} ${datasets.value[key]}`).join(', '));

async function openPasswordPrompt() {
  password.value = '';
  passwordError.value = '';
  message.value = null;
  isPasswordPromptOpen.value = true;
  await nextTick();
  passwordInput.value?.focus();
}

function closePasswordPrompt() {
  if (isResetting.value) return;
  isPasswordPromptOpen.value = false;
  password.value = '';
}

async function fetchCounts() {
  isLoading.value = true;
  try {
    const res = await axios.get('/api/data-reset', { params: { area: scope.area, brand: scope.brand } });
    datasets.value = res.data.datasets || datasets.value;
    masterDatasets.value = res.data.master_datasets || masterDatasets.value;
    counts.value = res.data.counts || {};
    passwordSet.value = res.data.password_set !== false;
  } catch (err) {
    if (err.response?.status === 403) isForbidden.value = true;
  } finally {
    isLoading.value = false;
  }
}

async function openPasswordForm() {
  Object.assign(passwordForm, { current_password: '', new_password: '', new_password_confirmation: '' });
  passwordFormError.value = '';
  message.value = null;
  isPasswordFormOpen.value = true;
  await nextTick();
  (passwordFormFirstInput.value || passwordFormNewInput.value)?.focus();
}

function closePasswordForm() {
  if (isSavingPassword.value) return;
  isPasswordFormOpen.value = false;
}

async function savePassword() {
  isSavingPassword.value = true;
  passwordFormError.value = '';
  try {
    const res = await axios.put('/api/data-reset/password', passwordForm);
    passwordSet.value = true;
    isPasswordFormOpen.value = false;
    message.value = { error: false, text: res.data.message || 'Password reset disimpan.' };
  } catch (err) {
    const errors = err.response?.data?.errors;
    passwordFormError.value = err.response?.status === 429
      ? 'Terlalu banyak percobaan. Coba lagi dalam 1 menit.'
      : (errors ? Object.values(errors).flat()[0] : (err.response?.data?.message || 'Gagal menyimpan password reset.'));
  } finally {
    isSavingPassword.value = false;
  }
}

async function resetData() {
  if (!password.value || !effectiveSelection.value.length || isResetting.value) return;

  isResetting.value = true;
  passwordError.value = '';
  try {
    const res = await axios.post('/api/data-reset', {
      datasets: effectiveSelection.value,
      area: scope.area,
      brand: scope.brand,
      password: password.value,
    });
    counts.value = res.data.counts || counts.value;
    message.value = { error: false, text: res.data.message || 'Reset selesai.' };
    selected.value = [];
    isPasswordPromptOpen.value = false;
    password.value = '';
  } catch (err) {
    // Password salah tetap di kotak password supaya bisa dicoba lagi
    const errors = err.response?.data?.errors;
    passwordError.value = err.response?.status === 429
      ? 'Terlalu banyak percobaan. Coba lagi dalam 1 menit.'
      : (errors ? Object.values(errors).flat()[0] : (err.response?.data?.message || 'Gagal mereset data.'));
    password.value = '';
    await nextTick();
    passwordInput.value?.focus();
  } finally {
    isResetting.value = false;
  }
}

watch(scope, () => {
  message.value = null;
  fetchCounts();
});

onMounted(() => {
  loadBrands();
  fetchCounts();
});
</script>
