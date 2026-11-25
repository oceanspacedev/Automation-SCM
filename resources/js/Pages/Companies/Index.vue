<template>
  <section class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
    <h1 class="text-2xl font-semibold tracking-tight">Perusahaan</h1>
    <p class="mt-2 text-sm text-gray-500">Pilih perusahaan untuk mengelola master dan transaksi.</p>
    <p v-if="error" class="mt-6 text-sm text-red-600">{{ error }}</p>
    <div class="mt-7 grid gap-4 sm:grid-cols-2">
      <button v-for="company in companies" :key="company.id" type="button"
        class="rounded-xl border p-6 text-left transition hover:border-gray-400 dark:border-slate-700"
        :class="company.slug === companySlug ? 'border-gray-900 bg-gray-50 dark:bg-slate-800' : 'border-gray-200'"
        @click="switchCompany(company.slug)">
        <div class="flex items-center justify-between gap-4">
          <span class="text-lg font-semibold">{{ company.name }}</span>
          <span v-if="company.slug === companySlug" class="rounded-full bg-gray-900 px-2.5 py-1 text-xs text-white">Aktif</span>
          <span v-else class="text-sm text-gray-500">Buka →</span>
        </div>
        <p class="mt-3 text-sm leading-6 text-gray-500">Dealer, brand, program, dan invoice dikelola terpisah.</p>
      </button>
    </div>
    <p class="mt-5 text-xs text-gray-500">PT MSI memakai layout invoice CV TOP SELULAR. Isi badan usaha di Master Badan Usaha sebelum membuat invoice MSI.</p>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { useCompany } from '../../composables/useCompany';
const { companySlug, switchCompany } = useCompany();
const companies = ref([]);
const error = ref('');
onMounted(async () => {
  try {
    companies.value = (await axios.get('/api/companies')).data.data;
  } catch {
    error.value = 'Daftar perusahaan belum berhasil dimuat. Silakan muat ulang halaman.';
  }
});
</script>
