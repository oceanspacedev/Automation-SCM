<script setup>
import { PopoverRoot } from 'reka-ui'

const props = defineProps({
  open: {
    type: Boolean,
    default: undefined,
  },
  defaultOpen: {
    type: Boolean,
    default: false,
  },
  modal: {
    type: Boolean,
    default: false,
  },
})

const emits = defineEmits(['update:open'])
</script>

<template>
  <PopoverRoot v-bind="props" @update:open="emits('update:open', $event)">
    <slot />
  </PopoverRoot>
</template>
