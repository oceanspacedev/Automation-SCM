import { computed } from 'vue';
import { useRoute } from 'vue-router';

// Wilayah yang memiliki Form Program & Data Program sendiri (harus sama dengan App\Area di backend)
export const AREAS = [
  { value: 'jabar', label: 'Jawa Barat', short: 'JABAR' },
  { value: 'jateng', label: 'Jawa Tengah', short: 'JATENG' },
];

export const DEFAULT_AREA = 'jabar';

export const AREA_ROUTE_PATTERN = AREAS.map(a => a.value).join('|');

export function findArea(value) {
  return AREAS.find(a => a.value === value) || AREAS.find(a => a.value === DEFAULT_AREA);
}

/**
 * Area aktif berdasarkan parameter :area di URL (default Jawa Barat).
 */
export function useArea() {
  const route = useRoute();
  const area = computed(() => findArea(route.params.area));

  return { area, areas: AREAS };
}
