import { reactive, computed } from 'vue';
import axios from 'axios';
import { useRoute } from 'vue-router';
import { canPerform, canEdit as canEditData } from '@/lib/access';

const state = reactive({
  user: null,
  loading: false,
  initialized: false,
});

export function useAuth() {
  const route = useRoute();
  const module = computed(() => ['drafts','invoices','data_program','form_program','master_program','master_dealer','master_customer','master_user'].includes(route?.meta?.permission) ? route.meta.permission : null);
  const checkAuth = async () => {
    state.loading = true;
    try {
      const res = await axios.get('/api/user');
      state.user = res.data.user || null;
    } catch {
      state.user = null;
    } finally {
      state.loading = false;
      state.initialized = true;
    }
    return state.user;
  };

  const login = async (credentials) => {
    state.loading = true;
    try {
      const res = await axios.post('/api/login', credentials);
      state.user = res.data.user;
      state.initialized = true;
      return res.data;
    } finally {
      state.loading = false;
    }
  };

  const verifyWaOtp = async (payload) => {
    state.loading = true;
    try {
      const res = await axios.post('/api/auth/wa-otp/verify', payload);
      if (res.data?.success && res.data?.user) {
        state.user = res.data.user;
        state.initialized = true;
      }
      return res.data;
    } finally {
      state.loading = false;
    }
  };

  const setUser = (user) => {
    state.user = user;
    state.initialized = true;
  };

  const logout = async () => {
    state.loading = true;
    try {
      await axios.post('/api/logout');
    } finally {
      state.user = null;
      state.loading = false;
    }
  };

  return {
    state,
    user: computed(() => state.user),
    // Izin transaksi mengikuti modul halaman yang sedang dibuka.
    canCreate: computed(() => module.value ? canPerform(state.user, module.value, 'create') : canEditData(state.user)),
    canEdit: computed(() => module.value ? canPerform(state.user, module.value, 'edit') : canEditData(state.user)),
    canDelete: computed(() => module.value ? canPerform(state.user, module.value, 'delete') : canEditData(state.user)),
    isAuthenticated: computed(() => !!state.user),
    isLoading: computed(() => state.loading),
    isInitialized: computed(() => state.initialized),
    checkAuth,
    login,
    verifyWaOtp,
    setUser,
    logout,
  };
}
