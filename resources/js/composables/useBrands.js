import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

// Brand data lama, link lama, dan webhook tanpa brand (harus sama dengan Brand::DEFAULT_SLUG di backend)
export const DEFAULT_BRAND = 'realme';

// Slug brand di URL, mis. /form-program/realme/jabar
export const BRAND_ROUTE_PATTERN = '[a-z0-9-]+';

// Halaman yang datanya terpisah per brand: /<halaman>/<brand>[/<area>]
export const BRAND_PAGES = ['drafts', 'invoices', 'form-program', 'data-program', 'riwayat-program', 'programs'];

const brands = ref([]);
let brandsRequest = null;

/**
 * Ambil daftar brand dari Master Brand (sekali saja; force = ambil ulang setelah brand diubah).
 */
export function loadBrands(force = false) {
  if (!brandsRequest || force) {
    brandsRequest = axios.get('/api/brands')
      .then((res) => {
        brands.value = res.data.data || [];
        return brands.value;
      })
      .catch(() => {
        brandsRequest = null;
        return brands.value;
      });
  }

  return brandsRequest;
}

/**
 * Nama tampilan sebuah slug brand, mis. "realme" → "Realme".
 */
export function brandName(slug) {
  const value = slug || DEFAULT_BRAND;
  return brands.value.find((b) => b.slug === value)?.name || value.charAt(0).toUpperCase() + value.slice(1);
}

/**
 * Brand aktif berdasarkan parameter :brand di URL (default Realme), plus daftar brand dari Master Brand.
 */
export function useBrands() {
  const route = useRoute();
  const activeBrands = computed(() => brands.value.filter((b) => b.is_active));
  const brand = computed(() => {
    const slug = route.params.brand || DEFAULT_BRAND;
    return { slug, name: brandName(slug) };
  });

  return { brands, activeBrands, brand, loadBrands };
}
