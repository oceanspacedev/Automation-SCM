<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({
  class: { type: null, required: false },
});
</script>

<template>
  <td :class="cn('px-4 py-2.5 align-middle text-xs text-gray-800 border-b border-gray-200 [&:has([role=checkbox])]:pr-0', props.class)">
    <slot />
  </td>
</template>
