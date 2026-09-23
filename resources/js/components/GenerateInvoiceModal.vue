<template>
  <Teleport to="body">
    <Transition name="modal-scale">
      <div
        v-if="modelValue"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @click.self="handleCancel"
      >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/40 backdrop-blur-xs transition-opacity" @click="handleCancel" />

        <!-- Modal Box -->
        <div class="relative z-10 w-full max-w-md bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden transform transition-all font-sans">
          <!-- Header -->
          <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
              <h3 class="text-base font-semibold text-gray-900 leading-snug">
                {{ title }}
              </h3>
              <p v-if="subtitle" class="text-xs text-gray-500 mt-0.5">
                {{ subtitle }}
              </p>
            </div>
            <button
              type="button"
              @click="handleCancel"
              :disabled="loading"
              class="w-7 h-7 flex items-center justify-center rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition cursor-pointer disabled:opacity-50"
            >
              <XIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- Body -->
          <div class="p-6 space-y-4">
            <!-- Target Summary -->
            <div v-if="targetInfo" class="text-xs text-gray-500">
              Target: <span class="font-medium text-gray-800">{{ targetInfo }}</span>
            </div>

            <!-- Bill To Dropdown -->
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1.5">
                Pilih Bill To
              </label>
              <select
                v-model="selectedKey"
                class="w-full h-9 px-3 rounded-lg border border-gray-200 bg-white text-sm text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-400 focus:border-gray-400 transition cursor-pointer"
              >
                <option v-for="opt in billToOptions" :key="opt.code" :value="opt.code">
                  {{ opt.code }} - {{ opt.name }}
                </option>
              </select>
            </div>

            <!-- Clean Neutral Preview Box -->
            <div v-if="currentSelected" class="p-3 bg-gray-50 rounded-lg border border-gray-200 text-xs space-y-1">
              <div class="font-semibold text-gray-900">
                {{ currentSelected.name }}
              </div>
              <div class="text-gray-600 whitespace-pre-line leading-relaxed">
                {{ currentSelected.address || '-' }}
              </div>
              <div v-if="currentSelected.npwp" class="text-gray-500 font-mono text-[11px] pt-0.5">
                NPWP: {{ currentSelected.npwp }}
              </div>
            </div>

            <p class="text-[11px] text-gray-400 leading-normal">
              Informasi di atas akan dicetak pada bagian Bill To di lembar invoice.
            </p>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-2 px-6 py-3.5 bg-gray-50 border-t border-gray-100">
            <button
              type="button"
              @click="handleCancel"
              :disabled="loading"
              class="h-8.5 px-3.5 rounded-lg border border-gray-200 bg-white text-xs font-medium text-gray-700 hover:bg-gray-100 transition cursor-pointer disabled:opacity-50"
            >
              {{ cancelText }}
            </button>
            <button
              type="button"
              @click="handleConfirm"
              :disabled="loading || !selectedKey"
              class="h-8.5 px-4 rounded-lg bg-[#1D70F5] text-white text-xs font-medium hover:bg-blue-600 transition cursor-pointer disabled:opacity-50 inline-flex items-center gap-1.5 shadow-2xs"
            >
              <svg v-if="loading" class="animate-spin -ml-0.5 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>{{ loading ? 'Memproses...' : confirmText }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { X as XIcon } from '@lucide/vue';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: 'Terbitkan Invoice' },
  subtitle: { type: String, default: '' },
  targetInfo: { type: String, default: '' },
  confirmText: { type: String, default: 'Ya, Terbitkan Invoice' },
  cancelText: { type: String, default: 'Batal' },
  loading: { type: Boolean, default: false },
  defaultBillTo: { type: String, default: 'CV TOP' },
});

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel']);

const defaultOptions = [
  {
    code: 'CV TOP',
    name: 'CV TOP SELULAR',
    address: 'Pertatean Blok - No. 011 RT. 001 RW. 005 Kel. Pekalipan\nPekaliapan - Kota Cirebon, Jawa Barat 45117',
    npwp: '31.352.339.1-426.000',
  },
  {
    code: 'PT RISM',
    name: 'PT RETAIL INDONESIA SELALU MAJU',
    address: 'RUKAN MANGGA DUA SQUARE H-18 ANCOL PADEMANGAN JAK',
    npwp: '61.186.183.2-044.000',
  },
  {
    code: 'PT MSI',
    name: 'PT MEDIA SELULER INDONESIA',
    address: 'Jl. Raya Cirebon - Bandung No.109, Kertawinangun, Kec. Kedawung, Kabupaten Cirebon, Jawa Barat 45153',
    npwp: '01.555.666.7-011.000',
  },
];

const billToOptions = ref([...defaultOptions]);
const selectedKey = ref(props.defaultBillTo || 'CV TOP');

const fetchOptions = async () => {
  try {
    const res = await axios.get('/api/bill-to-options');
    if (Array.isArray(res.data) && res.data.length > 0) {
      billToOptions.value = res.data;
    }
  } catch {
    // Keep fallback options
  }
};

watch(() => props.modelValue, (isOpen) => {
  if (isOpen) {
    if (!selectedKey.value) {
      selectedKey.value = props.defaultBillTo || billToOptions.value[0]?.code || 'CV TOP';
    }
    fetchOptions();
  }
});

const currentSelected = computed(() => {
  return billToOptions.value.find(o => o.code === selectedKey.value) || billToOptions.value[0];
});

const handleCancel = () => {
  if (props.loading) return;
  emit('update:modelValue', false);
  emit('cancel');
};

const handleConfirm = () => {
  if (props.loading || !selectedKey.value) return;
  emit('confirm', selectedKey.value);
};

onMounted(() => {
  fetchOptions();
});
</script>

<style scoped>
.modal-scale-enter-active,
.modal-scale-leave-active {
  transition: all 0.15s ease-out;
}

.modal-scale-enter-from,
.modal-scale-leave-to {
  opacity: 0;
  transform: scale(0.97);
}
</style>
