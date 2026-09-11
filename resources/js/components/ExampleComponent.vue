<template>
  <div class="p-6 max-w-sm mx-auto bg-white rounded-xl shadow-md flex items-center space-x-4 border border-slate-100">
    <div class="shrink-0">
      <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center font-bold text-xl">
        V
      </div>
    </div>
    <div>
      <div class="text-xl font-medium text-black">Vue 3 Component</div>
      <p class="text-slate-500 text-sm">Vue 3 berhasil terpasang di Laravel!</p>
      <button 
        @click="count++" 
        class="mt-3 px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded shadow-sm transition"
      >
        Ditekan: {{ count }} kali
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const count = ref(0);
</script>
