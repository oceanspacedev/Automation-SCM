<template>
  <span
    :class="[
      'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-medium uppercase tracking-wide transition-colors',
      badgeClass
    ]"
  >
    <slot>{{ label }}</slot>
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  label: { type: String, default: '' },
  color: {
    type: String,
    default: 'gray', // 'success' | 'emerald' | 'info' | 'blue' | 'warning' | 'amber' | 'danger' | 'rose' | 'gray'
  },
});

const badgeClass = computed(() => {
  const c = props.color.toLowerCase();
  if (['success', 'emerald', 'green', 'delivered', 'sent', 'paid', 'matched', 'ready'].includes(c)) {
    return 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20';
  }
  if (['info', 'blue', 'assigned', 'invoiced', 'generating', 'processing'].includes(c)) {
    return 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-700/10';
  }
  if (['warning', 'amber', 'yellow', 'orange', 'in_delivery', 'pending', 'doc_incomplete', 'nominal_mismatch'].includes(c)) {
    return 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20';
  }
  if (['danger', 'red', 'rose', 'failed', 'error', 'cancelled', 'no_match'].includes(c)) {
    return 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/10';
  }
  return 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/10';
});
</script>
