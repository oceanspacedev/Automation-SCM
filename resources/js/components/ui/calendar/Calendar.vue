<script setup>
import { ref, computed } from 'vue'
import {
  CalendarRoot,
  CalendarHeader,
  CalendarPrev,
  CalendarNext,
  CalendarHeading,
  CalendarGrid,
  CalendarGridHead,
  CalendarGridRow,
  CalendarHeadCell,
  CalendarGridBody,
  CalendarCell,
  CalendarCellTrigger,
} from 'reka-ui'
import { ChevronLeft, ChevronRight } from '@lucide/vue'
import { cn } from '@/lib/utils'

const props = defineProps({
  modelValue: {
    type: Object,
    default: undefined,
  },
  defaultValue: {
    type: Object,
    default: undefined,
  },
  defaultPlaceholder: {
    type: Object,
    default: undefined,
  },
  initialFocus: {
    type: Boolean,
    default: false,
  },
  fixedWeeks: {
    type: Boolean,
    default: false,
  },
  numberOfMonths: {
    type: Number,
    default: 1,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  locale: {
    type: String,
    default: 'en',
  },
  layout: {
    type: String,
    default: 'month',
  },
  class: {
    type: String,
    default: '',
  },
})

const emits = defineEmits(['update:modelValue'])
</script>

<template>
  <CalendarRoot
    v-slot="{ grid, weekDays }"
    :model-value="modelValue"
    :default-value="defaultValue"
    :default-placeholder="defaultPlaceholder"
    :initial-focus="initialFocus"
    :fixed-weeks="fixedWeeks"
    :number-of-months="numberOfMonths"
    :disabled="disabled"
    :locale="locale"
    :class="cn('p-3', props.class)"
    @update:model-value="emits('update:modelValue', $event)"
  >
    <CalendarHeader class="flex items-center justify-between pt-1 pb-2">
      <CalendarPrev
        class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input hover:bg-accent hover:text-accent-foreground h-7 w-7 bg-transparent p-0 opacity-50 hover:opacity-100"
      >
        <ChevronLeft class="h-4 w-4" />
      </CalendarPrev>

      <CalendarHeading class="text-sm font-medium" />

      <CalendarNext
        class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input hover:bg-accent hover:text-accent-foreground h-7 w-7 bg-transparent p-0 opacity-50 hover:opacity-100"
      >
        <ChevronRight class="h-4 w-4" />
      </CalendarNext>
    </CalendarHeader>

    <div class="flex flex-col gap-y-4 mt-2 sm:flex-row sm:gap-x-4 sm:gap-y-0">
      <template v-for="month in grid" :key="month.value.toString()">
        <CalendarGrid class="w-full border-collapse space-y-1">
          <CalendarGridHead>
            <CalendarGridRow class="flex">
              <CalendarHeadCell
                v-for="day in weekDays"
                :key="day"
                class="rounded-md w-9 font-normal text-[0.8rem] text-muted-foreground"
              >
                {{ day }}
              </CalendarHeadCell>
            </CalendarGridRow>
          </CalendarGridHead>

          <CalendarGridBody>
            <CalendarGridRow
              v-for="(weekDates, index) in month.rows"
              :key="`week-${index}`"
              class="flex w-full mt-2"
            >
              <CalendarCell
                v-for="weekDate in weekDates"
                :key="weekDate.toString()"
                :date="weekDate"
                class="relative h-9 w-9 p-0 text-center text-sm focus-within:relative focus-within:z-20"
              >
                <CalendarCellTrigger
                  :day="weekDate"
                  :month="month.value"
                  class="inline-flex items-center justify-center rounded-md text-sm ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 hover:bg-accent hover:text-accent-foreground h-9 w-9 p-0 font-normal aria-selected:opacity-100 data-[selected]:bg-primary data-[selected]:text-primary-foreground data-[selected]:hover:bg-primary data-[selected]:hover:text-primary-foreground data-[selected]:focus:bg-primary data-[selected]:focus:text-primary-foreground data-[today]:bg-accent data-[today]:text-accent-foreground data-[outside-month]:text-muted-foreground data-[outside-month]:opacity-50 data-[outside-month]:aria-selected:bg-accent/50 data-[outside-month]:aria-selected:text-muted-foreground data-[outside-month]:aria-selected:opacity-30 data-[disabled]:text-muted-foreground data-[disabled]:opacity-50"
                />
              </CalendarCell>
            </CalendarGridRow>
          </CalendarGridBody>
        </CalendarGrid>
      </template>
    </div>
  </CalendarRoot>
</template>
