<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({
  class: { type: null, required: false },
});
</script>

<template>
  <td :class="cn('px-3 py-3 align-middle text-xs text-gray-900 [&:has([role=checkbox])]:pr-0', props.class)">
    <slot />
  </td>
</template>
