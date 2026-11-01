import { onMounted, onUnmounted } from 'vue';

// Seberapa sering daftar dimuat ulang otomatis selama halamannya terbuka
export const AUTO_REFRESH_SECONDS = 15;

/**
 * Muat ulang data halaman otomatis tanpa reload: tiap AUTO_REFRESH_SECONDS selama tab terlihat, dan langsung saat
 * tab dibuka lagi. Dilewati selama `isPaused()` true (sedang memuat, ada modal terbuka, sedang mengedit, dsb.)
 * supaya tidak mengganggu yang sedang dikerjakan. `refresh` memuat ulang diam-diam: tanpa loading & pesan error.
 *
 * @param {() => Promise<unknown>} refresh
 * @param {{ isPaused?: () => boolean, seconds?: number }} [options]
 */
export function useAutoRefresh(refresh, { isPaused = () => false, seconds = AUTO_REFRESH_SECONDS } = {}) {
  let timer = null;
  let running = false;
  let lastRunAt = Date.now();

  const run = async () => {
    if (running || document.visibilityState !== 'visible' || isPaused()) return;

    running = true;
    try {
      await refresh();
    } catch (err) {
      console.error('Gagal memuat ulang data otomatis:', err);
    } finally {
      running = false;
      lastRunAt = Date.now();
    }
  };

  // Kembali ke tab ini: langsung muat ulang bila sudah lewat setengah jeda sejak terakhir
  const onVisibilityChange = () => {
    if (document.visibilityState === 'visible' && Date.now() - lastRunAt > (seconds * 1000) / 2) {
      run();
    }
  };

  onMounted(() => {
    lastRunAt = Date.now();
    timer = setInterval(run, seconds * 1000);
    document.addEventListener('visibilitychange', onVisibilityChange);
  });

  onUnmounted(() => {
    clearInterval(timer);
    document.removeEventListener('visibilitychange', onVisibilityChange);
  });
}

/**
 * Penanda permintaan data terbaru. Panggil di awal tiap muat data; fungsi yang dikembalikan bernilai false bila
 * sudah ada permintaan yang lebih baru (mis. ganti halaman/filter saat muat ulang otomatis berjalan), supaya
 * jawaban lama tidak menimpa data yang lebih baru.
 *
 * @returns {() => () => boolean}
 */
export function createRequestTracker() {
  let latest = 0;

  return () => {
    const id = ++latest;

    return () => id === latest;
  };
}
