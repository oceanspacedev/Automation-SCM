<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({
  class: { type: null, required: false },
});
</script>

<template>
  <tr :class="cn('border-b border-gray-200 transition-colors hover:bg-gray-50/50 data-[state=selected]:bg-gray-100', props.class)">
    <slot />
  </tr>
</template>
