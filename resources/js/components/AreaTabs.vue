<template>
  <div class="flex flex-wrap items-center gap-2">
    <!-- Halaman per brand (mis. /data-program/realme/jabar) juga punya tab brand -->
    <BrandTabs v-if="route.params.brand" />

    <!-- Tab area (Jawa Barat / Jawa Tengah): data halaman ini terpisah per area -->
    <div class="inline-flex items-center gap-1 p-1 rounded-lg border border-gray-200 bg-gray-50 text-xs">
      <router-link
        v-for="a in AREAS"
        :key="a.value"
        :to="{ name: route.name, params: { ...route.params, area: a.value }, query: route.query }"
        :class="[
          'h-7 px-3 inline-flex items-center rounded-md font-medium transition',
          a.value === area.value
            ? 'bg-white text-gray-900 shadow-2xs border border-gray-200'
            : 'text-gray-500 hover:text-gray-900'
        ]"
      >
        {{ a.label }}
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { useRoute } from 'vue-router';
import BrandTabs from '@/components/BrandTabs.vue';
import { AREAS, useArea } from '@/composables/useArea';

const route = useRoute();
const { area } = useArea();
</script>
