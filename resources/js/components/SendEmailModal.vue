<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div
        v-if="modelValue"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @click.self="close"
      >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="close" />

        <!-- Modal Card -->
        <div class="relative z-10 w-full max-w-lg bg-white rounded-2xl shadow-2xl overflow-hidden border border-gray-100 my-8">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gray-50/50">
            <div>
              <h3 class="text-base font-semibold text-gray-900">Kirim Invoice</h3>
              <p class="text-xs text-gray-500">{{ invoiceNumber }} &bull; {{ dealerName || customerName || 'Pemberitahuan Invoice' }}</p>
            </div>
            <button
              @click="close"
              class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition cursor-pointer"
            >
              <XIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- Body -->
          <div class="px-6 py-4 space-y-4 max-h-[72vh] overflow-y-auto">
            <!-- 1. Kirim dari (Sender Account Selector) -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">
                Kirim dari
              </label>
              <div class="relative">
                <select
                  v-model="senderId"
                  :disabled="loading || accountsLoading"
                  class="w-full h-11 px-3 pr-8 rounded-lg border border-gray-300 bg-white text-sm text-gray-900 focus:outline-none focus:ring-1 focus:ring-black focus:border-black transition disabled:opacity-50 cursor-pointer"
                >
                  <option v-for="acc in emailAccounts" :key="acc.id" :value="acc.id">
                    {{ acc.name }} &lt;{{ acc.email }}&gt; {{ acc.is_default ? '(Default)' : '' }}
                  </option>
                </select>
              </div>
              <p v-if="selectedSender" class="mt-1 text-xs text-gray-500">
                Pengirim: <span class="font-medium text-gray-800">{{ selectedSender.name }}</span> ({{ selectedSender.email }})
              </p>
            </div>

            <!-- 2. Kepada (Recipient Email) -->
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs font-semibold uppercase tracking-wider text-gray-700">
                  Kepada
                </label>
                <span v-if="defaultEmail && email === defaultEmail" class="text-[10px] font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200/50">
                  Otomatis dari Data
                </span>
              </div>
              <input
                v-model="email"
                type="email"
                placeholder="customer@email.com"
                :disabled="loading"
                class="w-full h-10 px-3 rounded-lg border border-gray-300 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-black focus:border-black transition disabled:opacity-50"
              />
            </div>

            <!-- 3. CC (Carbon Copy) -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">
                CC <span class="text-gray-400 font-normal normal-case">(opsional, pisahkan dengan koma)</span>
              </label>
              <input
                v-model="ccInput"
                type="text"
                placeholder="rekan@email.com, finance@email.com"
                :disabled="loading"
                class="w-full h-10 px-3 rounded-lg border border-gray-300 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-black focus:border-black transition disabled:opacity-50"
              />
            </div>

            <!-- 4. Nomor WhatsApp (opsional) -->
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs font-semibold uppercase tracking-wider text-gray-700">
                  WhatsApp <span class="text-gray-400 font-normal normal-case">(opsional)</span>
                </label>
                <span v-if="defaultWhatsapp && whatsapp === defaultWhatsapp" class="text-[10px] font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200/50">
                  Otomatis dari Data
                </span>
              </div>
              <input
                v-model="whatsapp"
                type="text"
                placeholder="081234567890 / 6281234567890"
                :disabled="loading"
                class="w-full h-10 px-3 rounded-lg border border-gray-300 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-black focus:border-black transition disabled:opacity-50"
              />
            </div>

            <!-- 5. Subject -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">
                Subject
              </label>
              <input
                v-model="subject"
                type="text"
                placeholder="Invoice ..."
                :disabled="loading"
                class="w-full h-10 px-3 rounded-lg border border-gray-300 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-black focus:border-black transition disabled:opacity-50"
              />
            </div>

            <!-- 6. Pesan -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">
                Pesan
              </label>
              <textarea
                v-model="message"
                rows="3"
                placeholder="Pesan pengantar invoice..."
                :disabled="loading"
                class="w-full p-3 rounded-lg border border-gray-300 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-black focus:border-black transition disabled:opacity-50 resize-none leading-relaxed"
              ></textarea>
            </div>

            <!-- 7. Lampiran -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">
                Lampiran
              </label>
              <div class="flex items-center justify-between p-2.5 rounded-lg border border-gray-200 bg-gray-50 text-xs text-gray-700">
                <div class="flex items-center gap-2">
                  <FileTextIcon class="w-4 h-4 text-gray-500" />
                  <span class="font-medium text-gray-900">{{ invoiceNumber }}.pdf</span>
                  <span class="text-gray-400">&bull; Invoice PDF Resmi</span>
                </div>
                <span class="text-[10px] font-medium uppercase tracking-wider text-gray-500 bg-white border border-gray-200 px-2 py-0.5 rounded">
                  Otomatis
                </span>
              </div>
            </div>

            <!-- Error Notification -->
            <Alert v-if="error" variant="destructive" class="py-2.5">
              <AlertCircleIcon class="h-4 w-4" />
              <AlertDescription class="text-xs leading-relaxed">{{ error }}</AlertDescription>
            </Alert>

            <!-- Success Notification -->
            <Alert v-if="success" class="py-2.5">
              <CheckCircleIcon class="h-4 w-4 text-emerald-600" />
              <AlertDescription class="text-xs text-emerald-700 font-medium">{{ success }}</AlertDescription>
            </Alert>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-2.5 px-6 py-4 border-t border-gray-100 bg-gray-50/70">
            <button
              @click="close"
              :disabled="loading"
              class="h-9 px-4 rounded-lg border border-gray-200 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 transition disabled:opacity-50 cursor-pointer shadow-xs"
            >
              {{ success ? 'Tutup' : 'Batal' }}
            </button>
            <button
              v-if="!success"
              @click="send"
              :disabled="loading || (!email && !whatsapp)"
              class="h-9 px-5 rounded-lg bg-[#1D70F5] text-white text-xs font-medium hover:bg-blue-600 transition disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-2 shadow-xs cursor-pointer"
            >
              <span v-if="loading" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
              <SendIcon v-else class="w-3.5 h-3.5" />
              <span>{{ loading ? 'Mengirim...' : 'Kirim Invoice' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import axios from 'axios';
import { XIcon, Send as SendIcon, FileText as FileTextIcon, AlertCircle as AlertCircleIcon, CheckCircle as CheckCircleIcon } from '@lucide/vue';
import { Alert, AlertDescription } from '@/components/ui/alert';

const props = defineProps({
  modelValue: Boolean,
  invoiceId: [String, Number],
  invoiceNumber: String,
  dealerName: String,
  customerName: String,
  defaultEmail: String,
  defaultWhatsapp: String,
});

const emit = defineEmits(['update:modelValue', 'sent']);

const defaultFallbackAccounts = [
  { id: 1, name: 'Rebate. MSI', email: 'ade@mediaselulerindonesia.com', is_default: true },
  { id: 2, name: 'Program CS', email: 'admin.scm@completeselular.com', is_default: false },
  { id: 3, name: 'Program MSI', email: 'admin.scm@mediaselulerindonesia.com', is_default: false },
  { id: 4, name: 'Program SMI', email: 'admin.scm@satumediaindonesia.com', is_default: false },
  { id: 5, name: 'Program Top', email: 'admin.scm@topselular.com', is_default: false },
];

const emailAccounts = ref([...defaultFallbackAccounts]);
const accountsLoading = ref(false);
const senderId = ref(1);
const email = ref('');
const whatsapp = ref('');
const ccInput = ref('');
const subject = ref('');
const message = ref('');
const loading = ref(false);
const error = ref('');
const success = ref('');

const selectedSender = computed(() => {
  return emailAccounts.value.find(acc => acc.id === senderId.value) || emailAccounts.value[0] || null;
});

const fetchEmailAccounts = async () => {
  try {
    accountsLoading.value = true;
    const res = await axios.get('/api/email-accounts');
    if (Array.isArray(res.data.data) && res.data.data.length > 0) {
      emailAccounts.value = res.data.data;
      if (!senderId.value || !emailAccounts.value.some(acc => acc.id === senderId.value)) {
        const defaultAcc = emailAccounts.value.find(acc => acc.is_default);
        senderId.value = defaultAcc ? defaultAcc.id : emailAccounts.value[0].id;
      }
    }
  } catch (e) {
    console.error('Gagal mengambil daftar email accounts:', e);
  } finally {
    accountsLoading.value = false;
  }
};

onMounted(() => {
  fetchEmailAccounts();
});

// Reset state and prefill when modal opens
watch(() => props.modelValue, (val) => {
  if (val) {
    email.value = props.defaultEmail || '';
    whatsapp.value = props.defaultWhatsapp || '';
    ccInput.value = '';
    subject.value = `Invoice ${props.invoiceNumber || ''} - ${props.dealerName || props.customerName || 'SCM'}`;
    message.value = `Bersama ini kami lampirkan dokumen invoice resmi ${props.invoiceNumber || ''}. Mohon dapat diproses sesuai ketentuan. Terima kasih.`;
    error.value = '';
    success.value = '';

    if (!senderId.value && emailAccounts.value.length > 0) {
      const defaultAcc = emailAccounts.value.find(acc => acc.is_default);
      senderId.value = defaultAcc ? defaultAcc.id : emailAccounts.value[0].id;
    }
  }
});

watch(() => props.defaultEmail, (val) => {
  if (props.modelValue && val) {
    email.value = val;
  }
});

watch(() => props.defaultWhatsapp, (val) => {
  if (props.modelValue && val) {
    whatsapp.value = val;
  }
});

const close = () => {
  if (loading.value) return;
  emit('update:modelValue', false);
};

const send = async () => {
  if ((!email.value && !whatsapp.value) || loading.value || success.value) return;

  error.value = '';
  loading.value = true;

  const ccArray = ccInput.value
    ? ccInput.value.split(',').map(s => s.trim()).filter(Boolean)
    : [];

  try {
    const res = await axios.post(`/api/invoices/${props.invoiceId}/send`, {
      sender_id: senderId.value,
      email: email.value || null,
      whatsapp: whatsapp.value || null,
      cc: ccArray,
      subject: subject.value || null,
      message: message.value || null,
    });

    success.value = res.data.message;
    emit('sent', {
      invoiceId: props.invoiceId,
      email: email.value,
      whatsapp: whatsapp.value,
      senderId: senderId.value,
    });
  } catch (err) {
    if (err.response?.data?.errors?.email) {
      error.value = err.response.data.errors.email[0];
    } else if (err.response?.data?.errors?.whatsapp) {
      error.value = err.response.data.errors.whatsapp[0];
    } else if (err.response?.data?.message) {
      error.value = err.response.data.message;
    } else {
      error.value = 'Gagal mengirim invoice. Silakan coba lagi.';
    }
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
.modal-fade-enter-active .relative,
.modal-fade-leave-active .relative {
  transition: transform 0.2s ease;
}
.modal-fade-enter-from .relative {
  transform: translateY(8px) scale(0.98);
}
</style>
