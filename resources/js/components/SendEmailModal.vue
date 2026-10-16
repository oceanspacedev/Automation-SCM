<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div
        v-if="modelValue"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @click.self="close"
      >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/40 backdrop-blur-xs" @click="close" />

        <!-- Modal Card -->
        <div class="relative z-10 w-full max-w-lg flex flex-col max-h-[90vh] bg-white rounded-xl shadow-xl border border-gray-200" role="dialog" aria-modal="true">
          <!-- Header -->
          <div class="px-6 pt-5 pb-4 border-b border-gray-100 flex items-start justify-between gap-4 shrink-0">
            <div class="min-w-0">
              <h3 class="text-base font-semibold text-gray-950">Kirim Invoice</h3>
              <p class="mt-0.5 text-xs text-gray-500 truncate">{{ invoiceNumber }} · {{ dealerName || customerName || 'Pemberitahuan Invoice' }}</p>
            </div>
            <button type="button" @click="close" class="p-1 -m-1 text-gray-400 hover:text-gray-700 cursor-pointer shrink-0" title="Tutup">
              <XIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- Body -->
          <div class="px-6 py-5 space-y-4 overflow-y-auto">
            <div>
              <label for="send-sender" :class="labelClass">Kirim dari</label>
              <select
                id="send-sender"
                v-model="senderId"
                :disabled="loading || accountsLoading"
                :class="[inputClass, 'pr-8 cursor-pointer']"
              >
                <option v-for="acc in emailAccounts" :key="acc.id" :value="acc.id">
                  {{ acc.name }} &lt;{{ acc.email }}&gt;{{ acc.is_default ? ' · Default' : '' }}
                </option>
              </select>
            </div>

            <!-- Tujuan: terisi dari data invoice, boleh diubah -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label for="send-email" :class="labelClass">Email tujuan</label>
                <input id="send-email" v-model="email" type="email" placeholder="customer@email.com" :disabled="loading" :class="inputClass" />
                <p v-if="defaultEmail && email === defaultEmail" class="mt-1 text-[11px] text-gray-400">Dari data invoice</p>
              </div>
              <div>
                <label for="send-wa" :class="labelClass">WhatsApp <span class="font-normal text-gray-400">(opsional)</span></label>
                <input id="send-wa" v-model="whatsapp" type="text" placeholder="08xxxxxxxxxx" :disabled="loading" :class="inputClass" />
                <p v-if="defaultWhatsapp && whatsapp === defaultWhatsapp" class="mt-1 text-[11px] text-gray-400">Dari data invoice</p>
              </div>
            </div>

            <div>
              <label for="send-cc" :class="labelClass">CC <span class="font-normal text-gray-400">(opsional, pisahkan dengan koma)</span></label>
              <input id="send-cc" v-model="ccInput" type="text" placeholder="rekan@email.com, finance@email.com" :disabled="loading" :class="inputClass" />
            </div>

            <div>
              <label for="send-subject" :class="labelClass">Subjek</label>
              <input id="send-subject" v-model="subject" type="text" placeholder="Invoice ..." :disabled="loading" :class="inputClass" />
            </div>

            <div>
              <label for="send-message" :class="labelClass">Pesan</label>
              <textarea
                id="send-message"
                v-model="message"
                rows="3"
                placeholder="Pesan pengantar invoice..."
                :disabled="loading"
                class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-white text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 transition disabled:opacity-50 resize-none leading-relaxed"
              ></textarea>
            </div>

            <p class="text-xs text-gray-500">
              Lampiran: <span class="text-gray-900">{{ invoiceNumber }}.pdf</span> (otomatis)
            </p>

            <p v-if="error" class="text-xs text-red-600 leading-relaxed">{{ error }}</p>
            <p v-if="success" class="text-xs text-emerald-700 leading-relaxed">{{ success }}</p>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-end gap-2 shrink-0">
            <button
              type="button"
              @click="close"
              :disabled="loading"
              class="h-9 px-4 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 transition disabled:opacity-50 cursor-pointer"
            >
              {{ success ? 'Tutup' : 'Batal' }}
            </button>
            <button
              v-if="!success"
              type="button"
              @click="send"
              :disabled="loading || (!email && !whatsapp)"
              class="h-9 px-4 rounded-lg bg-gray-900 hover:bg-gray-800 dark:bg-white dark:hover:bg-slate-200 text-xs font-medium text-white dark:text-gray-900 transition disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
            >
              {{ loading ? 'Mengirim...' : 'Kirim Invoice' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import axios from 'axios';
import { XIcon } from '@lucide/vue';

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

const labelClass = 'block text-xs font-medium text-gray-700 mb-1.5';
const inputClass = 'w-full h-9 px-3 rounded-lg border border-gray-300 bg-white text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 transition disabled:opacity-50';

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
