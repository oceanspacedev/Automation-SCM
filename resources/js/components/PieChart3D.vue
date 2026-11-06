<template>
  <div ref="root" class="pie3d w-full">
    <svg
      v-if="geo"
      :width="geo.width"
      :height="geo.height"
      :viewBox="`0 0 ${geo.width} ${geo.height}`"
      class="block mx-auto overflow-visible"
      role="img"
      :aria-label="ariaLabel"
    >
      <defs>
        <!-- Dinding samping: gelap di tepi kiri/kanan supaya terlihat melengkung -->
        <linearGradient :id="`${uid}-wall`" gradientUnits="userSpaceOnUse" :x1="geo.cx - geo.rx" :x2="geo.cx + geo.rx" y1="0" y2="0">
          <stop offset="0" stop-color="#000" stop-opacity="0.42" />
          <stop offset="0.45" stop-color="#000" stop-opacity="0.12" />
          <stop offset="1" stop-color="#000" stop-opacity="0.46" />
        </linearGradient>
        <!-- Kilau halus di permukaan atas -->
        <radialGradient :id="`${uid}-sheen`" gradientUnits="userSpaceOnUse" :cx="geo.cx - geo.rx * 0.35" :cy="geo.cy - geo.ry * 0.8" :r="geo.rx * 1.25">
          <stop offset="0" stop-color="#fff" stop-opacity="0.38" />
          <stop offset="0.55" stop-color="#fff" stop-opacity="0.08" />
          <stop offset="1" stop-color="#fff" stop-opacity="0" />
        </radialGradient>
        <filter :id="`${uid}-blur`" x="-20%" y="-50%" width="140%" height="200%">
          <feGaussianBlur stdDeviation="10" />
        </filter>
      </defs>

      <!-- Bayangan di bawah pie -->
      <ellipse
        :cx="geo.cx"
        :cy="geo.cy + geo.depth + geo.ry * 0.12"
        :rx="geo.rx * 0.98"
        :ry="geo.ry * 0.92"
        class="text-gray-900"
        fill="currentColor"
        :opacity="0.14 * geo.progress"
        :filter="`url(#${uid}-blur)`"
      />

      <!-- Urutan gambar: sisi potongan (belakang dulu) → dinding depan → permukaan atas -->
      <g class="pointer-events-none">
        <template v-for="face in geo.faces" :key="face.id">
          <path :d="face.d" :style="{ fill: face.color }" :class="['pie3d-part', dimmed(face.key) && 'is-dimmed']" />
          <path :d="face.d" fill="#000" fill-opacity="0.28" :class="['pie3d-part', dimmed(face.key) && 'is-dimmed']" />
        </template>
        <template v-for="wall in geo.walls" :key="wall.id">
          <path :d="wall.d" :style="{ fill: wall.color }" :class="['pie3d-part', dimmed(wall.key) && 'is-dimmed']" />
          <path :d="wall.d" :fill="`url(#${uid}-wall)`" :class="['pie3d-part', dimmed(wall.key) && 'is-dimmed']" />
        </template>
        <template v-for="slice in geo.slices" :key="slice.key">
          <path :d="slice.top" :style="{ fill: slice.color }" :class="['pie3d-part', dimmed(slice.key) && 'is-dimmed']" />
          <path :d="slice.top" :fill="`url(#${uid}-sheen)`" :class="['pie3d-part', dimmed(slice.key) && 'is-dimmed']" />
        </template>
      </g>

      <!-- Label di luar pie dengan garis penunjuk -->
      <g v-if="geo.showLabels" :opacity="geo.labelOpacity" class="pointer-events-none">
        <g v-for="label in geo.labels" :key="label.key" :class="['pie3d-label', dimmed(label.key) && 'is-faded']">
          <polyline :points="label.line" fill="none" class="text-gray-400" stroke="currentColor" stroke-width="1" />
          <text :x="label.x" :y="label.y" :text-anchor="label.anchor" class="text-xs">
            <tspan :x="label.x" dy="-0.2em" class="text-gray-950" fill="currentColor" font-weight="600">{{ label.name }}</tspan>
            <tspan :x="label.x" dy="1.35em" class="text-gray-500 tabular-nums" fill="currentColor">{{ label.detail }}</tspan>
          </text>
        </g>
      </g>

      <!-- Area sentuh di posisi diam, supaya irisan yang bergeser saat disorot tidak berkedip -->
      <g>
        <path
          v-for="hit in geo.hits"
          :key="hit.id"
          :d="hit.d"
          fill="transparent"
          :tabindex="hit.focusable ? 0 : -1"
          class="outline-none cursor-default"
          @mouseenter="enter($event, hit.key)"
          @mousemove="emit('slice-move', $event)"
          @mouseleave="leave"
          @focus="enter($event, hit.key)"
          @blur="leave"
        />
      </g>
    </svg>

    <!-- Layar sempit: label pindah ke daftar di bawah pie -->
    <ul v-if="geo && !geo.showLabels" class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1.5 text-xs">
      <li v-for="item in legend" :key="item.key" class="flex items-center gap-1.5 min-w-0">
        <span class="w-2.5 h-2.5 rounded-sm shrink-0" :style="{ background: item.color }"></span>
        <span class="truncate text-gray-700">{{ item.label }}</span>
        <span class="ml-auto shrink-0 text-gray-500 tabular-nums">{{ item.detail }}</span>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, shallowRef, computed, watch, onMounted, onBeforeUnmount, useId } from 'vue';

/**
 * Pie 3D: satu irisan per item. `slices` sudah urut dari terbesar: { key, label, value, other? }.
 * Warna mengikuti item (bukan peringkat) selama komponen hidup; item `other` selalu abu-abu.
 */
const props = defineProps({
  slices: { type: Array, required: true },
});

const emit = defineEmits(['slice-enter', 'slice-move', 'slice-leave']);

const PALETTE_SIZE = 8;
const TILT = 0.56;
const START_TILT = 0.3;
const REST_EXPLODE = 4;
const HOVER_EXPLODE = 16;
const INTRO_MS = 1100;
const LABEL_GAP = 34;

const uid = useId();
const root = ref(null);
const width = ref(0);
const geo = shallowRef(null);
const hoverKey = ref(null);

const reduceMotion = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

// Nilai yang dianimasikan per irisan: nilai sekarang → target, dan jarak geser keluar
const anim = new Map();
let order = [];
const colorSlots = new Map();
let introStart = null;
let introT = reduceMotion ? 1 : 0;
let frame = null;
let lastTime = null;
let resizeObserver = null;

const formatNumber = (value) => new Intl.NumberFormat('id-ID').format(Math.round(Number(value) || 0));
const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
const lerp = (from, to, t) => from + (to - from) * t;
const easeOutCubic = (t) => 1 - (1 - t) ** 3;
const smoothstep = (from, to, t) => {
  const x = clamp((t - from) / (to - from), 0, 1);
  return x * x * (3 - 2 * x);
};
const shorten = (text, max) => (text.length > max ? `${text.slice(0, max - 1)}…` : text);

const colorOf = (key) => {
  const slot = colorSlots.get(key);
  return slot === 'other' ? 'var(--pie-other)' : `var(--pie-${slot + 1})`;
};

const assignColors = () => {
  for (const slice of props.slices) {
    if (colorSlots.has(slice.key)) {
      continue;
    }
    if (slice.other) {
      colorSlots.set(slice.key, 'other');
      continue;
    }
    const used = new Set([...colorSlots.values()]);
    let slot = 0;
    while (used.has(slot) && slot < PALETTE_SIZE - 1) {
      slot++;
    }
    colorSlots.set(slice.key, slot);
  }
};

const total = computed(() => props.slices.reduce((sum, slice) => sum + slice.value, 0));
const shareOf = (value) => (total.value ? Math.round((value / total.value) * 100) : 0);

const legend = computed(() => props.slices.map((slice) => ({
  key: slice.key,
  label: slice.label,
  color: colorOf(slice.key),
  detail: `${formatNumber(slice.value)} · ${shareOf(slice.value)}%`,
})));

const ariaLabel = computed(() => props.slices.map((slice) => `${slice.label} ${slice.value} (${shareOf(slice.value)}%)`).join(', '));

const dimmed = (key) => hoverKey.value !== null && hoverKey.value !== key;

/** Masukkan data baru: irisan baru tumbuh dari 0, irisan yang hilang menyusut lalu dibuang. */
const syncSlices = (initial = false) => {
  assignColors();
  const nextKeys = props.slices.map((slice) => slice.key);

  for (const slice of props.slices) {
    const entry = anim.get(slice.key);
    if (entry) {
      entry.target = slice.value;
    } else {
      anim.set(slice.key, { value: initial || reduceMotion ? slice.value : 0, target: slice.value, explode: REST_EXPLODE });
    }
  }

  const merged = [...nextKeys];
  order.forEach((key, index) => {
    if (!nextKeys.includes(key) && anim.has(key)) {
      anim.get(key).target = 0;
      merged.splice(Math.min(index, merged.length), 0, key);
    }
  });
  order = merged;
  start();
};

/** Bentuk pie dari nilai animasi saat ini. */
const render = () => {
  const w = width.value;
  if (!w) {
    geo.value = null;
    return;
  }

  const showLabels = w >= 600;
  const rx = showLabels ? clamp(w * 0.2, 110, 190) : clamp(w / 2 - 24, 80, 160);
  // Nama region dipotong sesuai ruang di samping pie (± 7px per huruf)
  const nameMax = clamp(Math.floor((w / 2 - rx - 54) / 7.2), 8, 28);
  const finalRy = rx * TILT;
  const maxDepth = rx * 0.17;
  const progress = easeOutCubic(introT);
  const tilt = lerp(START_TILT, TILT, progress);
  const ry = rx * tilt;
  const depth = maxDepth * progress;
  const padTop = 28;
  const padBottom = 30;
  const height = Math.round(padTop + finalRy * 2 + maxDepth + padBottom);
  const cx = w / 2;
  const cy = padTop + finalRy;
  const rotation = lerp(-Math.PI * 0.55, 0, progress);
  const sweep = Math.PI * 2 * progress;

  const sum = order.reduce((acc, key) => acc + Math.max(0, anim.get(key)?.value || 0), 0);
  const slices = [];
  const faces = [];
  const walls = [];
  const hits = [];
  const labels = [];

  if (sum > 0) {
    let angle = -Math.PI / 2 + rotation;

    for (const key of order) {
      const entry = anim.get(key);
      const span = (Math.max(0, entry.value) / sum) * sweep;
      const a0 = angle;
      const a1 = angle + span;
      angle = a1;
      if (span < 0.0005) {
        continue;
      }

      const mid = (a0 + a1) / 2;
      const isWhole = span >= Math.PI * 2 - 0.0005;
      const color = colorOf(key);
      const at = (explode) => ({ x: Math.cos(mid) * (isWhole ? 0 : explode), y: Math.sin(mid) * (isWhole ? 0 : explode) * tilt });
      const shapes = (offset, r) => {
        const point = (a, dz = 0) => [cx + offset.x + rx * Math.cos(a), cy + offset.y + r * Math.sin(a) + dz];
        const center = (dz = 0) => [cx + offset.x, cy + offset.y + dz];
        const top = isWhole
          ? `M ${point(a0)} A ${rx} ${r} 0 1 1 ${point(a0 + Math.PI)} A ${rx} ${r} 0 1 1 ${point(a0)} Z`
          : `M ${center()} L ${point(a0)} A ${rx} ${r} 0 ${span > Math.PI ? 1 : 0} 1 ${point(a1)} Z`;

        // Dinding luar hanya bagian depan (sudut 0..π dalam koordinat layar)
        const wallPaths = [];
        for (let k = Math.floor(a0 / (Math.PI * 2)) - 1; k <= Math.floor(a1 / (Math.PI * 2)) + 1; k++) {
          const b0 = Math.max(a0, k * Math.PI * 2);
          const b1 = Math.min(a1, k * Math.PI * 2 + Math.PI);
          if (b1 - b0 > 0.0005) {
            wallPaths.push(`M ${point(b0)} A ${rx} ${r} 0 0 1 ${point(b1)} L ${point(b1, depth)} A ${rx} ${r} 0 0 0 ${point(b0, depth)} Z`);
          }
        }

        const facePaths = [];
        if (!isWhole) {
          for (const [a, visible] of [[a0, Math.cos(a0) < 0], [a1, Math.cos(a1) > 0]]) {
            if (visible && depth > 0.5) {
              const edge = point(a);
              facePaths.push({
                d: `M ${center()} L ${edge} L ${point(a, depth)} L ${center(depth)} Z`,
                depthY: (center()[1] + edge[1]) / 2,
              });
            }
          }
        }

        return { top, wallPaths, facePaths, point };
      };

      const visual = shapes(at(entry.explode), ry);
      const rest = shapes(at(REST_EXPLODE), ry);

      slices.push({ key, color, top: visual.top });
      visual.wallPaths.forEach((d, index) => walls.push({ id: `${key}-${index}`, key, color, d }));
      visual.facePaths.forEach((face, index) => faces.push({ id: `${key}-${index}`, key, color, ...face }));
      hits.push({ id: `${key}-top`, key, d: rest.top, focusable: true });
      rest.wallPaths.forEach((d, index) => hits.push({ id: `${key}-wall-${index}`, key, d, focusable: false }));

      const slice = props.slices.find((item) => item.key === key);
      if (showLabels && slice) {
        const offset = at(entry.explode);
        const front = Math.sin(mid) > 0 ? depth / 2 : 0;
        const elbow = [cx + offset.x + (rx + 16) * Math.cos(mid), cy + offset.y + (ry + 14) * Math.sin(mid) + front];
        labels.push({
          key,
          right: Math.cos(mid) >= 0,
          rim: `${cx + offset.x + rx * Math.cos(mid)},${cy + offset.y + ry * Math.sin(mid) + front}`,
          elbow,
          y: elbow[1],
          name: shorten(slice.label, nameMax),
          detail: `${formatNumber(slice.value)} · ${shareOf(slice.value)}%`,
        });
      }
    }
  }

  // Label kiri & kanan dijauhkan satu sama lain supaya tidak bertumpuk, lalu ditarik garis ke irisannya
  for (const right of [true, false]) {
    const side = labels.filter((label) => label.right === right).sort((a, b) => a.y - b.y);
    side.forEach((label, index) => {
      label.y = Math.max(label.y, index === 0 ? 20 : side[index - 1].y + LABEL_GAP);
    });
    for (let index = side.length - 1; index >= 0; index--) {
      side[index].y = Math.min(side[index].y, index === side.length - 1 ? height - 24 : side[index + 1].y - LABEL_GAP);
    }

    const textX = right ? cx + rx + 44 : cx - rx - 44;
    side.forEach((label) => {
      label.x = textX;
      label.anchor = right ? 'start' : 'end';
      label.line = `${label.rim} ${label.elbow[0]},${label.y} ${right ? textX - 6 : textX + 6},${label.y}`;
    });
  }

  faces.sort((a, b) => a.depthY - b.depthY);

  geo.value = {
    width: w,
    height,
    cx,
    cy,
    rx,
    ry,
    depth,
    progress,
    showLabels,
    labelOpacity: smoothstep(0.7, 1, introT),
    slices,
    faces,
    walls,
    hits,
    labels: labels.map(({ key, x, y, anchor, line, name, detail }) => ({ key, x, y, anchor, line, name, detail })),
  };
};

const step = (time) => {
  frame = null;
  const dt = lastTime === null ? 16 : Math.min(64, time - lastTime);
  lastTime = time;

  if (introT < 1) {
    introStart ??= time;
    introT = Math.min(1, (time - introStart) / INTRO_MS);
  }

  let moving = introT < 1;
  const valueEase = 1 - Math.exp(-dt / 140);
  const explodeEase = 1 - Math.exp(-dt / 90);

  for (const [key, entry] of anim) {
    const explodeTarget = hoverKey.value === key ? HOVER_EXPLODE : REST_EXPLODE;
    entry.value += (entry.target - entry.value) * valueEase;
    entry.explode += (explodeTarget - entry.explode) * explodeEase;

    if (Math.abs(entry.target - entry.value) > Math.max(0.001, entry.target * 0.0005) || Math.abs(explodeTarget - entry.explode) > 0.05) {
      moving = true;
    } else {
      entry.value = entry.target;
      entry.explode = explodeTarget;
    }

    if (entry.target === 0 && entry.value === 0) {
      anim.delete(key);
      order = order.filter((item) => item !== key);
    }
  }

  render();

  if (moving) {
    frame = requestAnimationFrame(step);
  } else {
    lastTime = null;
  }
};

const start = () => {
  if (reduceMotion) {
    for (const entry of anim.values()) {
      entry.value = entry.target;
    }
    render();
    return;
  }
  if (frame === null) {
    lastTime = null;
    frame = requestAnimationFrame(step);
  }
};

const enter = (event, key) => {
  hoverKey.value = key;
  const slice = props.slices.find((item) => item.key === key);
  if (slice) {
    emit('slice-enter', event, slice);
  }
  start();
};

const leave = () => {
  hoverKey.value = null;
  emit('slice-leave');
  start();
};

watch(() => props.slices.map((slice) => `${slice.key}:${slice.value}`).join('|'), () => syncSlices());

onMounted(() => {
  width.value = root.value?.clientWidth || 0;
  resizeObserver = new ResizeObserver(([entry]) => {
    width.value = Math.floor(entry.contentRect.width);
    render();
  });
  resizeObserver.observe(root.value);
  syncSlices(true);
  render();
});

onBeforeUnmount(() => {
  resizeObserver?.disconnect();
  if (frame !== null) {
    cancelAnimationFrame(frame);
  }
});
</script>

<style scoped>
/* Palet kategori (urutan tetap, sudah dicek beda warna & buta warna untuk irisan yang bersebelahan) */
.pie3d {
  --pie-1: #2a78d6;
  --pie-2: #eb6834;
  --pie-3: #1baf7a;
  --pie-4: #eda100;
  --pie-5: #e87ba4;
  --pie-6: #008300;
  --pie-7: #6250d6;
  --pie-8: #e34948;
  --pie-other: #9ca3af;
}

.pie3d:is(.dark *) {
  --pie-1: #3987e5;
  --pie-2: #d95926;
  --pie-3: #199e70;
  --pie-4: #c98500;
  --pie-5: #d55181;
  --pie-6: #008300;
  --pie-7: #9085e9;
  --pie-8: #e66767;
  --pie-other: #64748b;
}

/* Irisan lain memudar warnanya (bukan transparan, supaya bagian belakangnya tidak tembus) */
.pie3d-part {
  transition: filter 200ms ease;
}

.pie3d-part.is-dimmed {
  filter: saturate(0.2) brightness(1.2);
}

.pie3d-part.is-dimmed:is(.dark *) {
  filter: saturate(0.2) brightness(0.6);
}

.pie3d-label {
  transition: opacity 200ms ease;
}

.pie3d-label.is-faded {
  opacity: 0.4;
}

@media (prefers-reduced-motion: reduce) {
  .pie3d-part,
  .pie3d-label {
    transition: none;
  }
}
</style>
