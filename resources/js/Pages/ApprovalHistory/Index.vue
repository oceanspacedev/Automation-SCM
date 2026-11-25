<template>
  <div class="space-y-4">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
      <span>Riwayat</span>
      <ChevronRightIcon class="w-4 h-4 text-gray-400" />
      <span class="text-gray-800 font-medium">Riwayat Approval</span>
    </div>

    <AreaTabs />

    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
      <div>
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-950">Riwayat Approval</h1>
        <p class="text-xs text-gray-500 mt-0.5">Alur potong klaim per baris Data Program.</p>
      </div>
      <button
        type="button"
        @click="fetchRows(pagination.current_page)"
        :disabled="loading"
        class="h-9 px-3 inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-2xs cursor-pointer disabled:opacity-50 whitespace-nowrap self-start sm:self-auto"
      >
        <RefreshCwIcon :class="['w-3.5 h-3.5 text-gray-500', loading && 'animate-spin text-gray-900']" />
        <span>{{ loading ? 'Memuat...' : 'Refresh' }}</span>
      </button>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-xs overflow-hidden">
      <div class="px-4 sm:px-5 py-3 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <p class="text-xs text-gray-500">{{ formatCount(pagination.total) }} baris</p>

        <!-- Filter tahap & pencarian; di layar kecil cari di atas, tahap di bawahnya -->
        <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-2">
          <div class="relative sm:w-72">
            <select
              v-model="stage"
              @change="fetchRows(1)"
              aria-label="Tahap approval"
              class="h-9 w-full rounded-lg border border-gray-300 bg-white px-3 pr-8 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-gray-900 cursor-pointer appearance-none shadow-2xs"
            >
              <option v-for="option in stageOptions" :key="option.value" :value="option.value">
                {{ option.label }} ({{ formatCount(counts[option.value]) }})
              </option>
            </select>
            <ChevronDownIcon class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
          </div>

          <div class="relative w-full sm:w-64">
            <SearchIcon class="w-3.5 h-3.5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
            <input
              v-model="search"
              @input="debounceFetch"
              type="text"
              placeholder="Cari dealer, kode BT, program"
              class="h-9 w-full pl-8 pr-3 text-xs text-gray-900 bg-white border border-gray-300 rounded-lg placeholder:text-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-950 focus:border-gray-950 transition shadow-2xs"
            />
          </div>
        </div>
      </div>

      <p v-if="errorMessage" class="px-5 py-3 text-xs text-rose-700 border-b border-gray-100">{{ errorMessage }}</p>
      <p v-if="loading && rows.length === 0" class="py-12 text-center text-xs text-gray-500">Memuat riwayat approval...</p>
      <p v-else-if="rows.length === 0" class="py-12 px-4 text-center text-xs text-gray-500">Belum ada baris di tahap ini.</p>

      <ul v-else class="divide-y divide-gray-100">
        <li v-for="row in rows" :key="row.id" class="px-4 sm:px-5 py-3.5">
          <!-- Layar lebar: toko di kiri, alur dengan lebar terbatas, lalu Detail tepat di sebelahnya -->
          <div class="flex flex-col lg:flex-row lg:items-center gap-3 lg:gap-6">
            <div class="flex items-start justify-between gap-3 lg:w-60 xl:w-72 lg:shrink-0">
              <!-- Teks tampil utuh: program boleh turun baris, nominal di sebelah status -->
              <div class="min-w-0">
                <p class="text-xs font-semibold text-gray-900 break-words">{{ row.dealer_name || '-' }}</p>
                <p class="mt-0.5 text-[11px] leading-4 text-gray-500 break-words">{{ rowProgram(row) }}</p>
                <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                  <span :class="['inline-flex items-center h-[18px] px-1.5 rounded-full text-[10px] font-medium ring-1 ring-inset', stageBadgeClass(row.stage)]">
                    {{ row.stage_label }}
                  </span>
                  <span v-if="row.settlement" :class="settlementBadgeClass">{{ row.settlement }}</span>
                  <span class="text-[11px] font-medium text-gray-700 tabular-nums whitespace-nowrap">{{ formatRupiah(row.net_pay) }}</span>
                </div>
              </div>
              <button type="button" @click="openDetail(row)" :class="[detailButtonClass, 'lg:hidden']">Detail</button>
            </div>

            <!-- Alur langkah (layar sedang & lebar): satu baris sejajar, tinggi seragam -->
            <ol
              class="hidden md:grid grid-rows-[auto_auto_auto] w-full max-w-4xl xl:max-w-5xl lg:flex-1"
              :style="{ gridTemplateColumns: `repeat(${row.steps.length}, minmax(0, 1fr))` }"
            >
              <li v-for="(step, index) in row.steps" :key="step.key" :title="stepTooltip(step)" class="relative row-span-3 grid grid-rows-subgrid justify-items-center content-start text-center px-0.5 min-w-0 cursor-default">
                <!-- Garis dari langkah sebelumnya: hijau penuh bila sudah lewat, hijau berjalan menuju langkah sekarang -->
                <span v-if="index > 0" :class="['absolute top-2 right-1/2 w-full h-0.5 rounded-full overflow-hidden', connectorClass(step.state)]">
                  <span v-if="step.state === 'current'" class="flow-runner"></span>
                </span>
                <StepDot :state="step.state" class="relative z-10 ring-4 ring-white" />
                <p :class="['mt-1.5 w-full line-clamp-2 text-balance text-[10px] font-medium leading-[14px]', stepLabelClass(step.state)]">{{ shortLabel(step) }}</p>
                <!-- Nama panjang turun paling banyak dua baris -->
                <p v-if="step.person" :class="['w-full line-clamp-2 break-words text-[10px] leading-[14px]', step.state === 'waiting' ? 'text-gray-400' : 'text-gray-600']">
                  {{ displayName(step.person) }}
                </p>
              </li>
            </ol>

            <!-- Alur langkah (layar kecil) -->
            <ol class="md:hidden">
              <li v-for="(step, index) in row.steps" :key="step.key" class="relative flex items-start gap-2 pb-2.5 last:pb-0">
                <span
                  v-if="index < row.steps.length - 1"
                  :class="['absolute left-[7px] top-4 bottom-0 w-0.5 rounded-full overflow-hidden', connectorClass(row.steps[index + 1].state)]"
                >
                  <span v-if="row.steps[index + 1].state === 'current'" class="flow-runner-y"></span>
                </span>
                <StepDot :state="step.state" small class="relative z-10 shrink-0" />
                <div class="min-w-0 flex-1 flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <p :class="['text-[11px] font-medium leading-4', stepLabelClass(step.state)]">{{ shortLabel(step) }}</p>
                    <p v-if="step.person" class="text-[10px] leading-[14px] text-gray-500 break-words">{{ displayName(step.person) }}</p>
                  </div>
                  <span class="text-[10px] leading-4 text-gray-400 tabular-nums whitespace-nowrap">{{ stepTime(step) }}</span>
                </div>
              </li>
            </ol>

            <!-- Tepat setelah alur, tidak didorong ke ujung kanan -->
            <button type="button" @click="openDetail(row)" :class="[detailButtonClass, 'hidden lg:inline-flex']">Detail</button>
          </div>
        </li>
      </ul>

      <FilamentPagination
        :total="pagination.total"
        :current-page="pagination.current_page"
        :last-page="pagination.last_page"
        :per-page="pagination.per_page"
        @page-change="fetchRows"
        @per-page-change="(p) => { pagination.per_page = p; fetchRows(1); }"
      />
    </div>

    <!-- Detail: langkah lengkap dan semua catatan -->
    <div v-if="detail" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs" @click.self="detail = null">
      <div class="bg-white dark:bg-slate-900 rounded-xl max-w-lg sm:max-w-xl w-full shadow-2xl border border-gray-200 dark:border-slate-800 max-h-[88vh] flex flex-col text-[11px] overflow-hidden">
        <!-- Header -->
        <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-800 flex items-start justify-between gap-3 bg-white dark:bg-slate-900 shrink-0">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
              <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ detail.dealer_name || '-' }}</h3>
              <span :class="['inline-flex items-center h-[20px] px-2 rounded-full text-[10px] font-medium ring-1 ring-inset', stageBadgeClass(detail.stage)]">
                {{ detail.stage_label }}
              </span>
              <span v-if="detail.settlement" :class="settlementBadgeClass">{{ detail.settlement }}</span>
            </div>
            <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">{{ rowMeta(detail) }}</p>
            <p v-if="detail.periode" class="text-xs text-gray-400 dark:text-slate-500">{{ detail.periode }}</p>
          </div>
          <button type="button" @click="detail = null" class="p-1.5 -m-1 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 dark:hover:bg-slate-800 dark:hover:text-slate-200 transition cursor-pointer" title="Tutup">
            <XIcon class="w-4 h-4" />
          </button>
        </div>

        <!-- Segmented Tab Header -->
        <div class="px-5 border-b border-gray-100 dark:border-slate-800 flex items-center gap-2 bg-gray-50/70 dark:bg-slate-800/40 shrink-0">
          <button
            type="button"
            @click="activeDetailTab = 'steps'"
            :class="[
              'py-2.5 px-3 text-xs font-medium border-b-2 transition -mb-px flex items-center gap-1.5 cursor-pointer',
              activeDetailTab === 'steps'
                ? 'border-gray-900 text-gray-900 dark:border-white dark:text-white font-semibold'
                : 'border-transparent text-gray-500 hover:text-gray-900 dark:text-slate-400 dark:hover:text-white'
            ]"
          >
            <span>Alur Approval</span>
            <span v-if="detail.steps?.length" class="px-1.5 py-0.5 rounded-full text-[10px] font-medium bg-gray-200/80 dark:bg-slate-700 text-gray-700 dark:text-slate-200">
              {{ completedStepsCount }}/{{ detail.steps.length }}
            </span>
          </button>

          <button
            type="button"
            @click="activeDetailTab = 'logs'"
            :class="[
              'py-2.5 px-3 text-xs font-medium border-b-2 transition -mb-px flex items-center gap-1.5 cursor-pointer',
              activeDetailTab === 'logs'
                ? 'border-gray-900 text-gray-900 dark:border-white dark:text-white font-semibold'
                : 'border-transparent text-gray-500 hover:text-gray-900 dark:text-slate-400 dark:hover:text-white'
            ]"
          >
            <span>Semua Catatan</span>
            <span v-if="detail.timeline?.length" class="px-1.5 py-0.5 rounded-full text-[10px] font-medium bg-gray-200/80 dark:bg-slate-700 text-gray-700 dark:text-slate-200">
              {{ detail.timeline.length }}
            </span>
          </button>
        </div>

        <!-- Body Scroll Content -->
        <div class="p-5 overflow-y-auto flex-1 space-y-4">
          <!-- TAB 1: ALUR APPROVAL (STEPPER) -->
          <div v-if="activeDetailTab === 'steps'" class="space-y-4">
            <ol>
              <li v-for="(step, index) in detail.steps" :key="step.key" class="relative flex gap-3 pb-3.5 last:pb-0">
                <span
                  v-if="index < detail.steps.length - 1"
                  :class="['absolute left-2.5 top-5 bottom-0 w-0.5 rounded-full overflow-hidden', connectorClass(detail.steps[index + 1].state)]"
                >
                  <span v-if="detail.steps[index + 1].state === 'current'" class="flow-runner-y"></span>
                </span>
                <StepDot :state="step.state" class="relative z-10 shrink-0 mt-0.5" />
                <div class="min-w-0 flex-1 pt-px">
                  <div class="flex items-center justify-between gap-2">
                    <span :class="['font-medium text-xs', stepLabelClass(step.state)]">
                      {{ step.label }}
                      <span v-if="step.key === 'scm_cut'" class="ml-1.5 px-1.5 py-0.5 rounded bg-gray-100 dark:bg-slate-800 text-[10px] font-normal text-gray-500 dark:text-slate-400">Cabang info</span>
                    </span>
                    <span :class="['text-[10px] px-2 py-0.5 rounded-full font-medium', stepBadgeClass(step.state)]">
                      {{ stepStateLabel(step) }}
                    </span>
                  </div>

                  <div class="flex flex-wrap items-center gap-x-2 text-[11px] text-gray-500 dark:text-slate-400 mt-1">
                    <span v-if="step.person" class="text-gray-800 dark:text-slate-200 font-medium">
                      <span v-if="step.role" class="text-gray-400 dark:text-slate-500 font-normal">{{ step.role }} · </span>{{ displayName(step.person) }}
                    </span>
                    <span v-if="(step.at || step.date) && step.person" class="text-gray-300 dark:text-slate-700">·</span>
                    <span v-if="step.at || step.date" class="tabular-nums">{{ stepTime(step, true) }}</span>
                  </div>

                  <p v-if="step.actor" class="text-gray-500 dark:text-slate-400 text-[11px] mt-0.5">
                    Oleh {{ step.actor }}<template v-if="step.recipient"> · Ke {{ step.recipient }}</template>
                  </p>

                  <p v-if="step.note && step.key !== 'ready' && !isRedundantWaNote(step.note)" class="text-gray-700 dark:text-slate-200 text-xs mt-1.5 p-2 bg-gray-50 dark:bg-slate-800/80 rounded-md border border-gray-200/70 dark:border-slate-700 break-words font-sans leading-relaxed">
                    {{ step.note }}
                  </p>
                </div>
              </li>
            </ol>

            <!-- Kirim (ulang) WA lanjutan (transfer SCM, info potong SCM, cek pajak, atau hasil cek pajak ke Tim AP) -->
            <div v-if="canEdit && (resendActions.length || waMessage)" class="pt-3 border-t border-gray-100 dark:border-slate-800 flex flex-wrap items-center gap-x-2.5 gap-y-1.5">
              <button
                v-for="action in resendActions"
                :key="action.endpoint"
                type="button"
                @click="resendWhatsApp(action)"
                :disabled="sendingWa"
                :class="[detailButtonClass, 'inline-flex disabled:opacity-50']"
              >
                {{ sendingWa ? 'Mengirim...' : action.label }}
              </button>
              <span v-if="waMessage" :class="['break-words text-xs', waMessage.error ? 'text-rose-700 dark:text-rose-400' : 'text-emerald-700 dark:text-emerald-400']">{{ waMessage.text }}</span>
            </div>

            <!-- Aksi Pengembalian Tahap / Rollback Approval -->
            <div v-if="canEdit && canRollback" class="pt-2 border-t border-gray-100 dark:border-slate-800 space-y-2">
              <div class="flex items-center justify-between">
                <button
                  type="button"
                  @click="showRollbackForm = !showRollbackForm"
                  class="inline-flex items-center gap-1.5 text-xs font-medium text-amber-700 dark:text-amber-400 hover:text-amber-800 transition cursor-pointer"
                >
                  <RotateCcwIcon class="w-3.5 h-3.5" />
                  <span>{{ showRollbackForm ? 'Tutup form pengembalian' : 'Kembalikan tahap approval' }}</span>
                </button>
                <span v-if="rollbackSuccess" class="text-xs text-emerald-700 dark:text-emerald-400 font-medium break-words">{{ rollbackSuccess }}</span>
              </div>

              <!-- Form Rollback -->
              <div v-if="showRollbackForm" class="p-3 bg-amber-50/70 dark:bg-amber-950/20 rounded-lg border border-amber-200 dark:border-amber-800/60 space-y-2.5">
                <div>
                  <label class="block text-xs font-medium text-amber-950 dark:text-amber-200 mb-1">Kembalikan ke Tahap</label>
                  <select
                    v-model="rollbackTarget"
                    class="w-full h-8 text-xs bg-white dark:bg-slate-800 text-gray-900 dark:text-white border border-amber-300 dark:border-amber-700 rounded-md px-2.5 focus:outline-none focus:ring-1 focus:ring-amber-500 cursor-pointer"
                  >
                    <option v-for="opt in availableRollbackTargets" :key="opt.value" :value="opt.value">
                      {{ opt.label }}
                    </option>
                  </select>
                </div>

                <div>
                  <label class="block text-xs font-medium text-amber-950 dark:text-amber-200 mb-1">
                    Alasan Pengembalian <span class="text-rose-600 dark:text-rose-400">*</span>
                  </label>
                  <textarea
                    v-model="rollbackReason"
                    rows="2"
                    maxlength="500"
                    placeholder="mis. Faktur pajak tidak valid / salah nomor pembayaran / dealer minta tunda"
                    class="w-full p-2 text-xs bg-white dark:bg-slate-800 text-gray-900 dark:text-white border border-amber-300 dark:border-amber-700 rounded-md focus:outline-none focus:ring-1 focus:ring-amber-500 placeholder:text-gray-400 dark:placeholder:text-slate-500"
                  ></textarea>
                </div>

                <div v-if="rollbackTarget === 'approved'" class="flex items-center gap-2">
                  <input
                    type="checkbox"
                    id="notify_ar_checkbox"
                    v-model="rollbackNotifyAr"
                    class="w-3.5 h-3.5 rounded text-amber-600 focus:ring-amber-500 border-gray-300 dark:border-slate-700 cursor-pointer"
                  />
                  <label for="notify_ar_checkbox" class="text-xs text-amber-950 dark:text-amber-200 cursor-pointer select-none">
                    Kirim notifikasi WhatsApp ulang ke Tim AR
                  </label>
                </div>

                <div v-if="rollbackTarget === 'awaiting_transfer'" class="flex items-center gap-2">
                  <input
                    type="checkbox"
                    id="notify_scm_checkbox"
                    v-model="rollbackNotifyScm"
                    class="w-3.5 h-3.5 rounded text-amber-600 focus:ring-amber-500 border-gray-300 dark:border-slate-700 cursor-pointer"
                  />
                  <label for="notify_scm_checkbox" class="text-xs text-amber-950 dark:text-amber-200 cursor-pointer select-none">
                    Kirim notifikasi WhatsApp transfer ke Tim SCM
                  </label>
                </div>

                <p v-if="rollbackError" class="text-xs text-rose-700 dark:text-rose-400 font-medium">{{ rollbackError }}</p>

                <div class="flex items-center justify-end gap-2 pt-1">
                  <button
                    type="button"
                    @click="showRollbackForm = false"
                    class="px-2.5 py-1 text-xs text-gray-600 dark:text-slate-300 hover:text-gray-900 dark:hover:text-white bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-md transition cursor-pointer"
                  >
                    Batal
                  </button>
                  <button
                    type="button"
                    @click="submitRollback"
                    :disabled="submittingRollback || !rollbackReason.trim()"
                    class="px-3 py-1 text-xs font-medium text-white bg-amber-600 hover:bg-amber-700 disabled:opacity-50 rounded-md transition inline-flex items-center gap-1.5 cursor-pointer"
                  >
                    <RefreshCwIcon v-if="submittingRollback" class="w-3 h-3 animate-spin" />
                    <span>{{ submittingRollback ? 'Memproses...' : 'Konfirmasi Kembalikan' }}</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- TAB 2: SEMUA CATATAN (TIMELINE LOGS) -->
          <div v-else-if="activeDetailTab === 'logs'" class="space-y-3">
            <div v-if="detailLoading" class="py-8 text-center text-gray-500 dark:text-slate-400">
              <RefreshCwIcon class="w-4 h-4 animate-spin mx-auto mb-1 text-gray-400" />
              <span>Memuat catatan...</span>
            </div>
            <div v-else-if="!detail.timeline?.length" class="py-8 text-center text-gray-400 dark:text-slate-500">
              Belum ada catatan aktivitas untuk baris ini.
            </div>
            <ul v-else class="space-y-2">
              <li
                v-for="item in detail.timeline"
                :key="item.id"
                class="p-3 rounded-lg border border-gray-100 dark:border-slate-800 bg-gray-50/60 dark:bg-slate-800/40 hover:bg-gray-50 dark:hover:bg-slate-800/70 transition"
              >
                <div class="flex items-start justify-between gap-3">
                  <div class="flex items-center gap-2">
                    <span :class="['w-1.5 h-1.5 rounded-full shrink-0', item.success ? 'bg-emerald-500' : 'bg-rose-500']"></span>
                    <span :class="['font-semibold text-xs', item.success ? 'text-gray-900 dark:text-slate-100' : 'text-rose-700 dark:text-rose-400']">
                      {{ item.label }}{{ item.success ? '' : ' · gagal' }}
                    </span>
                  </div>
                  <span class="text-[11px] text-gray-400 dark:text-slate-500 tabular-nums whitespace-nowrap">
                    {{ formatDateTime(item.at) }}
                  </span>
                </div>

                <p v-if="item.actor || item.recipient" class="text-gray-500 dark:text-slate-400 text-xs mt-1 break-words">
                  <template v-if="item.actor">Oleh {{ item.actor }}</template>
                  <template v-if="item.actor && item.recipient"> · </template>
                  <template v-if="item.recipient">Ke {{ item.recipient }}</template>
                </p>

                <p v-if="item.note" class="text-gray-600 dark:text-slate-300 text-xs mt-1.5 p-2 bg-white dark:bg-slate-900 rounded-md border border-gray-200/60 dark:border-slate-800 break-words font-sans leading-relaxed">
                  {{ item.note }}
                </p>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, h } from 'vue';
import axios from 'axios';
import AreaTabs from '@/components/AreaTabs.vue';
import FilamentPagination from '@/components/ui/FilamentPagination.vue';
import { useAutoRefresh, createRequestTracker } from '@/composables/useAutoRefresh';
import { useAuth } from '@/composables/useAuth';
import {
  ChevronRight as ChevronRightIcon,
  ChevronDown as ChevronDownIcon,
  RefreshCw as RefreshCwIcon,
  Search as SearchIcon,
  Check as CheckIcon,
  X as XIcon,
  Pause as PauseIcon,
  RotateCcw as RotateCcwIcon,
} from 'lucide-vue-next';

// Bulatan langkah: hijau selesai, kuning posisi sekarang, merah WA gagal, oranye ditunda, abu-abu belum
const StepDot = (props) => {
  const size = props.small ? 'w-4 h-4' : 'w-[18px] h-[18px]';
  const icon = props.small ? 'w-2 h-2' : 'w-2.5 h-2.5';
  const styles = {
    done: 'bg-emerald-500 text-white',
    failed: 'bg-rose-500 text-white',
    postponed: 'bg-amber-500 text-white',
    current: 'bg-white border-2 border-amber-400',
    waiting: 'bg-white border border-gray-300 dark:border-slate-500',
  };
  const inner = {
    done: () => h(CheckIcon, { class: icon, 'stroke-width': 3 }),
    failed: () => h(XIcon, { class: icon, 'stroke-width': 3 }),
    postponed: () => h(PauseIcon, { class: icon, 'stroke-width': 3 }),
    current: () => h('span', { class: 'w-1 h-1 rounded-full bg-amber-500' }),
  }[props.state];
  // Posisi sekarang berdenyut pelan, seperti titik pengantaran yang sedang berjalan
  const pulse = props.state === 'current' ? [h('span', { class: 'absolute inset-0 rounded-full bg-amber-400/40 animate-ping motion-reduce:hidden' })] : [];

  return h(
    'span',
    { class: ['relative rounded-full flex items-center justify-center', size, styles[props.state] || styles.waiting] },
    [...pulse, ...(inner ? [inner()] : [])]
  );
};
StepDot.props = { state: { type: String, default: 'waiting' }, small: Boolean };

const { canEdit } = useAuth();

const rows = ref([]);
const counts = ref({});
const stages = ref({});
const stage = ref('all');
const search = ref('');
const loading = ref(false);
const errorMessage = ref('');
const detail = ref(null);
const detailLoading = ref(false);
const sendingWa = ref(false);
const waMessage = ref(null);
const activeDetailTab = ref('steps'); // 'steps' | 'logs'

const completedStepsCount = computed(() => {
  return detail.value?.steps?.filter((s) => s.state === 'done').length || 0;
});

const isRedundantWaNote = (note) => {
  if (!note) return true;
  const s = String(note).toLowerCase();
  return s.includes('berhasil dikirim ke whatsapp') || s.startsWith('notifikasi klaim program') || s.startsWith('permintaan transfer');
};

const stepBadgeClass = (state) => {
  if (state === 'done') return 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:border-emerald-800/80 dark:text-emerald-300';
  if (state === 'current') return 'bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/40 dark:border-amber-800/80 dark:text-amber-300';
  if (state === 'failed') return 'bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-950/40 dark:border-rose-800/80 dark:text-rose-300';
  if (state === 'postponed') return 'bg-orange-50 text-orange-700 border border-orange-200 dark:bg-orange-950/40 dark:border-orange-800/80 dark:text-orange-300';
  return 'bg-gray-100 text-gray-500 border border-gray-200 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-400';
};

const showRollbackForm = ref(false);
const rollbackTarget = ref('approved');
const rollbackReason = ref('');
const rollbackNotifyAr = ref(false);
const rollbackNotifyScm = ref(false);
const submittingRollback = ref(false);
const rollbackError = ref('');
const rollbackSuccess = ref('');

const availableRollbackTargets = computed(() => {
  const currentStage = detail.value?.stage;

  if (currentStage === 'tax_checked' || currentStage === 'cut') {
    return [
      { value: 'approved', label: 'Menunggu AR (Potong piutang - batalkan status selesai)' },
      { value: 'awaiting_transfer', label: 'Menunggu Transfer SCM (Transfer bank - batalkan status selesai)' },
      { value: 'postponed', label: 'Ditunda Dealer (Pending Dealer)' },
      { value: 'waiting_telemarketing', label: 'Reset ke Awal (Belum ke Telemarketing)' },
    ];
  }

  if (currentStage === 'approved') {
    return [
      { value: 'awaiting_transfer', label: 'Alihkan ke Transfer SCM (Dealer minta transfer bank)' },
      { value: 'postponed', label: 'Ditunda Dealer (Pending Dealer)' },
      { value: 'waiting_telemarketing', label: 'Reset ke Awal (Belum ke Telemarketing)' },
    ];
  }

  if (currentStage === 'awaiting_transfer') {
    return [
      { value: 'approved', label: 'Alihkan ke Potong AR (Dealer minta potong piutang)' },
      { value: 'postponed', label: 'Ditunda Dealer (Pending Dealer)' },
      { value: 'waiting_telemarketing', label: 'Reset ke Awal (Belum ke Telemarketing)' },
    ];
  }

  if (currentStage === 'postponed') {
    return [
      { value: 'approved', label: 'Menunggu AR (Dealer Setuju Potong)' },
      { value: 'awaiting_transfer', label: 'Menunggu Transfer SCM (Dealer Setuju Ditransfer)' },
      { value: 'waiting_telemarketing', label: 'Reset ke Awal (Belum ke Telemarketing)' },
    ];
  }

  if (currentStage === 'waiting_approval') {
    return [
      { value: 'waiting_telemarketing', label: 'Reset ke Awal (Belum ke Telemarketing)' },
    ];
  }

  return [];
});

const canRollback = computed(() => availableRollbackTargets.value.length > 0);

// WA yang bisa dikirim (ulang) dari Detail: permintaan transfer ke Tim SCM, info potong ke Tim SCM, cek PPh ke Tim Pajak,
// dan hasil cek PPh ke Tim AP bila belum terkirim
const resendActions = computed(() => {
  const actions = [];
  if (detail.value?.stage === 'awaiting_transfer') {
    actions.push({ endpoint: 'send-wa-scm-transfer', label: 'Kirim WA transfer ke Tim SCM' });
  }
  const scmCutStep = detail.value?.steps?.find((step) => step.key === 'scm_cut');
  if (['cut', 'tax_checked'].includes(detail.value?.stage) && scmCutStep && scmCutStep.state !== 'done') {
    actions.push({ endpoint: 'send-wa-scm', label: 'Kirim info potong ke Tim SCM' });
  }
  if (detail.value?.stage === 'cut') {
    actions.push({ endpoint: 'send-wa-tax', label: 'Kirim WA ke Tim Pajak' });
  }
  const apStep = detail.value?.steps?.find((step) => step.key === 'ap');
  if (detail.value?.stage === 'tax_checked' && apStep && apStep.state !== 'done') {
    actions.push({ endpoint: 'send-wa-ap', label: 'Kirim hasil ke WA Tim AP' });
  }
  return actions;
});
const pagination = reactive({ current_page: 1, last_page: 1, per_page: 25, total: 0 });
let debounceTimer = null;

const stageOptions = computed(() => [
  { value: 'all', label: 'Semua tahap' },
  ...Object.entries(stages.value).map(([value, label]) => ({ value, label })),
]);

const formatCount = (value) => Number(value || 0).toLocaleString('id-ID');
const formatRupiah = (value) => (value === null || value === undefined || value === '' ? '-' : `Rp ${new Intl.NumberFormat('id-ID').format(Number(value))}`);
const formatDateTime = (iso) => (iso ? new Date(iso).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-');

const rowProgram = (row) => [row.kode_bt, row.program_name || row.program].filter(Boolean).join(' · ') || '-';
const rowMeta = (row) => [rowProgram(row), formatRupiah(row.net_pay)].join(' · ');

// Nama dari data tertulis kapital semua; ditampilkan "Akhmad Zulfikar" supaya mudah dibaca
const displayName = (name) => {
  if (!name) return '';
  if (/[a-z]/.test(name)) return name;
  return name.toLowerCase().replace(/(^|[\s.'-])(\p{L})/gu, (match, separator, letter) => separator + letter.toUpperCase());
};

// Waktu langkah: jam tercatat, atau tanggal potong dari Data Program untuk baris lama
const stepTime = (step, long = false) => {
  if (step.at) {
    return long
      ? formatDateTime(step.at)
      : new Date(step.at).toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
  }
  return step.date || '';
};

// Label singkat di daftar; label lengkap tetap di tooltip dan Detail
// Tiap langkah dibaca sebagai kejadian; WhatsApp hanya alat kirimnya, nama penerimanya ada di bawah label
const STEP_SHORT_LABELS = {
  ready: 'Bisa dipotong',
  telemarketing: 'Dikirim ke Telemarketing',
  approval: 'Dealer setuju',
  ar: 'Dikirim ke AR',
  cut: 'Dipotong AR',
  scm_cut: 'Info ke SCM',
  scm_transfer: 'Dikirim ke SCM',
  transferred: 'Ditransfer SCM',
  tax: 'Dikirim ke Pajak',
  tax_check: 'Dicek Pajak',
  ap: 'Info ke AP',
  scm: 'Hasil ke SCM',
};
const shortLabel = (step) => (step.state === 'postponed' ? 'Ditunda dealer' : STEP_SHORT_LABELS[step.key] || step.label);
const stepTooltip = (step) => [step.label, displayName(step.person), stepTime(step, true)].filter(Boolean).join(' · ');

const STEP_STATE_LABELS = {
  done: 'Selesai',
  current: 'Menunggu',
  waiting: 'Belum dimulai',
  failed: 'Gagal terkirim',
  postponed: 'Ditunda',
};
const stepStateLabel = (step) => {
  if (step.state === 'done' && !step.at && !step.date && step.key !== 'ready') return 'Selesai · waktu tidak tercatat';
  return STEP_STATE_LABELS[step.state] || step.state;
};

const detailButtonClass = 'h-7 px-2.5 shrink-0 items-center rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-[11px] font-medium text-gray-700 cursor-pointer';

// Garis menuju sebuah langkah: hijau penuh bila sudah lewat, jalur hijau muda (dengan kilatan berjalan) menuju posisi sekarang
const connectorClass = (state) => ({
  done: 'bg-emerald-500',
  current: 'bg-emerald-100 dark:bg-emerald-900',
  failed: 'bg-rose-300 dark:bg-rose-800',
  postponed: 'bg-amber-300 dark:bg-amber-700',
}[state] || 'bg-gray-200 dark:bg-slate-700');

const stepLabelClass = (state) => ({
  done: 'text-gray-900',
  current: 'text-amber-700',
  failed: 'text-rose-700',
  postponed: 'text-amber-700',
}[state] || 'text-gray-400');

// Mode gelap: latar transparan berwarna, teks lebih terang, cincin redup (amber & emerald diatur di app.css)
const stageBadgeClass = (value) => ({
  waiting_telemarketing: 'bg-gray-50 text-gray-600 ring-gray-200 dark:ring-slate-700',
  waiting_approval: 'bg-amber-50 text-amber-700 ring-amber-200 dark:ring-amber-500/30',
  postponed: 'bg-orange-50 text-orange-700 ring-orange-200 dark:bg-orange-500/15 dark:text-orange-300 dark:ring-orange-500/30',
  approved: 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/15 dark:text-sky-300 dark:ring-sky-500/30',
  awaiting_transfer: 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/15 dark:text-sky-300 dark:ring-sky-500/30',
  cut: 'bg-violet-50 text-violet-700 ring-violet-200 dark:bg-violet-500/15 dark:text-violet-300 dark:ring-violet-500/30',
  tax_checked: 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:ring-emerald-500/30',
}[value] || 'bg-gray-50 text-gray-600 ring-gray-200 dark:ring-slate-700');

// Sudah potong / Done transfer: netral, di samping status tahap
const settlementBadgeClass = 'inline-flex items-center h-[18px] px-1.5 rounded-full text-[10px] font-medium ring-1 ring-inset bg-white text-gray-700 ring-gray-300 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-600';

const startRowsRequest = createRequestTracker();

async function fetchRows(page = 1, { silent = false } = {}) {
  const isLatest = startRowsRequest();
  if (!silent) {
    loading.value = true;
    errorMessage.value = '';
  }
  try {
    const params = { page, per_page: pagination.per_page, stage: stage.value };
    if (search.value.trim()) params.search = search.value.trim();
    const res = await axios.get('/api/data-program/approval-history', { params });
    if (!isLatest()) return;
    rows.value = res.data.data || [];
    counts.value = res.data.counts || {};
    stages.value = res.data.stages || {};
    Object.assign(pagination, res.data.pagination || {});
  } catch (err) {
    if (!silent) errorMessage.value = 'Gagal memuat riwayat approval: ' + (err.response?.data?.message || err.message);
  } finally {
    if (!silent && isLatest()) loading.value = false;
  }
}

// Langkah approval baru (dealer setuju, AR potong, cek pajak) muncul sendiri; berhenti selama Detail terbuka
useAutoRefresh(() => fetchRows(pagination.current_page, { silent: true }), {
  isPaused: () => loading.value || detail.value !== null || sendingWa.value,
});

function debounceFetch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => fetchRows(1), 350);
}

async function openDetail(row) {
  detail.value = { ...row, timeline: [] };
  detailLoading.value = true;
  try {
    const res = await axios.get(`/api/data-program/${row.id}/approval-history`);
    detail.value = res.data.data;
  } catch {
    // Detail tetap tampil dari data baris; hanya daftar catatan yang kosong
  } finally {
    detailLoading.value = false;
  }
}

// Reset state saat membuka baris baru
watch(() => detail.value?.id, () => {
  activeDetailTab.value = 'steps';
  waMessage.value = null;
  showRollbackForm.value = false;
  rollbackReason.value = '';
  rollbackError.value = '';
  rollbackSuccess.value = '';
  rollbackNotifyAr.value = false;
  rollbackNotifyScm.value = false;
  if (availableRollbackTargets.value.length > 0) {
    rollbackTarget.value = availableRollbackTargets.value[0].value;
  }
});

async function submitRollback() {
  if (!canEdit.value || !detail.value || !rollbackReason.value.trim()) return;

  submittingRollback.value = true;
  rollbackError.value = '';
  rollbackSuccess.value = '';

  try {
    const res = await axios.post(`/api/data-program/${detail.value.id}/approval-history/rollback`, {
      target_stage: rollbackTarget.value,
      reason: rollbackReason.value.trim(),
      notify_ar: rollbackNotifyAr.value,
      notify_scm: rollbackNotifyScm.value,
    });

    rollbackSuccess.value = res.data?.message || 'Tahap approval berhasil dikembalikan.';
    if (res.data?.data) {
      detail.value = {
        ...detail.value,
        ...res.data.data,
      };

      const idx = rows.value.findIndex((r) => r.id === detail.value.id);
      if (idx !== -1) {
        rows.value[idx] = {
          ...rows.value[idx],
          ...res.data.data,
        };
      }
    }

    showRollbackForm.value = false;
    rollbackReason.value = '';
    fetchRows(pagination.current_page);
  } catch (err) {
    rollbackError.value = err.response?.data?.message || 'Gagal mengembalikan tahap approval.';
  } finally {
    submittingRollback.value = false;
  }
}

async function resendWhatsApp(action) {
  if (!canEdit.value || !action) return;
  sendingWa.value = true;
  waMessage.value = null;
  try {
    const res = await axios.post(`/api/data-program/${detail.value.id}/${action.endpoint}`);
    waMessage.value = { error: false, text: res.data.message || 'WA terkirim.' };
    await openDetail(detail.value);
    fetchRows(pagination.current_page);
  } catch (err) {
    waMessage.value = { error: true, text: err.response?.data?.message || 'Gagal mengirim WA.' };
    if (detail.value) await openDetail(detail.value);
  } finally {
    sendingWa.value = false;
  }
}

onMounted(() => fetchRows(1));
</script>

<style scoped>
/* Kilatan hijau yang berjalan di garis menuju langkah sekarang, seperti pelacak pengantaran */
.flow-runner,
.flow-runner-y {
  position: absolute;
  border-radius: 9999px;
}

.flow-runner {
  top: 0;
  bottom: 0;
  left: 0;
  width: 45%;
  background: linear-gradient(90deg, rgb(16 185 129 / 0), rgb(16 185 129) 70%);
  animation: flow-run-x 1.4s ease-in-out infinite;
}

.flow-runner-y {
  left: 0;
  right: 0;
  top: 0;
  height: 45%;
  background: linear-gradient(180deg, rgb(16 185 129 / 0), rgb(16 185 129) 70%);
  animation: flow-run-y 1.4s ease-in-out infinite;
}

@keyframes flow-run-x {
  from { transform: translateX(-100%); }
  to { transform: translateX(225%); }
}

@keyframes flow-run-y {
  from { transform: translateY(-100%); }
  to { transform: translateY(225%); }
}

/* Tanpa animasi bila pengguna memintanya: garis diisi diam setengah */
@media (prefers-reduced-motion: reduce) {
  .flow-runner,
  .flow-runner-y {
    animation: none;
  }
}
</style>
