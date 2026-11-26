import { computed } from 'vue';

const params = new URLSearchParams(window.location.search);
const isPublic = /^\/(form(?:\/|-)|form-submission)/.test(window.location.pathname);
export const companySlug = params.get('company') || (isPublic ? 'top' : sessionStorage.getItem('scm-company') || 'top');
if (!isPublic) sessionStorage.setItem('scm-company', companySlug);

export function companyUrl(path) {
  const url = new URL(path, window.location.origin);
  url.searchParams.set('company', companySlug);
  return url.pathname + url.search + url.hash;
}

export function switchCompany(slug) {
  sessionStorage.setItem('scm-company', slug);
  window.location.assign('/dashboard?company=' + encodeURIComponent(slug));
}

export function useCompany() {
  return {
    companySlug,
    companyName: computed(() => companySlug === 'msi' ? 'PT MEDIA SELULAR INDONESIA' : 'CV TOP SELULAR'),
    switchCompany,
  };
}
