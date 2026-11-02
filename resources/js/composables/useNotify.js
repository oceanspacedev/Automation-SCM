import { reactive } from 'vue';

// Notifikasi (toast) dan dialog konfirmasi bergaya aplikasi untuk semua halaman, pengganti alert() & confirm() browser.
// Ditampilkan oleh <NotificationHost> yang dipasang sekali di App.vue.
const state = reactive({
  toasts: [],
  dialog: null,
});

let nextId = 1;

export function dismissToast(id) {
  const index = state.toasts.findIndex((toast) => toast.id === id);
  if (index !== -1) state.toasts.splice(index, 1);
}

function push(type, message, { title = null, timeout = null } = {}) {
  const id = nextId++;
  state.toasts.push({ id, type, title, message });
  // Pesan gagal tampil lebih lama supaya sempat dibaca
  setTimeout(() => dismissToast(id), timeout ?? (type === 'error' ? 8000 : 5000));

  return id;
}

export const notify = {
  success: (message, options) => push('success', message, options),
  error: (message, options) => push('error', message, options),
  info: (message, options) => push('info', message, options),
};

/**
 * Tanyakan konfirmasi lewat dialog aplikasi; hasilnya true bila pengguna menyetujui.
 *
 * @param {{ title?: string, message: string, confirmText?: string, cancelText?: string }} options
 * @returns {Promise<boolean>}
 */
export function confirmAction({ title = 'Konfirmasi', message, confirmText = 'Ya, Lanjutkan', cancelText = 'Batal' }) {
  state.dialog?.resolve(false);

  return new Promise((resolve) => {
    state.dialog = { title, message, confirmText, cancelText, resolve };
  });
}

export function answerDialog(value) {
  const dialog = state.dialog;
  state.dialog = null;
  dialog?.resolve(value);
}

export function useNotify() {
  return { state, notify, confirmAction, answerDialog, dismissToast };
}
