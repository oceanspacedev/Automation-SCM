<template>
  <div class="space-y-4">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
      <span>Master Data</span>
      <ChevronRightIcon class="w-3.5 h-3.5 text-gray-400" />
      <span class="text-gray-800">Jadwal Otomatis</span>
    </div>

    <div>
      <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-950">Jadwal Otomatis</h1>
      <p class="text-xs text-gray-500 mt-0.5">Atur kapan tugas otomatis berjalan. Semua jam dalam WIB.</p>
      <p class="text-xs text-gray-500 mt-0.5">Pencocokan Data Program dengan Form Program berjalan sendiri setiap menit; di sini diatur kapan WhatsApp ke telemarketing dikirim.</p>
    </div>

    <p v-if="isLoading" class="py-10 text-center text-xs text-gray-500">Memuat jadwal...</p>

    <!-- Scheduler Laravel tidak berdetak: jadwal di bawah tidak akan dijalankan -->
    <section
      v-if="!isLoading && !schedulerAlive"
      role="status"
      class="max-w-3xl rounded-xl border border-gray-200 bg-white shadow-xs px-5 sm:px-6 py-4 flex items-start gap-3"
    >
      <AlertTriangleIcon class="w-4 h-4 mt-0.5 shrink-0 text-amber-600" />
      <div class="min-w-0 flex-1 flex flex-col sm:flex-row sm:items-start justify-between gap-3">
        <div class="min-w-0 text-xs">
          <p class="font-semibold text-gray-950">Scheduler belum berjalan, jadwal tidak akan jalan otomatis.</p>
          <p class="mt-1 text-gray-600 leading-relaxed">
            Jalankan <code :class="codeClass">php artisan schedule:work</code> (lokal) atau pasang cron
            <code :class="codeClass">php artisan schedule:run</code> (server).
          </p>
          <p class="mt-1 text-gray-500">
            {{ schedulerLastSeen ? `Terakhir terdeteksi ${formatDateTime(schedulerLastSeen)}.` : 'Belum pernah terdeteksi.' }}
          </p>
        </div>
        <button type="button" @click="checkScheduler" :disabled="isCheckingScheduler" :class="[smallButtonClass, 'self-start shrink-0 disabled:opacity-50']">
          {{ isCheckingScheduler ? 'Memeriksa...' : 'Periksa lagi' }}
        </button>
      </div>
    </section>

    <section
      v-for="item in schedules"
      :key="item.key"
      class="max-w-3xl rounded-xl border border-gray-200 bg-white shadow-xs"
    >
      <!-- Judul & saklar aktif -->
      <div class="px-5 sm:px-6 py-4 flex items-start justify-between gap-4">
        <div class="min-w-0">
          <h2 class="text-sm font-semibold text-gray-950">{{ item.name }}</h2>
          <p class="mt-0.5 text-xs text-gray-500">{{ item.description }}</p>
        </div>
        <button
          type="button"
          role="switch"
          :aria-checked="forms[item.key].is_active"
          :aria-label="forms[item.key].is_active ? 'Nonaktifkan jadwal' : 'Aktifkan jadwal'"
          @click="forms[item.key].is_active = !forms[item.key].is_active"
          class="shrink-0 inline-flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer"
        >
          <span :class="[switchTrackClass, forms[item.key].is_active ? 'bg-gray-900 dark:bg-white' : 'bg-gray-300 dark:bg-slate-700']">
            <span :class="[switchKnobClass, forms[item.key].is_active ? 'translate-x-4' : 'translate-x-0']"></span>
          </span>
          {{ forms[item.key].is_active ? 'Aktif' : 'Nonaktif' }}
        </button>
      </div>

      <!-- Pengaturan: label di kiri, isian di kanan -->
      <div class="border-t border-gray-100 divide-y divide-gray-100" :class="!forms[item.key].is_active && 'opacity-60'">
        <div :class="rowClass">
          <p :class="rowLabelClass">Jadwal</p>
          <div class="space-y-3 min-w-0">
            <div class="inline-flex items-center gap-1 p-1 rounded-lg border border-gray-200 bg-gray-50 text-xs">
              <button
                v-for="mode in modes"
                :key="mode.value"
                type="button"
                @click="forms[item.key].mode = mode.value"
                :class="[
                  'h-7 px-3 rounded-md font-medium transition cursor-pointer',
                  forms[item.key].mode === mode.value ? 'bg-white text-gray-900 shadow-2xs border border-gray-200' : 'text-gray-500 hover:text-gray-900'
                ]"
              >
                {{ mode.label }}
              </button>
            </div>

            <select
              v-if="forms[item.key].mode === 'interval'"
              :id="`interval-${item.key}`"
              v-model.number="forms[item.key].interval_minutes"
              aria-label="Interval"
              :class="[selectClass, 'w-full sm:w-52 px-3']"
            >
              <option v-for="minutes in intervalChoices" :key="minutes" :value="minutes">{{ intervalLabel(minutes) }}</option>
            </select>

            <div v-else class="flex flex-wrap items-center gap-2">
              <span
                v-for="time in forms[item.key].times"
                :key="time"
                class="inline-flex items-center gap-1 h-8 pl-3 pr-1 rounded-lg border border-gray-300 bg-white text-xs font-medium text-gray-900 tabular-nums"
              >
                {{ time }}
                <button type="button" @click="removeTime(item.key, time)" class="p-1 text-gray-400 hover:text-gray-700 cursor-pointer" :title="`Hapus jam ${time}`">
                  <XIcon class="w-3 h-3" />
                </button>
              </span>

              <!-- Tambah jam (24 jam) -->
              <span class="inline-flex items-center gap-1">
                <select :id="`hour-${item.key}`" v-model="newTimes[item.key].hour" aria-label="Jam" :class="[selectClass, 'w-16 px-2']">
                  <option v-for="h in hours" :key="h" :value="h">{{ h }}</option>
                </select>
                <span class="text-xs text-gray-400">:</span>
                <select :id="`minute-${item.key}`" v-model="newTimes[item.key].minute" aria-label="Menit" :class="[selectClass, 'w-16 px-2']">
                  <option v-for="m in minutes" :key="m" :value="m">{{ m }}</option>
                </select>
                <button type="button" @click="addTime(item.key)" :class="smallButtonClass">Tambah</button>
              </span>
            </div>
            <p v-if="forms[item.key].mode === 'times' && !forms[item.key].times.length" class="text-[11px] text-gray-500">Tambahkan minimal satu jam.</p>
          </div>
        </div>

        <div :class="rowClass">
          <p :class="rowLabelClass">Hari</p>
          <div class="space-y-2 min-w-0">
            <div class="grid grid-cols-7 gap-1.5 sm:flex sm:flex-wrap">
              <button
                v-for="day in weekDays"
                :key="day.value"
                type="button"
                @click="toggleDay(item.key, day.value)"
                :aria-pressed="isDayOn(item.key, day.value)"
                :class="[
                  'h-8 sm:w-11 rounded-lg border text-xs font-medium transition cursor-pointer',
                  isDayOn(item.key, day.value)
                    ? 'border-gray-900 bg-gray-900 text-white dark:bg-white dark:text-gray-900 dark:border-white'
                    : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'
                ]"
              >
                {{ day.label }}
              </button>
            </div>
            <p class="text-[11px] text-gray-500">{{ forms[item.key].days.length ? 'Hanya di hari yang dipilih.' : 'Tidak ada yang dipilih: jalan setiap hari.' }}</p>
          </div>
        </div>
      </div>

      <!-- Ringkasan & aksi -->
      <div class="px-5 sm:px-6 py-4 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <dl class="text-xs space-y-1 min-w-0">
          <div class="flex gap-2">
            <dt class="text-gray-500 w-24 shrink-0">Berikutnya</dt>
            <dd class="text-gray-900">{{ nextRunLabel(forms[item.key]) }}</dd>
          </div>
          <div class="flex gap-2">
            <dt class="text-gray-500 w-24 shrink-0">Terakhir jalan</dt>
            <dd class="text-gray-900 min-w-0 break-words">
              {{ item.last_run_at ? formatDateTime(item.last_run_at) : 'Belum pernah' }}
              <span v-if="item.last_result" class="block text-gray-500 mt-0.5">{{ item.last_result }}</span>
            </dd>
          </div>
        </dl>
        <div class="flex flex-col items-stretch sm:items-end gap-1.5 shrink-0">
          <div class="flex items-center gap-2">
            <button type="button" @click="runNow(item.key)" :disabled="!!busy[item.key]" :class="buttonClass">
              {{ busy[item.key] === 'run' ? 'Menjalankan...' : 'Jalankan sekarang' }}
            </button>
            <button type="button" @click="save(item.key)" :disabled="!!busy[item.key]" :class="primaryButtonClass">
              {{ busy[item.key] === 'save' ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
          <p v-if="messages[item.key]" class="text-xs sm:text-right" :class="messages[item.key].error ? 'text-red-600' : 'text-emerald-700'">{{ messages[item.key].text }}</p>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import { AlertTriangle as AlertTriangleIcon, ChevronRight as ChevronRightIcon, X as XIcon } from 'lucide-vue-next';

const buttonClass = 'h-9 px-3.5 inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 shadow-2xs transition cursor-pointer whitespace-nowrap disabled:opacity-50';
const selectClass = 'h-9 rounded-lg border border-gray-300 bg-white text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-gray-900 cursor-pointer';
const rowClass = 'px-5 sm:px-6 py-4 grid gap-2 sm:grid-cols-[120px_minmax(0,1fr)] sm:gap-6';
const rowLabelClass = 'text-xs font-medium text-gray-700 sm:pt-2';
const switchTrackClass = 'relative inline-flex h-5 w-9 shrink-0 items-center rounded-full p-0.5 transition-colors';
const switchKnobClass = 'h-4 w-4 rounded-full bg-white dark:bg-slate-900 shadow-sm transition-transform';
const smallButtonClass = 'h-9 px-3 inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-medium text-gray-700 transition cursor-pointer whitespace-nowrap';
const codeClass = 'px-1 py-0.5 rounded bg-gray-100 font-mono text-[11px] text-gray-800 whitespace-nowrap';
const primaryButtonClass = 'h-9 px-4 inline-flex items-center justify-center rounded-lg bg-gray-900 hover:bg-gray-800 dark:bg-white dark:hover:bg-slate-200 text-xs font-medium text-white dark:text-gray-900 transition cursor-pointer whitespace-nowrap disabled:opacity-50';

const modes = [
  { value: 'interval', label: 'Setiap beberapa menit' },
  { value: 'times', label: 'Jam tertentu' },
];

// 1 = Senin … 7 = Minggu (sama dengan ISO di backend)
const weekDays = [
  { value: 1, label: 'Sen' }, { value: 2, label: 'Sel' }, { value: 3, label: 'Rab' }, { value: 4, label: 'Kam' },
  { value: 5, label: 'Jum' }, { value: 6, label: 'Sab' }, { value: 7, label: 'Min' },
];
const DAY_NAMES = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

const RUN_CONFIRMATIONS = {
  telemarketing_wa: 'Kirim WhatsApp sekarang ke telemarketing untuk semua baris BISA DI POTONG yang belum dikirimi?',
};

// Pilihan jam 00–23 dan menit kelipatan 5 (format 24 jam)
const hours = Array.from({ length: 24 }, (_, h) => String(h).padStart(2, '0'));
const minutes = Array.from({ length: 12 }, (_, i) => String(i * 5).padStart(2, '0'));

const schedules = ref([]);
const intervalChoices = ref([5, 10, 15, 30, 60, 120, 180, 360]);
const isLoading = ref(false);
// Dianggap hidup sampai server bilang sebaliknya, supaya peringatan tidak berkedip saat memuat
const schedulerAlive = ref(true);
const schedulerLastSeen = ref(null);
const isCheckingScheduler = ref(false);
const forms = reactive({});
const newTimes = reactive({});
const busy = reactive({});
const messages = reactive({});

const intervalLabel = (minutes) => (minutes % 60 === 0 ? `Setiap ${minutes / 60} jam` : `Setiap ${minutes} menit`);

const formatDateTime = (value) => {
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? String(value)
    : `${date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })} ${date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}`;
};

function fillForm(item) {
  forms[item.key] = {
    is_active: !!item.is_active,
    mode: item.mode || 'interval',
    interval_minutes: item.interval_minutes || 15,
    times: [...(item.times || [])].sort(),
    days: [...(item.days || [])].map(Number),
  };
  newTimes[item.key] = { hour: '08', minute: '00' };
}

async function fetchSchedules() {
  isLoading.value = true;
  try {
    const res = await axios.get('/api/automation-schedules');
    schedules.value = res.data.data || [];
    if (res.data.interval_choices) intervalChoices.value = res.data.interval_choices;
    applySchedulerStatus(res.data);
    schedules.value.forEach(fillForm);
  } finally {
    isLoading.value = false;
  }
}

function applySchedulerStatus(data) {
  schedulerAlive.value = data.scheduler_alive !== false;
  schedulerLastSeen.value = data.scheduler_last_seen || null;
}

// Cek ulang status scheduler saja, tanpa menimpa isian form yang belum disimpan
async function checkScheduler() {
  isCheckingScheduler.value = true;
  try {
    const res = await axios.get('/api/automation-schedules');
    applySchedulerStatus(res.data);
  } catch {
    // Biarkan status terakhir yang tampil
  } finally {
    isCheckingScheduler.value = false;
  }
}

function addTime(key) {
  const time = `${newTimes[key].hour}:${newTimes[key].minute}`;
  if (forms[key].times.includes(time)) return;
  forms[key].times = [...forms[key].times, time].sort();
}

function removeTime(key, time) {
  forms[key].times = forms[key].times.filter((t) => t !== time);
}

// Tidak ada hari yang dipilih = setiap hari
const isDayOn = (key, day) => forms[key].days.includes(day);
function toggleDay(key, day) {
  const days = forms[key].days;
  forms[key].days = days.includes(day) ? days.filter((d) => d !== day) : [...days, day].sort();
}

// Perkiraan jalan berikutnya, dengan aturan yang sama seperti scheduler (dicek per menit, waktu WIB)
function nextRunLabel(form) {
  if (!form.is_active) return 'Tidak jalan (nonaktif)';
  if (form.mode === 'times' && !form.times.length) return 'Belum ada jam';

  const start = new Date();
  start.setSeconds(0, 0);
  for (let i = 1; i <= 7 * 24 * 60; i++) {
    const t = new Date(start.getTime() + i * 60000);
    const isoDay = t.getDay() === 0 ? 7 : t.getDay();
    if (form.days.length && !form.days.includes(isoDay)) continue;
    const hhmm = `${String(t.getHours()).padStart(2, '0')}:${String(t.getMinutes()).padStart(2, '0')}`;
    const due = form.mode === 'times'
      ? form.times.includes(hhmm)
      : (t.getHours() * 60 + t.getMinutes()) % Math.max(1, form.interval_minutes) === 0;
    if (due) {
      const isToday = t.toDateString() === new Date().toDateString();
      return `${isToday ? 'Hari ini' : DAY_NAMES[t.getDay()]}, ${hhmm}`;
    }
  }
  return '-';
}

const errorText = (err, fallback) => {
  const errors = err.response?.data?.errors;
  return errors ? Object.values(errors).flat()[0] : (err.response?.data?.message || fallback);
};

async function save(key) {
  busy[key] = 'save';
  messages[key] = null;
  try {
    const res = await axios.put(`/api/automation-schedules/${key}`, forms[key]);
    const index = schedules.value.findIndex((s) => s.key === key);
    schedules.value[index] = res.data.data;
    fillForm(res.data.data);
    messages[key] = { error: false, text: res.data.message || 'Jadwal disimpan.' };
  } catch (err) {
    messages[key] = { error: true, text: errorText(err, 'Gagal menyimpan jadwal.') };
  } finally {
    busy[key] = null;
  }
}

async function runNow(key) {
  // Yang dijalankan adalah pengaturan yang sudah disimpan
  if (!confirm(RUN_CONFIRMATIONS[key] || 'Jalankan sekarang?')) return;

  busy[key] = 'run';
  messages[key] = null;
  try {
    const res = await axios.post(`/api/automation-schedules/${key}/run`);
    const index = schedules.value.findIndex((s) => s.key === key);
    if (res.data.data) schedules.value[index] = { ...schedules.value[index], last_run_at: res.data.data.last_run_at, last_result: res.data.data.last_result };
    messages[key] = { error: false, text: 'Selesai dijalankan.' };
  } catch (err) {
    messages[key] = { error: true, text: errorText(err, 'Gagal menjalankan.') };
  } finally {
    busy[key] = null;
  }
}

onMounted(fetchSchedules);
</script>
