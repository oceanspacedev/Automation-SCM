<script setup>
import { cn } from '@/lib/utils';
import TableRow from './TableRow.vue';
import TableCell from './TableCell.vue';

const props = defineProps({
  class: { type: null, required: false },
  colspan: { type: Number, default: 1 },
});
</script>

<template>
  <TableRow>
    <TableCell
      :class="cn('p-6 whitespace-nowrap align-middle text-xs text-gray-500 text-center', props.class)"
      :colspan="props.colspan"
    >
      <div class="flex items-center justify-center py-4">
        <slot />
      </div>
    </TableCell>
  </TableRow>
</template>
