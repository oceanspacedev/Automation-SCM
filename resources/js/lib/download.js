import router from '@/router';

/**
 * Download an export (e.g. Excel) from an API endpoint with the page's filters.
 * Like API requests, it goes for the area (Jabar/Jateng) in the page URL; empty and "all" filters are left out.
 */
export function downloadExport(path, filters = {}) {
  const route = router.currentRoute.value;
  const params = { area: route?.params?.area || route?.meta?.defaultArea, ...filters };
  const query = new URLSearchParams(
    Object.entries(params).filter(([, value]) => value !== undefined && value !== null && value !== '' && value !== 'all')
  ).toString();

  const link = document.createElement('a');
  link.href = query ? `${path}?${query}` : path;
  link.setAttribute('download', '');
  document.body.appendChild(link);
  link.click();
  link.remove();
}
