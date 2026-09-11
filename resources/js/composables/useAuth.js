import { reactive, computed } from 'vue';
import axios from 'axios';

const state = reactive({
  user: null,
  loading: false,
  initialized: false,
});

export function useAuth() {
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
      return res.data;
    } finally {
      state.loading = false;
    }
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
    isAuthenticated: computed(() => !!state.user),
    isLoading: computed(() => state.loading),
    isInitialized: computed(() => state.initialized),
    checkAuth,
    login,
    logout,
  };
}
