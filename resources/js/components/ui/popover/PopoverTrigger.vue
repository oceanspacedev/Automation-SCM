<script setup>
import { PopoverTrigger } from 'reka-ui'

defineProps({
  asChild: {
    type: Boolean,
    default: false,
  },
})
</script>

<template>
  <PopoverTrigger v-bind="$attrs" :as-child="asChild">
    <slot />
  </PopoverTrigger>
</template>
