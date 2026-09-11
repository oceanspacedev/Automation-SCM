<template>
  <Teleport to="body">
    <Transition name="modal-scale">
      <div
        v-if="modelValue"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @click.self="handleCancel"
      >
        <!-- Backdrop with soft blur -->
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm transition-opacity" @click="handleCancel" />

        <!-- Modal Dialog Box -->
        <div class="relative z-10 w-full max-w-md bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden transform transition-all">
          <div class="p-6">
            <!-- Icon & Header -->
            <div class="flex items-start gap-4">
              <div
                class="flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center"
                :class="iconBgClass"
              >
                <!-- Authentic Gmail-style Colored Mail Icon -->
                <svg v-if="icon === 'mail'" class="w-6 h-6" viewBox="0 0 24 24" fill="none">
                  <path d="M2 6C2 4.89543 2.89543 4 4 4H20C21.1046 4 22 4.89543 22 6V18C22 19.1046 21.1046 20 20 20H4C2.89543 20 2 19.1046 2 18V6Z" fill="#F8FAFC" stroke="#E2E8F0" stroke-width="1.5"/>
                  <path d="M2 6L12 13L22 6" stroke="#2563EB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M2 18L9 11.5" stroke="#94A3B8" stroke-width="1.5" stroke-linecap="round"/>
                  <path d="M22 18L15 11.5" stroke="#94A3B8" stroke-width="1.5" stroke-linecap="round"/>
                  <circle cx="12" cy="13" r="1.5" fill="#2563EB"/>
                </svg>

                <svg v-else class="w-6 h-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
              </div>

              <div class="flex-1 pt-0.5">
                <h3 class="text-base font-semibold text-gray-900 leading-snug">
                  {{ title }}
                </h3>
                <p class="mt-1.5 text-sm text-gray-600 leading-relaxed">
                  {{ message }}
                </p>
              </div>
            </div>

            <!-- Extra Details / Note (Optional Slot) -->
            <div v-if="$slots.default" class="mt-4 pt-4 border-t border-gray-100">
              <slot />
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-end gap-2.5 px-6 py-4 bg-gray-50/80 border-t border-gray-100">
            <button
              type="button"
              @click="handleCancel"
              :disabled="loading"
              class="h-9 px-4 rounded-lg border border-gray-200 bg-white text-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition cursor-pointer disabled:opacity-50"
            >
              {{ cancelText }}
            </button>
            <button
              type="button"
              @click="handleConfirm"
              :disabled="loading"
              class="h-9 px-4 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 transition cursor-pointer disabled:opacity-50 inline-flex items-center gap-2 shadow-sm"
            >
              <svg v-if="loading" class="animate-spin -ml-0.5 mr-1 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
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
import { computed } from 'vue';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: 'Konfirmasi Pengiriman' },
  message: { type: String, default: 'Apakah Anda yakin ingin melanjutkan tindakan ini?' },
  confirmText: { type: String, default: 'Ya, Kirim Sekarang' },
  cancelText: { type: String, default: 'Batal' },
  icon: { type: String, default: 'mail' },
  variant: { type: String, default: 'default' },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel']);

const iconBgClass = computed(() => {
  if (props.icon === 'mail') return 'bg-blue-50 border border-blue-100';
  return 'bg-amber-50 border border-amber-100';
});

const handleCancel = () => {
  if (props.loading) return;
  emit('update:modelValue', false);
  emit('cancel');
};

const handleConfirm = () => {
  if (props.loading) return;
  emit('confirm');
};
</script>

<style scoped>
.modal-scale-enter-active,
.modal-scale-leave-active {
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.modal-scale-enter-from,
.modal-scale-leave-to {
  opacity: 0;
  transform: scale(0.96);
}
</style>
