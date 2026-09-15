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
        <div class="relative z-10 w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
              <div class="flex items-center justify-center w-9 h-9 rounded-full bg-gray-100 text-gray-700">
                <SendIcon class="w-4.5 h-4.5" />
              </div>
              <div>
                <h3 class="text-sm font-semibold text-gray-900">Kirim Notifikasi Invoice</h3>
                <p class="text-xs text-gray-500">{{ invoiceNumber }} (Email & WhatsApp)</p>
              </div>
            </div>
            <button
              @click="close"
              class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition cursor-pointer"
            >
              <XIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- Body -->
          <div class="px-6 py-5 space-y-4">
            <!-- Email Input -->
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600">
                  Alamat Email Tujuan
                </label>
                <span v-if="defaultEmail && email === defaultEmail" class="text-[10px] font-medium text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">
                  Otomatis dari Draft
                </span>
              </div>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                  <MailIcon class="w-4 h-4" />
                </div>
                <input
                  v-model="email"
                  type="email"
                  placeholder="contoh@dealer.com"
                  :disabled="loading"
                  class="w-full h-10 pl-9 pr-3 rounded-lg border border-gray-200 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 transition disabled:opacity-50 disabled:bg-gray-50"
                />
              </div>
            </div>

            <!-- WhatsApp Input -->
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600">
                  Nomor WhatsApp Tujuan
                </label>
                <span v-if="defaultWhatsapp && whatsapp === defaultWhatsapp" class="text-[10px] font-medium text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">
                  Otomatis dari Draft
                </span>
              </div>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-emerald-600 font-semibold text-xs">
                  WA
                </div>
                <input
                  v-model="whatsapp"
                  type="text"
                  placeholder="081234567890 / 6281234567890"
                  :disabled="loading"
                  class="w-full h-10 pl-9 pr-3 rounded-lg border border-gray-200 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 transition disabled:opacity-50 disabled:bg-gray-50"
                />
              </div>
            </div>

            <!-- Error -->
            <Alert v-if="error" variant="destructive" class="mt-2 py-2">
              <AlertCircleIcon class="h-4 w-4" />
              <AlertDescription>{{ error }}</AlertDescription>
            </Alert>

            <!-- Success -->
            <Alert v-if="success" class="mt-3">
              <CheckCircleIcon class="h-4 w-4 text-emerald-600" />
              <AlertDescription class="text-emerald-700 font-medium">{{ success }}</AlertDescription>
            </Alert>

            <!-- Info hint -->
            <p v-if="!success" class="mt-2 text-xs text-gray-400 leading-relaxed">
              Notifikasi akan dikirimkan ke Email dan WhatsApp sekaligus. PDF invoice terlampir pada email dan link unduh PDF disertakan pada pesan WhatsApp.
            </p>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-2.5 px-6 py-4 border-t border-gray-100 bg-gray-50/50">
            <button
              @click="close"
              :disabled="loading"
              class="h-9 px-4 rounded-lg border border-gray-200 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition disabled:opacity-50 cursor-pointer"
            >
              {{ success ? 'Tutup' : 'Batal' }}
            </button>
            <button
              v-if="!success"
              @click="send"
              :disabled="loading || (!email && !whatsapp)"
              class="h-9 px-5 rounded-lg bg-[#1D70F5] text-white text-sm font-medium hover:bg-blue-600 transition disabled:opacity-40 disabled:cursor-not-allowed inline-flex items-center gap-2 shadow-sm cursor-pointer"
            >
              <span v-if="loading" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
              <SendIcon v-else class="w-4 h-4" />
              {{ loading ? 'Mengirim...' : 'Kirim Notifikasi' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { MailIcon, XIcon, SendIcon, AlertCircle as AlertCircleIcon, CheckCircle as CheckCircleIcon } from '@lucide/vue';
import { Alert, AlertDescription } from '@/components/ui/alert';

const props = defineProps({
  modelValue: Boolean,
  invoiceId: [String, Number],
  invoiceNumber: String,
  defaultEmail: String,
  defaultWhatsapp: String,
});

const emit = defineEmits(['update:modelValue', 'sent']);

const email = ref('');
const whatsapp = ref('');
const loading = ref(false);
const error = ref('');
const success = ref('');

// Reset state and prefill when modal opens
watch(() => props.modelValue, (val) => {
  if (val) {
    email.value = props.defaultEmail || '';
    whatsapp.value = props.defaultWhatsapp || '';
    error.value = '';
    success.value = '';
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

  try {
    const res = await axios.post(`/api/invoices/${props.invoiceId}/send-email`, {
      email: email.value || null,
      whatsapp: whatsapp.value || null,
    });

    success.value = res.data.message;
    emit('sent', {
      invoiceId: props.invoiceId,
      email: email.value,
      whatsapp: whatsapp.value,
    });
  } catch (err) {
    if (err.response?.data?.errors?.email) {
      error.value = err.response.data.errors.email[0];
    } else if (err.response?.data?.errors?.whatsapp) {
      error.value = err.response.data.errors.whatsapp[0];
    } else if (err.response?.data?.message) {
      error.value = err.response.data.message;
    } else {
      error.value = 'Gagal mengirim notifikasi invoice. Silakan coba lagi.';
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
