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
              <div class="flex items-center justify-center w-9 h-9 rounded-full bg-gray-100">
                <MailIcon class="w-4.5 h-4.5 text-gray-700" />
              </div>
              <div>
                <h3 class="text-sm font-semibold text-gray-900">Kirim Invoice via Email</h3>
                <p class="text-xs text-gray-500">{{ invoiceNumber }}</p>
              </div>
            </div>
            <button
              @click="close"
              class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition"
            >
              <XIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- Body -->
          <div class="px-6 py-5">
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-sm font-medium text-gray-700">
                Alamat Email Tujuan
              </label>
              <span v-if="defaultEmail" class="text-[11px] font-medium text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">
                Otomatis dari Draft
              </span>
            </div>
            <input
              v-model="email"
              type="email"
              placeholder="contoh@email.com"
              :disabled="loading"
              @keyup.enter="send"
              class="w-full h-10 px-3 rounded-lg border border-gray-200 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 transition disabled:opacity-50 disabled:bg-gray-50"
            />

            <!-- Error -->
            <Alert v-if="error" variant="destructive" class="mt-2 py-2">
              <AlertCircleIcon class="h-4 w-4" />
              <AlertDescription>{{ error }}</AlertDescription>
            </Alert>

            <!-- Success -->
            <Alert v-if="success" class="mt-3">
              <CheckCircleIcon class="h-4 w-4" />
              <AlertDescription>{{ success }}</AlertDescription>
            </Alert>

            <!-- Info hint -->
            <p v-if="!success" class="mt-2.5 text-xs text-gray-400">
              Invoice PDF akan dilampirkan secara otomatis di email.
            </p>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-2.5 px-6 py-4 border-t border-gray-100 bg-gray-50/50">
            <button
              @click="close"
              :disabled="loading"
              class="h-9 px-4 rounded-lg border border-gray-200 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition disabled:opacity-50"
            >
              Batal
            </button>
            <button
              @click="send"
              :disabled="loading || !email || !!success"
              class="h-9 px-5 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 transition disabled:opacity-40 disabled:cursor-not-allowed inline-flex items-center gap-2 shadow-sm"
            >
              <span v-if="loading" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
              <SendIcon v-else class="w-4 h-4" />
              {{ loading ? 'Mengirim...' : 'Kirim Email' }}
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
});

const emit = defineEmits(['update:modelValue', 'sent']);

const email = ref('');
const loading = ref(false);
const error = ref('');
const success = ref('');

// Reset state and prefill when modal opens
watch(() => props.modelValue, (val) => {
  if (val) {
    email.value = props.defaultEmail || '';
    error.value = '';
    success.value = '';
  }
});

watch(() => props.defaultEmail, (val) => {
  if (props.modelValue && val) {
    email.value = val;
  }
});

const close = () => {
  if (loading.value) return;
  emit('update:modelValue', false);
};

const send = async () => {
  if (!email.value || loading.value || success.value) return;

  error.value = '';
  loading.value = true;

  try {
    const res = await axios.post(`/api/invoices/${props.invoiceId}/send-email`, {
      email: email.value,
    });

    success.value = res.data.message;
    emit('sent', { invoiceId: props.invoiceId, email: email.value });
  } catch (err) {
    if (err.response?.data?.errors?.email) {
      error.value = err.response.data.errors.email[0];
    } else if (err.response?.data?.message) {
      error.value = err.response.data.message;
    } else {
      error.value = 'Gagal mengirim email. Silakan coba lagi.';
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
