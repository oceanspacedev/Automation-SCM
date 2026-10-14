<template>
  <Teleport to="body">
    <Transition name="modal-scale">
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/40 backdrop-blur-xs"
        @click.self="closeModal"
      >
        <div
          class="bg-white rounded-xl max-w-2xl w-full p-5 sm:p-6 shadow-xl border border-gray-200 space-y-4 max-h-[92vh] overflow-y-auto transform transition-all font-sans"
        >
          <!-- Header Modal -->
          <div class="flex items-center justify-between border-b border-gray-200 pb-2.5">
            <div>
              <h3 class="text-sm sm:text-base font-bold text-gray-950">
                Tambah Draft Invoice
              </h3>
              <p class="text-[11px] text-gray-500 mt-0.5">
                Input data draft invoice secara manual dengan sinkronisasi Data Master.
              </p>
            </div>
            <button
              type="button"
              @click="closeModal"
              class="text-gray-400 hover:text-gray-600 cursor-pointer p-1 rounded-md hover:bg-gray-100 transition"
            >
              <XIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- Error Alert if any -->
          <div v-if="errorMessage" class="p-2.5 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs flex items-center justify-between">
            <span>{{ errorMessage }}</span>
            <button type="button" @click="errorMessage = ''" class="text-red-400 hover:text-red-700 ml-2">&times;</button>
          </div>

          <!-- Form Body -->
          <form @submit.prevent="submitForm" class="space-y-4 text-xs">
            
            <!-- SECTION 1: MASTER DEALER & STORE DATA (No Icons) -->
            <div class="space-y-2.5">
              <div class="flex items-center justify-between">
                <span class="font-semibold text-gray-900 text-xs">
                  Data Dealer & Toko
                </span>
                <span v-if="selectedFromMaster" class="text-[11px] text-gray-600 bg-gray-100 border border-gray-200 px-2 py-0.5 rounded font-medium">
                  Tersinkron Data Master
                </span>
              </div>

              <!-- Quick Search Master Dealer -->
              <div class="relative" ref="dealerSearchRef">
                <label class="block font-medium text-gray-700 mb-1">
                  Cari dari Master Dealer
                </label>
                <div class="relative">
                  <SearchIcon class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                  <input
                    v-model="dealerSearchQuery"
                    @input="handleDealerSearchInput"
                    @focus="isDealerDropdownOpen = true"
                    type="text"
                    placeholder="Ketik nama dealer atau ID Realme..."
                    class="w-full h-8.5 pl-8 pr-7 rounded-lg border border-gray-300 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 transition placeholder:text-gray-400"
                  />
                  <button
                    v-if="dealerSearchQuery"
                    type="button"
                    @click="clearDealerSearch"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 p-0.5"
                  >
                    <XIcon class="w-3 h-3" />
                  </button>
                </div>

                <!-- Autocomplete Dropdown List -->
                <div
                  v-if="isDealerDropdownOpen && dealerSuggestions.length > 0"
                  class="absolute left-0 top-full mt-1 w-full bg-white rounded-lg shadow-lg border border-gray-200 z-50 max-h-52 overflow-y-auto divide-y divide-gray-100"
                >
                  <button
                    v-for="dealer in dealerSuggestions"
                    :key="dealer.id"
                    type="button"
                    @click="selectDealer(dealer)"
                    class="w-full text-left px-3 py-2 hover:bg-gray-50 transition cursor-pointer flex items-center justify-between text-xs group"
                  >
                    <div class="min-w-0 pr-2">
                      <div class="font-semibold text-gray-900 group-hover:text-gray-950 truncate">
                        {{ dealer.dealer_name }}
                      </div>
                      <div class="text-[11px] text-gray-500 flex items-center gap-2 mt-0.5">
                        <span>ID: {{ dealer.id_realme }}</span>
                        <span v-if="dealer.region">({{ dealer.region }})</span>
                        <span v-if="dealer.kode_bt">BT: {{ dealer.kode_bt }}</span>
                      </div>
                    </div>
                    <span class="text-[11px] text-blue-600 font-medium shrink-0">Pilih</span>
                  </button>
                </div>
              </div>

              <!-- Two Column Inputs for Dealer Details -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-0.5">
                <div>
                  <label class="block font-medium text-gray-700 mb-1">
                    ID Realme / Kode Dealer
                  </label>
                  <input
                    v-model="form.dealer_code"
                    type="text"
                    placeholder="Contoh: DLGD8266"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">
                    Nama Dealer / Toko <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="form.dealer_name"
                    type="text"
                    required
                    placeholder="Nama toko"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">
                    Wilayah (Region)
                  </label>
                  <div class="relative">
                    <select
                      v-model="form.region"
                      class="w-full h-8.5 px-3 pr-7 rounded-lg border border-gray-300 bg-white text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900 appearance-none cursor-pointer"
                    >
                      <option value="">Pilih Wilayah</option>
                      <option v-for="reg in regionOptions" :key="reg" :value="reg">{{ reg }}</option>
                    </select>
                    <ChevronDownIcon class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
                  </div>
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">
                    Kode BT
                  </label>
                  <input
                    v-model="form.kode_bt"
                    type="text"
                    placeholder="Contoh: BT001"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">
                    Customer by CSA (Bill To)
                  </label>
                  <div class="relative">
                    <select
                      v-model="selectedCustomer"
                      @change="handleCustomerSelectChange"
                      class="w-full h-8.5 px-3 pr-7 rounded-lg border border-gray-300 bg-white text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900 appearance-none cursor-pointer"
                    >
                      <option value="">Pilih Customer (Bill-To)</option>
                      <option v-for="c in customerOptions" :key="c.id" :value="c.name">
                        {{ c.code }} - {{ c.name }}
                      </option>
                      <option value="__OTHER__">+ Customer Lainnya (Input Manual)...</option>
                    </select>
                    <ChevronDownIcon class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
                  </div>
                  <input
                    v-if="selectedCustomer === '__OTHER__'"
                    v-model="form.customer_name"
                    type="text"
                    placeholder="Ketik nama customer / PT..."
                    class="mt-1.5 w-full h-8.5 px-3 rounded-lg border border-gray-300 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">
                    No. WhatsApp Toko
                  </label>
                  <input
                    v-model="form.whatsapp"
                    type="text"
                    placeholder="08xxxxxxxxxx"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 transition"
                  />
                </div>
              </div>
            </div>

            <hr class="border-gray-100" />

            <!-- SECTION 2: PROGRAM & INVOICE INFO (No Icons) -->
            <div class="space-y-2.5">
              <span class="font-semibold text-gray-900 text-xs">
                Informasi Program & Dokumen
                <span class="ml-1 font-normal text-gray-500">({{ area.label }})</span>
              </span>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <!-- Nama Program -->
                <div>
                  <label class="block font-medium text-gray-700 mb-1">
                    Nama Program <span class="text-red-500">*</span>
                  </label>
                  <div class="relative">
                    <select
                      v-model="selectedProgram"
                      @change="handleProgramSelectChange"
                      required
                      class="w-full h-8.5 px-3 pr-7 rounded-lg border border-gray-300 bg-white text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900 appearance-none cursor-pointer"
                    >
                      <option value="">Pilih Program</option>
                      <option v-for="p in programOptions" :key="p.name" :value="p.name">{{ p.name }}</option>
                      <option value="__OTHER__">+ Program Lainnya (Input Manual)...</option>
                    </select>
                    <ChevronDownIcon class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
                  </div>
                  <input
                    v-if="selectedProgram === '__OTHER__'"
                    v-model="form.program_name"
                    type="text"
                    required
                    placeholder="Ketik nama program baru..."
                    class="mt-1.5 w-full h-8.5 px-3 rounded-lg border border-gray-300 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 transition"
                  />
                </div>

                <!-- Kode Program (otomatis dari Master Program) -->
                <div>
                  <label class="block font-medium text-gray-700 mb-1">
                    Program
                  </label>
                  <input
                    v-model="form.program_code"
                    type="text"
                    placeholder="Otomatis terisi, cth: PROGRAM 345"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 transition uppercase"
                  />
                </div>

                <!-- Periode Program -->
                <div>
                  <label class="block font-medium text-gray-700 mb-1">
                    Periode Program
                  </label>
                  <input
                    v-model="form.program_period"
                    type="text"
                    placeholder="Contoh: Maret 2026"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 transition"
                  />
                </div>

                <!-- Tipe Invoice -->
                <div>
                  <label class="block font-medium text-gray-700 mb-1">
                    Tipe Invoice <span class="text-red-500">*</span>
                  </label>
                  <div class="relative">
                    <select
                      v-model="form.invoice_type"
                      required
                      class="w-full h-8.5 px-3 pr-7 rounded-lg border border-gray-300 bg-white text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900 appearance-none cursor-pointer"
                    >
                      <option value="NPS FL">NPS FL</option>
                      <option value="DSA">DSA</option>
                      <option value="REGULAR">REGULAR</option>
                    </select>
                    <ChevronDownIcon class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
                  </div>
                </div>

                <!-- Tanggal Invoice -->
                <div>
                  <label class="block font-medium text-gray-700 mb-1">
                    Tanggal Invoice
                  </label>
                  <input
                    v-model="form.invoice_date"
                    type="date"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 transition cursor-pointer"
                  />
                </div>

                <!-- No Credit Note -->
                <div>
                  <label class="block font-medium text-gray-700 mb-1">
                    No. Credit Note (CN) <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="form.cn_number"
                    type="text"
                    placeholder="Dipakai sebagai nomor invoice"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 transition"
                  />
                </div>

                <!-- RSM -->
                <div>
                  <label class="block font-medium text-gray-700 mb-1">
                    Nama RSM / Sales
                  </label>
                  <input
                    v-model="form.rsm"
                    type="text"
                    placeholder="Nama RSM"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 transition"
                  />
                </div>
              </div>
            </div>

            <hr class="border-gray-100" />

            <!-- SECTION 3: FINANCIAL & TAX INPUT MANUAL (No Icons, All Editable Inputs) -->
            <div class="space-y-2.5">
              <div class="flex items-center justify-between">
                <span class="font-semibold text-gray-900 text-xs">
                  Nilai Support & Pajak
                </span>
                <button
                  type="button"
                  @click="recalculateTaxesFromSupport"
                  class="text-[11px] text-blue-600 hover:text-blue-800 hover:underline cursor-pointer"
                >
                  Hitung Otomatis dari Support
                </button>
              </div>

              <!-- Baris 1: Support Amount, Jenis PPh, Jenis NPWP -->
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <!-- Support Amount -->
                <div>
                  <label class="block font-medium text-gray-700 mb-1">
                    Nilai Support (Rp) <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model.number="form.support_amount"
                    @input="handleSupportAmountInput"
                    type="number"
                    min="0"
                    step="any"
                    required
                    placeholder="0"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 transition"
                  />
                </div>

                <!-- Jenis PPh -->
                <div>
                  <label class="block font-medium text-gray-700 mb-1">
                    Jenis PPh
                  </label>
                  <div class="relative">
                    <select
                      v-model="form.pph_type"
                      @change="handleTaxTypeChange"
                      class="w-full h-8.5 px-3 pr-7 rounded-lg border border-gray-300 bg-white text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900 appearance-none cursor-pointer"
                    >
                      <option value="BADAN">BADAN (2%)</option>
                      <option value="PRIBADI">PRIBADI (2.5%)</option>
                      <option value="BONUS">BONUS (15%)</option>
                    </select>
                    <ChevronDownIcon class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
                  </div>
                </div>

                <!-- Jenis NPWP -->
                <div>
                  <label class="block font-medium text-gray-700 mb-1">
                    Jenis NPWP
                  </label>
                  <div class="relative">
                    <select
                      v-model="form.npwp_type"
                      @change="handleTaxTypeChange"
                      class="w-full h-8.5 px-3 pr-7 rounded-lg border border-gray-300 bg-white text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900 appearance-none cursor-pointer"
                    >
                      <option value="BADAN">BADAN</option>
                      <option value="PRIBADI">PRIBADI</option>
                      <option value="NON NPWP">NON NPWP</option>
                    </select>
                    <ChevronDownIcon class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
                  </div>
                </div>
              </div>

              <!-- Baris 2: Input Manual DPP, DPP Lain, PPN -->
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-0.5">
                <div>
                  <label class="block font-medium text-gray-700 mb-1">
                    DPP (Rp)
                  </label>
                  <input
                    v-model.number="form.dpp"
                    @input="handleManualDppChange"
                    type="number"
                    step="any"
                    placeholder="0"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">
                    DPP Lain (Rp)
                  </label>
                  <input
                    v-model.number="form.dpp_lain"
                    @input="handleManualDppLainChange"
                    type="number"
                    step="any"
                    placeholder="0"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">
                    PPN (Rp)
                  </label>
                  <input
                    v-model.number="form.ppn"
                    @input="handleManualTaxRecalc"
                    type="number"
                    step="any"
                    placeholder="0"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 transition"
                  />
                </div>
              </div>

              <!-- Baris 3: Input Manual PPh, Total Netpay, Kuantitas Qty -->
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-0.5">
                <div>
                  <label class="block font-medium text-gray-700 mb-1">
                    PPh (Rp)
                  </label>
                  <input
                    v-model.number="form.pph"
                    @input="handleManualTaxRecalc"
                    type="number"
                    step="any"
                    placeholder="0"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">
                    Total Netpay (Rp)
                  </label>
                  <input
                    v-model.number="form.netpay"
                    type="number"
                    step="any"
                    placeholder="0"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 bg-white text-xs font-semibold text-gray-950 focus:outline-none focus:ring-1 focus:ring-gray-900 transition"
                  />
                </div>

                <div>
                  <label class="block font-medium text-gray-700 mb-1">
                    Kuantitas (Qty)
                  </label>
                  <input
                    v-model.number="form.real_qty"
                    type="number"
                    min="0"
                    step="any"
                    placeholder="1"
                    class="w-full h-8.5 px-3 rounded-lg border border-gray-300 bg-white text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 transition"
                  />
                </div>
              </div>
            </div>

            <!-- Footer Actions (Exact style from screenshot) -->
            <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
              <button
                type="button"
                @click="closeModal"
                :disabled="isSubmitting"
                class="h-8 px-3 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 text-xs font-medium cursor-pointer transition disabled:opacity-50"
              >
                Batal
              </button>
              <button
                type="submit"
                :disabled="isSubmitting"
                class="h-8 px-4 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium cursor-pointer transition shadow-2xs flex items-center gap-1.5 disabled:opacity-50"
              >
                <Loader2Icon v-if="isSubmitting" class="w-3.5 h-3.5 animate-spin" />
                <span>{{ isSubmitting ? 'Menyimpan...' : 'Simpan' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, reactive, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { useArea } from '@/composables/useArea';
import {
  X as XIcon,
  Search as SearchIcon,
  ChevronDown as ChevronDownIcon,
  Loader2 as Loader2Icon,
} from 'lucide-vue-next';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close', 'created']);

const isSubmitting = ref(false);
const errorMessage = ref('');
const selectedFromMaster = ref(false);

const dealerSearchRef = ref(null);
const dealerSearchQuery = ref('');
const isDealerDropdownOpen = ref(false);
const dealerSuggestions = ref([]);
let searchDebounceTimeout = null;

const customerOptions = ref([]);

const regionOptions = [
  'BIG BANDUNG',
  'BIG KARAWANG',
  'BIG TASIK',
  'BIG CIREBON',
  'JABODETABEK',
  'JAWA BARAT',
];

const programOptions = ref([]);

const selectedProgram = ref('');
const selectedCustomer = ref('');

function handleProgramSelectChange() {
  if (selectedProgram.value === '__OTHER__') {
    form.program_name = '';
    form.program_code = '';
    return;
  }

  const program = programOptions.value.find(p => p.name === selectedProgram.value);
  form.program_name = selectedProgram.value;
  form.program_code = program?.code || '';
  if (program?.period) {
    form.program_period = program.period;
  }
}

function handleCustomerSelectChange() {
  if (selectedCustomer.value === '__OTHER__') {
    form.customer_name = '';
  } else {
    form.customer_name = selectedCustomer.value;
  }
}

async function fetchProgramOptions() {
  try {
    const res = await axios.get('/api/programs/options');
    if (res.data?.success) {
      programOptions.value = res.data.data || [];
    }
  } catch (e) {
    // Ignore error
  }
}

// Draft masuk ke area tab Draft yang sedang dibuka
const { area } = useArea();

const form = reactive({
  dealer_code: '',
  dealer_name: '',
  customer_name: '',
  region: '',
  kode_bt: '',
  rsm: '',
  program_name: '',
  area: area.value.value,
  program_code: '',
  program_period: '',
  invoice_type: 'NPS FL',
  invoice_date: new Date().toISOString().split('T')[0],
  cn_number: '',
  support_amount: 0,
  pph_type: 'BADAN',
  npwp_type: 'BADAN',
  dpp: 0,
  dpp_lain: 0,
  ppn: 0,
  pph: 0,
  netpay: 0,
  real_qty: 1,
  whatsapp: '',
  email: '',
  address: '',
});

// Calculate taxes based on current support_amount, pph_type, and npwp_type
function recalculateTaxesFromSupport() {
  const support = Number(form.support_amount) || 0;
  if (support <= 0) {
    form.dpp = 0;
    form.dpp_lain = 0;
    form.ppn = 0;
    form.pph = 0;
    form.netpay = 0;
    return;
  }

  const pphRate = form.pph_type === 'PRIBADI' ? 0.025 : (form.pph_type === 'BONUS' ? 0.15 : 0.02);

  // DPP Calculation
  const dpp = form.pph_type === 'BONUS' ? support : Math.round(support / 1.11);

  // DPP Lain Calculation
  const isDppLain = form.npwp_type !== 'NON NPWP' && form.pph_type !== 'BONUS';
  const dppLain = isDppLain ? Math.round((dpp * 11) / 12) : 0;

  // PPN & PPh
  const ppn = Math.round(dppLain * 0.12);
  const pph = Math.round(dpp * pphRate);
  const netpay = Math.round(dpp + ppn - pph);

  form.dpp = dpp;
  form.dpp_lain = dppLain;
  form.ppn = ppn;
  form.pph = pph;
  form.netpay = netpay;
}

// When user edits Nilai Support, automatically populate DPP, PPN, PPh, Netpay as starting values
function handleSupportAmountInput() {
  recalculateTaxesFromSupport();
}

function handleTaxTypeChange() {
  recalculateTaxesFromSupport();
}

// When user edits DPP manually
function handleManualDppChange() {
  const dpp = Number(form.dpp) || 0;
  const pphRate = form.pph_type === 'PRIBADI' ? 0.025 : (form.pph_type === 'BONUS' ? 0.15 : 0.02);
  
  if (form.dpp_lain > 0 || (form.npwp_type !== 'NON NPWP' && form.pph_type !== 'BONUS')) {
    form.dpp_lain = Math.round((dpp * 11) / 12);
    form.ppn = Math.round(form.dpp_lain * 0.12);
  }
  form.pph = Math.round(dpp * pphRate);
  form.netpay = Math.round(dpp + (Number(form.ppn) || 0) - form.pph);
}

// When user edits DPP Lain manually
function handleManualDppLainChange() {
  const dppLain = Number(form.dpp_lain) || 0;
  form.ppn = Math.round(dppLain * 0.12);
  handleManualTaxRecalc();
}

// When user edits PPN or PPh manually, re-calculate Netpay = DPP + PPN - PPh
function handleManualTaxRecalc() {
  const dpp = Number(form.dpp) || 0;
  const ppn = Number(form.ppn) || 0;
  const pph = Number(form.pph) || 0;
  form.netpay = Math.round(dpp + ppn - pph);
}

function handleDealerSearchInput() {
  if (searchDebounceTimeout) clearTimeout(searchDebounceTimeout);
  searchDebounceTimeout = setTimeout(async () => {
    const q = dealerSearchQuery.value.trim();
    if (q.length < 1) {
      dealerSuggestions.value = [];
      return;
    }
    try {
      const res = await axios.get('/api/dealers/suggestions', { params: { query: q, limit: 15 } });
      if (res.data?.success && res.data.data) {
        dealerSuggestions.value = res.data.data;
        isDealerDropdownOpen.value = true;
      }
    } catch (e) {
      dealerSuggestions.value = [];
    }
  }, 200);
}

function clearDealerSearch() {
  dealerSearchQuery.value = '';
  dealerSuggestions.value = [];
  isDealerDropdownOpen.value = false;
}

async function selectDealer(dealer) {
  form.dealer_code = dealer.id_realme || '';
  form.dealer_name = dealer.dealer_name || '';
  if (dealer.region) {
    form.region = dealer.region.toUpperCase();
  }
  if (dealer.kode_bt) {
    form.kode_bt = dealer.kode_bt;
  }
  if (dealer.whatsapp) {
    form.whatsapp = dealer.whatsapp;
  }

  isDealerDropdownOpen.value = false;
  selectedFromMaster.value = true;
  dealerSearchQuery.value = `${dealer.dealer_name} (${dealer.id_realme})`;

  // Lookup full details including address
  try {
    const res = await axios.get('/api/dealers/lookup', { params: { id_realme: dealer.kode_bt || dealer.id_realme } });
    if (res.data?.success && res.data.found && res.data.data) {
      const full = res.data.data;
      if (full.address) {
        form.address = full.address;
      }
      if (full.email && !form.email) {
        form.email = full.email;
      }
    }
  } catch (e) {
    // Ignore error
  }
}

async function fetchMasterCustomers() {
  try {
    const res = await axios.get('/api/customers/options');
    if (res.data?.success && res.data.data) {
      customerOptions.value = res.data.data;
    }
  } catch (e) {
    // Ignore error
  }
}

function handleClickOutside(event) {
  if (dealerSearchRef.value && !dealerSearchRef.value.contains(event.target)) {
    isDealerDropdownOpen.value = false;
  }
}

function resetForm() {
  form.dealer_code = '';
  form.dealer_name = '';
  form.customer_name = '';
  form.region = '';
  form.kode_bt = '';
  form.rsm = '';
  form.program_name = '';
  form.program_code = '';
  form.program_period = '';
  form.invoice_type = 'NPS FL';
  form.area = area.value.value;
  form.invoice_date = new Date().toISOString().split('T')[0],
  form.cn_number = '';
  form.support_amount = 0;
  form.pph_type = 'BADAN';
  form.npwp_type = 'BADAN';
  form.dpp = 0;
  form.dpp_lain = 0;
  form.ppn = 0;
  form.pph = 0;
  form.netpay = 0;
  form.real_qty = 1;
  form.whatsapp = '';
  form.email = '';
  form.address = '';
  dealerSearchQuery.value = '';
  selectedFromMaster.value = false;
  selectedProgram.value = '';
  selectedCustomer.value = '';
  errorMessage.value = '';
}

function closeModal() {
  if (isSubmitting.value) return;
  emit('close');
}

async function submitForm() {
  if (!form.dealer_name) {
    errorMessage.value = 'Nama dealer wajib diisi.';
    return;
  }
  if (!form.program_name) {
    errorMessage.value = 'Nama program wajib diisi.';
    return;
  }
  if (!form.cn_number?.trim()) {
    errorMessage.value = 'No CN wajib diisi karena dipakai sebagai nomor invoice.';
    return;
  }
  if (form.support_amount === null || form.support_amount === undefined || form.support_amount < 0) {
    errorMessage.value = 'Nilai support wajib diisi.';
    return;
  }

  isSubmitting.value = true;
  errorMessage.value = '';

  try {
    const res = await axios.post('/api/drafts', form);
    if (res.data?.success) {
      emit('created', res.data.data);
      resetForm();
      emit('close');
    } else {
      errorMessage.value = res.data?.message || 'Gagal menyimpan draft invoice.';
    }
  } catch (error) {
    if (error.response?.data?.errors) {
      const errs = Object.values(error.response.data.errors).flat();
      errorMessage.value = errs.join(', ');
    } else {
      errorMessage.value = error.response?.data?.message || 'Terjadi kesalahan sistem saat menyimpan.';
    }
  } finally {
    isSubmitting.value = false;
  }
}

watch(
  () => form.customer_name,
  (val) => {
    if (!val) {
      if (selectedCustomer.value !== '__OTHER__') selectedCustomer.value = '';
    } else if (customerOptions.value.some(c => c.name === val)) {
      selectedCustomer.value = val;
    } else {
      selectedCustomer.value = '__OTHER__';
    }
  }
);

watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      resetForm();
      fetchMasterCustomers();
      fetchProgramOptions();
    }
  }
);

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
  fetchProgramOptions();
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<style scoped>
.modal-scale-enter-active,
.modal-scale-leave-active {
  transition: all 0.2s ease-out;
}
.modal-scale-enter-from,
.modal-scale-leave-to {
  opacity: 0;
  transform: scale(0.97);
}
</style>
