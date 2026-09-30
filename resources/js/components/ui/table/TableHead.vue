<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({
  class: { type: null, required: false },
});
</script>

<template>
  <th :class="cn('h-9 px-4 text-left align-middle font-semibold text-gray-950 text-xs tracking-normal border-b border-gray-200 bg-white sticky top-0 z-20 shadow-[0_1px_0_0_#e5e7eb] [&:has([role=checkbox])]:pr-0', props.class)">
    <slot />
  </th>
</template>
