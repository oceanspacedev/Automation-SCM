import { createApp } from 'vue';
import axios from 'axios';
import App from './App.vue';
import router from './router';

// Kirim area aktif (Jabar/Jateng) dari URL halaman ke setiap request API
axios.interceptors.request.use((config) => {
  // Form publik lama (/form-realme, /form-submission) tetap milik Jawa Barat
  const route = router.currentRoute.value;
  const area = route?.params?.area || route?.meta?.defaultArea;
  if (area && !config.headers['X-Area']) {
    config.headers['X-Area'] = area;
  }

  return config;
});

const app = createApp(App);

app.use(router);
app.mount('#app');
