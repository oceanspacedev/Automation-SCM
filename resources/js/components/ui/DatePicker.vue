<script setup>
import { computed, ref } from 'vue'
import { CalendarDate, parseDate, today, getLocalTimeZone } from '@internationalized/date'
import { CalendarIcon, XIcon } from '@lucide/vue'
import { Calendar } from '@/components/ui/calendar'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import { cn } from '@/lib/utils'

const props = defineProps({
  modelValue: {
    type: [String, Object, null],
    default: '',
  },
  placeholder: {
    type: String,
    default: 'Pilih tanggal',
  },
  format: {
    type: String,
    default: 'DD/MM/YYYY', // 'DD/MM/YYYY' or 'YYYY-MM-DD'
  },
  clearable: {
    type: Boolean,
    default: true,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  class: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:modelValue', 'change'])

const isOpen = ref(false)
const defaultPlaceholder = today(getLocalTimeZone())

/**
 * Parse any date string format (DD/MM/YYYY, YYYY-MM-DD, ISO) to CalendarDate
 */
const parseToCalendarDate = (val) => {
  if (!val) return undefined
  if (typeof val === 'object' && val.year && val.month && val.day) return val

  const s = String(val).trim()
  if (!s) return undefined

  // Format: YYYY-MM-DD
  if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
    try {
      return parseDate(s)
    } catch (e) {
      // ignore
    }
  }

  // Format: DD/MM/YYYY or DD-MM-YYYY
  const dmy = s.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/)
  if (dmy) {
    try {
      return new CalendarDate(parseInt(dmy[3], 10), parseInt(dmy[2], 10), parseInt(dmy[1], 10))
    } catch (e) {
      // ignore
    }
  }

  // Fallback: standard Date parsing (e.g. ISO string)
  try {
    const d = new Date(s)
    if (!isNaN(d.getTime())) {
      return new CalendarDate(d.getFullYear(), d.getMonth() + 1, d.getDate())
    }
  } catch (e) {
    // ignore
  }

  return undefined
}

const calendarValue = computed({
  get() {
    return parseToCalendarDate(props.modelValue)
  },
  set(val) {
    onDateSelect(val)
  },
})

/**
 * Format a CalendarDate to the target output string
 */
const formatOutput = (cd) => {
  if (!cd) return ''
  if (props.format === 'YYYY-MM-DD') {
    return cd.toString()
  }
  // Default 'DD/MM/YYYY'
  const d = String(cd.day).padStart(2, '0')
  const m = String(cd.month).padStart(2, '0')
  const y = cd.year
  return `${d}/${m}/${y}`
}

/**
 * Handle date selection from Calendar
 */
const onDateSelect = (cd) => {
  if (!cd) {
    emit('update:modelValue', '')
    emit('change', '')
    isOpen.value = false
    return
  }

  const formatted = formatOutput(cd)
  emit('update:modelValue', formatted)
  emit('change', formatted)
  isOpen.value = false
}

/**
 * Quick shortcut: Hari Ini (Today)
 */
const selectToday = () => {
  const cd = today(getLocalTimeZone())
  onDateSelect(cd)
}

/**
 * Quick shortcut: Kemarin (Yesterday)
 */
const selectYesterday = () => {
  const cd = today(getLocalTimeZone()).subtract({ days: 1 })
  onDateSelect(cd)
}

/**
 * Quick action: Bersihkan tanggal
 */
const clearDate = () => {
  emit('update:modelValue', '')
  emit('change', '')
  isOpen.value = false
}

/**
 * Display label in trigger button
 */
const displayLabel = computed(() => {
  if (!props.modelValue) return ''
  const cd = parseToCalendarDate(props.modelValue)
  if (cd) {
    return formatOutput(cd)
  }
  return String(props.modelValue)
})
</script>

<template>
  <Popover v-model:open="isOpen">
    <PopoverTrigger as-child>
      <button
        type="button"
        :disabled="disabled"
        :class="cn(
          'h-9 w-full px-3 rounded-md border border-gray-200 bg-white text-xs text-left font-normal flex items-center justify-between transition focus:outline-none focus:ring-1 focus:ring-black hover:border-gray-300 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer group',
          !modelValue && 'text-gray-400',
          props.class
        )"
      >
        <div class="flex items-center gap-2 truncate">
          <CalendarIcon class="w-3.5 h-3.5 text-gray-400 group-hover:text-gray-600 transition shrink-0" />
          <span :class="modelValue ? 'text-gray-900 font-medium' : 'text-gray-400'">
            {{ displayLabel || placeholder }}
          </span>
        </div>

        <div class="flex items-center gap-1 shrink-0 ml-1">
          <!-- Clear Button -->
          <span
            v-if="clearable && modelValue && !disabled"
            role="button"
            tabindex="0"
            @click.stop.prevent="clearDate"
            @keydown.enter.stop.prevent="clearDate"
            class="p-1 rounded text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition cursor-pointer"
            title="Hapus tanggal"
          >
            <XIcon class="w-3 h-3" />
          </span>
        </div>
      </button>
    </PopoverTrigger>

    <PopoverContent
      class="z-[80] w-auto p-0 bg-white rounded-xl shadow-2xl border border-gray-200 overflow-hidden"
      align="start"
      :side-offset="4"
    >
      <div class="p-1">
        <Calendar
          :model-value="calendarValue"
          :initial-focus="true"
          :default-placeholder="defaultPlaceholder"
          locale="id-ID"
          @update:model-value="onDateSelect"
        />
      </div>

      <!-- Quick Action Footer -->
      <div class="px-3 py-2 border-t border-gray-100 bg-gray-50/80 flex items-center justify-between gap-2 text-xs">
        <div class="flex items-center gap-1.5">
          <button
            type="button"
            @click="selectToday"
            class="px-2 py-1 rounded bg-white hover:bg-gray-100 text-gray-700 font-medium border border-gray-200 text-[11px] transition shadow-2xs cursor-pointer"
          >
            Hari Ini
          </button>
          <button
            type="button"
            @click="selectYesterday"
            class="px-2 py-1 rounded bg-white hover:bg-gray-100 text-gray-700 font-medium border border-gray-200 text-[11px] transition shadow-2xs cursor-pointer"
          >
            Kemarin
          </button>
        </div>

        <button
          v-if="modelValue"
          type="button"
          @click="clearDate"
          class="px-2 py-1 rounded text-rose-600 hover:bg-rose-50 text-[11px] font-medium transition cursor-pointer"
        >
          Bersihkan
        </button>
      </div>
    </PopoverContent>
  </Popover>
</template>
