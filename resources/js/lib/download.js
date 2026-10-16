import { AREA_ROUTE_PATTERN } from '@/composables/useArea';
import { BRAND_PAGES, BRAND_ROUTE_PATTERN } from '@/composables/useBrands';

/**
 * Download an export (e.g. Excel) from an API endpoint with the page's filters.
 * Like API requests, it goes for the brand & area (Jabar/Jateng) in the page URL; empty and "all" filters are left out.
 */
export function downloadExport(path, filters = {}) {
  // Area dari URL halaman (mis. /dealers/jateng); tidak memakai router agar tidak terjadi import berputar
  const area = window.location.pathname.match(new RegExp(`/(${AREA_ROUTE_PATTERN})(?:/|$)`))?.[1];
  // Brand dari URL halaman per brand (mis. /programs/realme, /data-program/realme/jabar)
  const brand = window.location.pathname.match(new RegExp(`^/(?:${BRAND_PAGES.join('|')})/(${BRAND_ROUTE_PATTERN})(?:/|$)`))?.[1];
  const params = { area, brand, ...filters };
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
