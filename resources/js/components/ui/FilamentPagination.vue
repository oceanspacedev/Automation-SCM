<template>
  <div class="px-4 py-3 border-t border-gray-200 bg-white flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-600 font-sans">
    <!-- Results summary (Filament format: Showing X to Y of Z results) -->
    <div class="font-normal text-gray-600">
      <span v-if="total > 0">
        Showing <span class="font-semibold text-gray-900">{{ from }}</span> to
        <span class="font-semibold text-gray-900">{{ to }}</span> of
        <span class="font-semibold text-gray-900">{{ total }}</span> results
      </span>
      <span v-else>
        No results found
      </span>
    </div>

    <!-- Controls: Per Page + Navigation -->
    <div class="flex items-center gap-4">
      <!-- Per Page Selector -->
      <div class="flex items-center gap-2">
        <label :for="selectId" class="text-xs text-gray-600 font-normal">Per page</label>
        <div class="relative">
          <select
            :id="selectId"
            :value="perPage"
            @change="onPerPageSelect"
            class="h-8 pl-2.5 pr-7 py-1 text-xs font-medium text-gray-900 bg-white border border-gray-300 rounded-lg shadow-2xs focus:outline-none focus:ring-1 focus:ring-gray-950 focus:border-gray-950 appearance-none cursor-pointer"
          >
            <option v-for="opt in perPageOptions" :key="opt" :value="opt">
              {{ opt }}
            </option>
          </select>
          <ChevronDownIcon class="w-3.5 h-3.5 text-gray-400 absolute right-2 top-2.5 pointer-events-none" />
        </div>
      </div>

      <!-- Navigation buttons -->
      <div v-if="lastPage > 1" class="flex items-center gap-1.5">
        <button
          type="button"
          @click="changePage(currentPage - 1)"
          :disabled="currentPage <= 1"
          class="h-8 px-2.5 inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed shadow-2xs transition cursor-pointer"
          title="Previous Page"
        >
          <ChevronLeftIcon class="w-3.5 h-3.5" />
          <span class="hidden sm:inline">Previous</span>
        </button>

        <!-- Current / Total indicator -->
        <span class="px-2 text-xs font-semibold text-gray-900">
          {{ currentPage }} / {{ lastPage }}
        </span>

        <button
          type="button"
          @click="changePage(currentPage + 1)"
          :disabled="currentPage >= lastPage"
          class="h-8 px-2.5 inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed shadow-2xs transition cursor-pointer"
          title="Next Page"
        >
          <span class="hidden sm:inline">Next</span>
          <ChevronRightIcon class="w-3.5 h-3.5" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { ChevronDownIcon, ChevronLeftIcon, ChevronRightIcon } from 'lucide-vue-next';

const props = defineProps({
  total: { type: Number, default: 0 },
  currentPage: { type: Number, default: 1 },
  lastPage: { type: Number, default: 1 },
  perPage: { type: Number, default: 10 },
  perPageOptions: {
    type: Array,
    default: () => [10, 25, 50, 100],
  },
});

const emit = defineEmits(['update:currentPage', 'update:perPage', 'pageChange', 'perPageChange']);

const selectId = `per-page-${Math.random().toString(36).slice(2, 7)}`;

const from = computed(() => {
  if (props.total === 0) return 0;
  return (props.currentPage - 1) * props.perPage + 1;
});

const to = computed(() => {
  return Math.min(props.currentPage * props.perPage, props.total);
});

const changePage = (page) => {
  if (page < 1 || page > props.lastPage || page === props.currentPage) return;
  emit('update:currentPage', page);
  emit('pageChange', page);
};

const onPerPageSelect = (e) => {
  const newPerPage = Number(e.target.value);
  emit('update:perPage', newPerPage);
  emit('perPageChange', newPerPage);
  emit('pageChange', 1);
};
</script>
