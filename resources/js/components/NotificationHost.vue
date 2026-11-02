<template>
  <!-- Notifikasi: kanan atas di layar lebar, melebar di atas pada layar kecil -->
  <Teleport to="body">
    <div class="fixed top-3 inset-x-3 sm:inset-x-auto sm:top-4 sm:right-4 z-[60] flex flex-col items-stretch sm:items-end gap-2 pointer-events-none">
      <TransitionGroup name="toast">
        <div
          v-for="toast in state.toasts"
          :key="toast.id"
          role="status"
          class="pointer-events-auto w-full sm:w-96 flex items-start gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-lg"
        >
          <span :class="['mt-0.5 shrink-0 w-5 h-5 rounded-full flex items-center justify-center', iconClass[toast.type]]">
            <CheckIcon v-if="toast.type === 'success'" class="w-3 h-3" stroke-width="3" />
            <XIcon v-else-if="toast.type === 'error'" class="w-3 h-3" stroke-width="3" />
            <InfoIcon v-else class="w-3 h-3" stroke-width="3" />
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-gray-900">{{ toast.title || defaultTitle[toast.type] }}</p>
            <p v-if="toast.message" class="mt-0.5 text-xs text-gray-600 leading-relaxed break-words">{{ toast.message }}</p>
          </div>
          <button type="button" @click="dismissToast(toast.id)" class="-mr-1 p-1 shrink-0 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-50 cursor-pointer" title="Tutup">
            <XIcon class="w-3.5 h-3.5" />
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>

  <ConfirmModal
    :model-value="!!state.dialog"
    :title="state.dialog?.title"
    :message="state.dialog?.message"
    :confirm-text="state.dialog?.confirmText"
    :cancel-text="state.dialog?.cancelText"
    icon="warning"
    layer-class="z-[70]"
    @confirm="answerDialog(true)"
    @cancel="answerDialog(false)"
  />
</template>

<script setup>
import { Check as CheckIcon, X as XIcon, Info as InfoIcon } from 'lucide-vue-next';
import ConfirmModal from '@/components/ConfirmModal.vue';
import { useNotify } from '@/composables/useNotify';

const { state, answerDialog, dismissToast } = useNotify();

const iconClass = {
  success: 'bg-emerald-100 text-emerald-700',
  error: 'bg-rose-100 text-rose-700',
  info: 'bg-blue-100 text-blue-700',
};

const defaultTitle = {
  success: 'Berhasil',
  error: 'Gagal',
  info: 'Info',
};
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
