<template>
  <!-- Tab brand (dari Master Brand): data halaman ini terpisah per brand -->
  <div class="inline-flex items-center gap-1 p-1 rounded-lg border border-gray-200 bg-gray-50 text-xs">
    <router-link
      v-for="b in tabs"
      :key="b.slug"
      :to="{ name: route.name, params: { ...route.params, brand: b.slug }, query: route.query }"
      :class="[
        'h-7 px-3 inline-flex items-center rounded-md font-medium transition',
        b.slug === brand.slug
          ? 'bg-white text-gray-900 shadow-2xs border border-gray-200'
          : 'text-gray-500 hover:text-gray-900'
      ]"
    >
      {{ b.name }}
    </router-link>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useBrands } from '@/composables/useBrands';

const route = useRoute();
const { activeBrands, brand, loadBrands } = useBrands();

// Brand aktif dari Master Brand; brand yang sedang dibuka tetap tampil walau sudah dinonaktifkan
const tabs = computed(() => {
  const list = [...activeBrands.value];
  if (!list.some((b) => b.slug === brand.value.slug)) {
    list.push(brand.value);
  }
  return list;
});

onMounted(() => loadBrands());
</script>
