<template>
  <!-- Pilihan filter yang bisa dicari (mis. Nama Program, Dealer); daftar tampil di bawah tombol, tidak menutupi isian lain -->
  <div class="space-y-1" ref="root">
    <label class="text-[11px] font-medium text-gray-700">{{ label }}</label>
    <div>
      <button
        type="button"
        @click="toggle"
        class="h-8 w-full rounded-md border border-gray-200 bg-white px-2.5 text-xs flex items-center justify-between gap-2 hover:border-gray-300 focus:outline-none focus:ring-1 focus:ring-black transition cursor-pointer"
        :class="modelValue ? 'border-gray-900 text-gray-900 font-medium bg-gray-50/60' : 'text-gray-700'"
        :title="modelValue || label"
      >
        <span class="truncate text-left">{{ modelValue || allLabel }}</span>
        <div class="flex items-center gap-1 shrink-0">
          <span
            v-if="modelValue"
            @click.stop="select('')"
            class="text-gray-400 hover:text-gray-700 p-0.5 rounded cursor-pointer"
            :title="`Hapus filter ${label.toLowerCase()}`"
          >
            <XIcon class="w-3 h-3" />
          </span>
          <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400 transition-transform duration-150" :class="open && 'rotate-180'" />
        </div>
      </button>

      <div v-if="open" class="mt-1.5 w-full bg-white border border-gray-200 rounded-lg shadow-xs overflow-hidden text-xs">
        <div class="p-2 border-b border-gray-100 bg-gray-50/70 flex items-center gap-2">
          <SearchIcon class="w-3.5 h-3.5 text-gray-400 shrink-0" />
          <input
            ref="searchInput"
            v-model="query"
            type="text"
            :placeholder="searchPlaceholder"
            class="w-full bg-transparent border-none text-xs text-gray-900 placeholder-gray-400 focus:outline-none"
            @click.stop
            @keydown.esc="open = false"
          />
          <button v-if="query" @click.stop="query = ''" type="button" class="text-gray-400 hover:text-gray-600 cursor-pointer">
            <XIcon class="w-3 h-3" />
          </button>
        </div>
        <div class="max-h-48 overflow-y-auto py-1 divide-y divide-gray-50">
          <button
            type="button"
            @click="select('')"
            class="w-full text-left px-3 py-1.5 flex items-center justify-between hover:bg-gray-50 transition cursor-pointer text-xs"
            :class="!modelValue ? 'bg-gray-50 font-medium text-gray-900' : 'text-gray-600'"
          >
            <span>{{ allLabel }} ({{ options.length }})</span>
            <CheckIcon v-if="!modelValue" class="w-3.5 h-3.5 text-gray-900 shrink-0" />
          </button>
          <button
            v-for="option in filteredOptions"
            :key="option"
            type="button"
            @click="select(option)"
            class="w-full text-left px-3 py-1.5 flex items-center justify-between hover:bg-gray-50 transition cursor-pointer text-xs"
            :class="modelValue === option ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700'"
          >
            <span class="truncate pr-2" :title="option">{{ option }}</span>
            <CheckIcon v-if="modelValue === option" class="w-3.5 h-3.5 text-gray-900 shrink-0" />
          </button>
          <div v-if="filteredOptions.length === 0" class="px-3 py-2 text-center text-gray-400 text-xs">{{ emptyText }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import {
  Check as CheckIcon,
  ChevronDown as ChevronDownIcon,
  Search as SearchIcon,
  X as XIcon,
} from 'lucide-vue-next';

const props = defineProps({
  modelValue: { type: String, default: '' },
  options: { type: Array, default: () => [] },
  label: { type: String, required: true },
  allLabel: { type: String, required: true },
  searchPlaceholder: { type: String, default: 'Cari...' },
  emptyText: { type: String, default: 'Tidak ada yang cocok' },
});

const emit = defineEmits(['update:modelValue', 'change']);

const root = ref(null);
const searchInput = ref(null);
const open = ref(false);
const query = ref('');

const filteredOptions = computed(() => {
  const q = query.value.trim().toLowerCase();
  return q ? props.options.filter((option) => option && option.toLowerCase().includes(q)) : props.options;
});

const toggle = async () => {
  open.value = !open.value;
  if (open.value) {
    query.value = '';
    await nextTick();
    searchInput.value?.focus();
  }
};

const select = (value) => {
  open.value = false;
  emit('update:modelValue', value);
  emit('change', value);
};

const closeOnOutsideClick = (event) => {
  if (root.value && !root.value.contains(event.target)) open.value = false;
};

onMounted(() => document.addEventListener('click', closeOnOutsideClick));
onUnmounted(() => document.removeEventListener('click', closeOnOutsideClick));
</script>
