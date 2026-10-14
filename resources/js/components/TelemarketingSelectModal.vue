<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 scale-98"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-98"
    >
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs font-sans"
        @click.self="handleClose"
      >
        <!-- Modal Dialog Box -->
        <div class="bg-white rounded-xl shadow-xl border border-gray-200 max-w-lg w-full overflow-hidden text-xs">
          
          <!-- Modal Header (Sama persis dengan modal notifikasi dokumen SCM) -->
          <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-white">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-lg bg-gray-100 border border-gray-200 flex items-center justify-center text-gray-700">
                <svg class="w-4 h-4 fill-current text-gray-700" viewBox="0 0 24 24">
                  <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                </svg>
              </div>
              <div>
                <h3 class="text-xs font-semibold text-gray-900">Kirim WA Telemarketing</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                  {{ targetData?.dealer_name || targetData?.kode_bt || '-' }} • {{ targetData?.program_name || '-' }}
                </p>
              </div>
            </div>
            <button
              type="button"
              @click="handleClose"
              class="w-8 h-8 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100 flex items-center justify-center transition cursor-pointer"
              title="Tutup"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <!-- Modal Body -->
          <div class="p-5 space-y-3.5 text-xs font-sans">
            
            <!-- Ringkasan Klaim (Tabel Rapi, Ukuran Teks dan Font Sama Persis) -->
            <div>
              <p class="text-xs font-medium text-gray-700 mb-1.5">
                Data Klaim Program
              </p>
              <div class="overflow-hidden rounded-md border border-gray-200 bg-white">
                <table class="w-full text-xs text-left border-collapse">
                  <tbody class="divide-y divide-gray-100 text-xs">
                    <tr>
                      <td class="py-2 px-3 text-gray-700 font-normal w-36 whitespace-nowrap">Dealer / Toko</td>
                      <td class="py-2 px-1 text-gray-400 font-normal w-2 text-center">:</td>
                      <td class="py-2 px-3 text-gray-700 font-normal">{{ targetData?.dealer_name || targetData?.kode_bt || '-' }}</td>
                    </tr>
                    <tr v-if="targetData?.program_name">
                      <td class="py-2 px-3 text-gray-700 font-normal whitespace-nowrap">Nama Program</td>
                      <td class="py-2 px-1 text-gray-400 font-normal text-center">:</td>
                      <td class="py-2 px-3 text-gray-700 font-normal">{{ targetData.program_name }}</td>
                    </tr>
                    <tr>
                      <td class="py-2 px-3 text-gray-700 font-normal whitespace-nowrap">Nominal Potongan</td>
                      <td class="py-2 px-1 text-gray-400 font-normal text-center">:</td>
                      <td class="py-2 px-3 text-gray-700 font-normal">{{ formatCurrency(targetData?.net_pay || targetData?.dpp || 0) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Field Pilihan Kontak Telemarketing -->
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="block text-xs font-medium text-gray-700">
                  Tujuan WhatsApp Telemarketing
                </label>
                <span v-if="isLoadingContacts" class="text-xs text-gray-400">
                  Memuat kontak...
                </span>
              </div>

              <select
                v-model="selectedMode"
                class="w-full h-8.5 px-3 rounded-md border border-gray-300 text-xs text-gray-900 bg-white focus:outline-none focus:ring-1 focus:ring-black focus:border-black transition cursor-pointer"
              >
                <!-- Opsi kontak dari Master User -->
                <option
                  v-for="c in contacts"
                  :key="c.id"
                  :value="'user_' + c.id"
                >
                  {{ c.name }} ({{ c.whatsapp }})
                </option>

                <!-- Nomor Default Sistem -->
                <option value="__DEFAULT__">
                  Nomor Default Sistem ({{ defaultPhone || '081224290502' }})
                </option>

                <!-- Ketik Manual -->
                <option value="__MANUAL__">
                  + Ketik Nomor Manual
                </option>
              </select>

              <!-- Input jika pilih manual -->
              <div v-if="selectedMode === '__MANUAL__'" class="pt-2">
                <input
                  v-model="manualPhone"
                  type="text"
                  placeholder="081224290502"
                  class="w-full h-8.5 px-3 rounded-md border border-gray-300 text-xs text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-black focus:border-black transition font-sans"
                />
              </div>

              <p class="text-xs text-gray-500 mt-1">
                Daftar kontak diambil otomatis dari menu Master User (role: Telemarketing).
              </p>
            </div>

          </div>

          <!-- Modal Footer (Sama persis dengan modal notifikasi dokumen SCM) -->
          <div class="px-5 py-3 border-t border-gray-200 bg-gray-50 flex items-center justify-between gap-2">
            <button
              type="button"
              @click="handleClose"
              :disabled="isSubmitting"
              class="h-8.5 px-3.5 rounded-md border border-gray-200 bg-white hover:bg-gray-100 text-xs text-gray-700 font-normal transition cursor-pointer shadow-2xs disabled:opacity-50"
            >
              Batal
            </button>

            <button
              type="button"
              @click="handleSend"
              :disabled="isSubmitting || !effectivePhone"
              class="h-8.5 px-4 rounded-md bg-gray-900 hover:bg-black text-white text-xs font-medium transition cursor-pointer flex items-center gap-1.5 shadow-xs disabled:opacity-50"
            >
              <span v-if="isSubmitting" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
              <svg v-else class="w-3.5 h-3.5 fill-current text-white" viewBox="0 0 24 24">
                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
              </svg>
              <span>{{ isSubmitting ? 'Mengirim...' : 'Kirim via Gateway' }}</span>
            </button>
          </div>

        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  targetData: {
    type: Object,
    default: () => ({}),
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close', 'send']);

const contacts = ref([]);
const defaultPhone = ref('');
const isLoadingContacts = ref(false);
const selectedMode = ref('__DEFAULT__');
const manualPhone = ref('');

// Computed effective phone number to send to
const effectivePhone = computed(() => {
  if (selectedMode.value === '__MANUAL__') {
    return manualPhone.value.trim();
  }
  if (selectedMode.value === '__DEFAULT__') {
    return defaultPhone.value || '081224290502';
  }
  if (selectedMode.value.startsWith('user_')) {
    const userId = parseInt(selectedMode.value.replace('user_', ''), 10);
    const found = contacts.value.find(c => c.id === userId);
    return found ? found.whatsapp : '';
  }
  return '';
});

// Computed selected contact name
const selectedContactName = computed(() => {
  if (selectedMode.value.startsWith('user_')) {
    const userId = parseInt(selectedMode.value.replace('user_', ''), 10);
    const found = contacts.value.find(c => c.id === userId);
    return found ? found.name : '';
  }
  return '';
});

// Fetch telemarketing contacts from API
async function fetchContacts() {
  isLoadingContacts.value = true;
  try {
    const res = await axios.get('/api/telemarketing-contacts');
    if (res.data?.success) {
      contacts.value = res.data.contacts || [];
      defaultPhone.value = res.data.default_phone || '';

      // Auto-select contact based on dealer PIC or first contact
      if (props.targetData?.telemarketing) {
        const match = contacts.value.find(
          c => c.name.toLowerCase() === props.targetData.telemarketing.toLowerCase()
        );
        if (match) {
          selectedMode.value = 'user_' + match.id;
          return;
        }
      }

      // Use the telemarketing WA number from Master Program when no user matches
      if (props.targetData?.telemarketing_phone) {
        selectedMode.value = '__MANUAL__';
        manualPhone.value = props.targetData.telemarketing_phone;
        return;
      }

      if (contacts.value.length > 0) {
        selectedMode.value = 'user_' + contacts.value[0].id;
      } else {
        selectedMode.value = '__DEFAULT__';
      }
    }
  } catch (err) {
    selectedMode.value = '__DEFAULT__';
  } finally {
    isLoadingContacts.value = false;
  }
}

// Watch isOpen to fetch fresh contacts upon open
watch(
  () => props.isOpen,
  (val) => {
    if (val) {
      fetchContacts();
    }
  }
);

function handleClose() {
  if (!props.isSubmitting) {
    emit('close');
  }
}

function handleSend() {
  if (!effectivePhone.value) return;

  emit('send', {
    phone: effectivePhone.value,
    telemarketing_name: selectedContactName.value || null,
    target: props.targetData,
  });
}

function formatCurrency(val) {
  const num = typeof val === 'number' ? val : parseFloat(val) || 0;
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(num);
}
</script>
