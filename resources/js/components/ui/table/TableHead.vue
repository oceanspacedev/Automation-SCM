<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({
  class: { type: null, required: false },
});
</script>

<template>
  <th :class="cn('h-10 px-3 text-left align-middle font-medium text-gray-500 text-xs [&:has([role=checkbox])]:pr-0', props.class)">
    <slot />
  </th>
</template>
