import { createApp } from 'vue';
import axios from 'axios';
import App from './App.vue';
import { companySlug } from './composables/useCompany';
import router from './router';

// Kirim area (Jabar/Jateng) dan brand (Realme, ...) aktif dari URL halaman ke setiap request API
axios.interceptors.request.use((config) => {
  // Halaman tanpa area di URL bisa menentukan area bawaan lewat meta.defaultArea
  config.headers['X-Company'] = companySlug;
  if (/\/api\/(login|register|logout|auth\/|user$)/.test(config.url || '')) {
    config.headers['X-Company'] = 'top';
    return config;
  }
  const route = router.currentRoute.value;
  const area = route?.params?.area || route?.meta?.defaultArea;
  if (area && !config.headers['X-Area']) {
    config.headers['X-Area'] = area;
  }

  const brand = route?.params?.brand;
  if (brand && !config.headers['X-Brand']) {
    config.headers['X-Brand'] = brand;
  }

  return config;
});

const app = createApp(App);

app.use(router);
app.mount('#app');
