import { ref } from 'vue';

const isDark = ref(false);

function applyTheme(dark) {
  isDark.value = dark;
  if (typeof document !== 'undefined') {
    if (dark) {
      document.documentElement.classList.add('dark');
      localStorage.setItem('scm_theme', 'dark');
    } else {
      document.documentElement.classList.remove('dark');
      localStorage.setItem('scm_theme', 'light');
    }
  }
}

function initTheme() {
  if (typeof window === 'undefined') return;
  const saved = localStorage.getItem('scm_theme');
  if (saved) {
    applyTheme(saved === 'dark');
  } else {
    // Default to light unless user system explicitly prefers dark
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    applyTheme(prefersDark);
  }
}

export function useTheme() {
  const toggleDarkMode = () => {
    applyTheme(!isDark.value);
  };

  return {
    isDark,
    toggleDarkMode,
    applyTheme,
    initTheme,
  };
}
