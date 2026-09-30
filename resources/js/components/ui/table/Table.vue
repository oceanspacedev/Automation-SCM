<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({
  class: { type: null, required: false },
  containerClass: { type: null, required: false },
});
</script>

<template>
  <div :class="cn('relative w-full overflow-auto', props.containerClass)">
    <table :class="cn('w-full caption-bottom text-xs border-separate border-spacing-0', props.class)">
      <slot />
    </table>
  </div>
</template>
