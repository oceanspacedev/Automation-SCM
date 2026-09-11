<script setup>
import { PopoverContent, PopoverPortal } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps({
  class: {
    type: String,
    default: '',
  },
  align: {
    type: String,
    default: 'center',
  },
  sideOffset: {
    type: Number,
    default: 4,
  },
})
</script>

<template>
  <PopoverPortal>
    <PopoverContent
      :align="align"
      :side-offset="sideOffset"
      :class="cn(
        'z-50 w-72 rounded-md border bg-popover p-4 text-popover-foreground shadow-md outline-none data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2',
        props.class,
      )"
      v-bind="$attrs"
    >
      <slot />
    </PopoverContent>
  </PopoverPortal>
</template>
