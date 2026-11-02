<template>
  <div class="min-h-screen bg-white dark:bg-[#0B1120] text-gray-900 dark:text-slate-100 flex flex-col font-sans transition-colors">
    <!-- Header (Hidden on Login Page & Standalone Public Pages) -->
    <header
      v-if="!isStandalonePage"
      class="border-b border-gray-200 dark:border-slate-800 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md sticky top-0 z-30 transition-colors"
    >
      <div class="w-full mx-auto px-4 sm:px-6">
        <div class="flex justify-between items-center h-16">
          <!-- Layar kecil: tombol menu + nama halaman yang sedang dibuka -->
          <div class="flex lg:hidden items-center gap-2 min-w-0">
            <button
              type="button"
              @click="isMobileNavOpen = true"
              class="w-9 h-9 -ml-1.5 rounded-lg flex items-center justify-center text-gray-700 dark:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-800 cursor-pointer shrink-0"
              title="Buka menu"
            >
              <MenuIcon class="w-5 h-5" />
            </button>
            <span class="text-sm font-semibold text-gray-900 dark:text-slate-100 truncate">{{ currentPageLabel }}</span>
          </div>

          <div class="hidden lg:flex items-center">
            <!-- Menu utama (layar lebar) -->
            <NavigationMenu>
              <NavigationMenuList class="flex items-center gap-1">
                <!-- 1-4. Draft, Invoice, Form Program, Data Program: dropdown per brand → area -->
                <NavigationMenuItem v-for="menu in areaMenus" :key="menu.path">
                  <Popover :open="openAreaMenu === menu.path" @update:open="(open) => (openAreaMenu = open ? menu.path : null)">
                    <PopoverTrigger as-child>
                      <button
                        type="button"
                        :class="[
                          navigationMenuTriggerStyle(),
                          isActive(menu.path) && 'bg-gray-100 dark:bg-slate-800 text-gray-900 dark:text-white font-semibold',
                          'gap-1.5 dark:text-slate-200 dark:hover:bg-slate-800'
                        ]"
                      >
                        <span>{{ menu.label }}</span>
                        <span v-if="isActive(menu.path) && route.params.area" class="text-[10px] font-semibold text-gray-500 dark:text-slate-400 uppercase">{{ activeBrandName }} · {{ activeAreaShort }}</span>
                        <ChevronDownIcon
                          class="w-3.5 h-3.5 text-gray-500 dark:text-slate-400 transition-transform duration-200"
                          :class="openAreaMenu === menu.path && 'rotate-180'"
                        />
                      </button>
                    </PopoverTrigger>
                    <PopoverContent align="start" class="w-52 max-h-[70vh] overflow-y-auto p-1.5 rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xl text-xs">
                      <!-- Brand dari Master Brand; tiap brand punya Jawa Barat & Jawa Tengah -->
                      <div v-for="(brand, index) in navBrands" :key="brand.slug" :class="index > 0 && 'mt-1 pt-1 border-t border-gray-100 dark:border-slate-800'">
                        <p class="px-3 pt-1.5 pb-1 text-[11px] font-semibold text-gray-400 dark:text-slate-500">{{ brand.name }}</p>
                        <router-link
                          v-for="area in AREAS"
                          :key="area.value"
                          :to="`${menu.path}/${brand.slug}/${area.value}`"
                          @click="openAreaMenu = null"
                          :class="[
                            'block px-3 py-2 rounded-lg transition-colors text-xs font-medium',
                            isActive(`${menu.path}/${brand.slug}/${area.value}`) ? 'bg-gray-100 dark:bg-slate-800 text-gray-900 dark:text-white font-semibold' : 'text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'
                          ]"
                        >
                          {{ area.label }}
                        </router-link>
                      </div>
                    </PopoverContent>
                  </Popover>
                </NavigationMenuItem>

                <!-- 5. Dropdown Riwayat -->
                <NavigationMenuItem v-if="canAccess('riwayat')">
                  <Popover v-model:open="isRiwayatOpen">
                    <PopoverTrigger as-child>
                      <button
                        type="button"
                        :class="[
                          navigationMenuTriggerStyle(),
                          (isActive('/dashboard') || isActive('/riwayat-program') || isActive('/riwayat-approval') || isActive('/email-logs')) && 'bg-gray-100 dark:bg-slate-800 text-gray-900 dark:text-white font-semibold',
                          'gap-1.5 dark:text-slate-200 dark:hover:bg-slate-800'
                        ]"
                      >
                        <span>Riwayat</span>
                        <ChevronDownIcon
                          class="w-3.5 h-3.5 text-gray-500 dark:text-slate-400 transition-transform duration-200"
                          :class="isRiwayatOpen && 'rotate-180'"
                        />
                      </button>
                    </PopoverTrigger>
                    <PopoverContent align="start" class="w-48 p-1.5 rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xl text-xs space-y-0.5">

                      <router-link
                        v-if="canAccess('riwayat_program')"
                        to="/riwayat-program"
                        @click="isRiwayatOpen = false"
                        :class="[
                          'block px-3 py-2 rounded-lg transition-colors text-xs font-medium',
                          isActive('/riwayat-program') ? 'bg-gray-100 dark:bg-slate-800 text-gray-900 dark:text-white font-semibold' : 'text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'
                        ]"
                      >
                        Riwayat Program
                      </router-link>

                      <!-- Alur approval ke telemarketing & AR; ikut hak akses Riwayat Program -->
                      <router-link
                        v-if="canAccess('riwayat_program')"
                        to="/riwayat-approval"
                        @click="isRiwayatOpen = false"
                        :class="[
                          'block px-3 py-2 rounded-lg transition-colors text-xs font-medium',
                          isActive('/riwayat-approval') ? 'bg-gray-100 dark:bg-slate-800 text-gray-900 dark:text-white font-semibold' : 'text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'
                        ]"
                      >
                        Riwayat Approval
                      </router-link>

                      <router-link
                        v-if="canAccess('email_logs')"
                        to="/email-logs"
                        @click="isRiwayatOpen = false"
                        :class="[
                          'block px-3 py-2 rounded-lg transition-colors text-xs font-medium',
                          isActive('/email-logs') ? 'bg-gray-100 dark:bg-slate-800 text-gray-900 dark:text-white font-semibold' : 'text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'
                        ]"
                      >
                        Riwayat Email
                      </router-link>

                      <router-link
                        v-if="canAccess('dashboard')"
                        to="/dashboard"
                        @click="isRiwayatOpen = false"
                        :class="[
                          'block px-3 py-2 rounded-lg transition-colors text-xs font-medium',
                          isActive('/dashboard') ? 'bg-gray-100 dark:bg-slate-800 text-gray-900 dark:text-white font-semibold' : 'text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'
                        ]"
                      >
                        Dashboard
                      </router-link>
                    </PopoverContent>
                  </Popover>
                </NavigationMenuItem>

                <!-- 6. Dropdown Master Data -->
                <NavigationMenuItem v-if="canAccess('master')">
                  <Popover v-model:open="isMasterOpen">
                    <PopoverTrigger as-child>
                      <button
                        type="button"
                        :class="[
                          navigationMenuTriggerStyle(),
                          masterColumns.some((column) => column.some((link) => isActive(link.to))) && 'bg-gray-100 dark:bg-slate-800 text-gray-900 dark:text-white font-semibold',
                          'gap-1.5 dark:text-slate-200 dark:hover:bg-slate-800'
                        ]"
                      >
                        <span>Master</span>
                        <ChevronDownIcon
                          class="w-3.5 h-3.5 text-gray-500 dark:text-slate-400 transition-transform duration-200"
                          :class="isMasterOpen && 'rotate-180'"
                        />
                      </button>
                    </PopoverTrigger>
                    <!-- Dua kolom supaya daftar Master tidak memanjang ke bawah -->
                    <PopoverContent align="start" :class="[masterColumns.length > 1 ? 'w-80' : 'w-48', 'p-1.5 rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xl text-xs']">
                      <div :class="['grid gap-1.5 divide-x divide-gray-100 dark:divide-slate-800', masterColumns.length > 1 ? 'grid-cols-2' : 'grid-cols-1']">
                        <div v-for="(column, index) in masterColumns" :key="index" :class="['space-y-0.5', index > 0 && 'pl-1.5']">
                          <router-link
                            v-for="link in column"
                            :key="link.to"
                            :to="link.to"
                            @click="isMasterOpen = false"
                            :class="[
                              'block px-3 py-2 rounded-lg transition-colors text-xs font-medium',
                              isActive(link.to) ? 'bg-gray-100 dark:bg-slate-800 text-gray-900 dark:text-white font-semibold' : 'text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'
                            ]"
                          >
                            {{ link.label }}
                          </router-link>
                        </div>
                      </div>
                    </PopoverContent>
                  </Popover>
                </NavigationMenuItem>
              </NavigationMenuList>
            </NavigationMenu>
          </div>

          <!-- Right: AI Running Indicator & User Profile -->
          <div v-if="user" class="flex items-center gap-3">
            <!-- Persistent AI Background Running Indicator (Visible Across All Menus) -->
            <div v-if="aiStatus.isRunning || aiRecentlyCompleted" class="relative flex items-center">
              <Popover>
                <PopoverTrigger as-child>
                  <button
                    type="button"
                    :class="[
                      'h-9 px-3 rounded-full border transition-all duration-300 flex items-center gap-2 text-xs font-medium cursor-pointer select-none focus:outline-none focus:ring-1 focus:ring-black dark:focus:ring-white',
                      aiStatus.isRunning
                        ? 'bg-emerald-50/90 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-800 text-emerald-900 dark:text-emerald-200 shadow-xs hover:bg-emerald-100/90'
                        : 'bg-gray-50 dark:bg-slate-800 border-gray-200 dark:border-slate-700 text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700'
                    ]"
                  >
                    <!-- Pulsing indicator dot when running -->
                    <span v-if="aiStatus.isRunning" class="relative flex h-2 w-2">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>

                    <BotIcon
                      :class="[
                        'w-4 h-4',
                        aiStatus.isRunning ? 'text-emerald-600 dark:text-emerald-400 animate-pulse' : 'text-gray-500 dark:text-slate-400'
                      ]"
                    />

                    <div class="hidden sm:flex items-center gap-1.5">
                      <span v-if="aiStatus.isRunning" class="font-medium text-emerald-900 dark:text-emerald-200">
                        AI Memproses
                        <span v-if="aiStatus.runningInfo?.total" class="font-mono text-[11px] font-semibold text-emerald-700 dark:text-emerald-400 ml-0.5">
                          ({{ aiStatus.runningInfo.processed }}/{{ aiStatus.runningInfo.total }})
                        </span>
                      </span>
                      <span v-else class="text-gray-700 dark:text-slate-300 font-medium flex items-center gap-1">
                        <CheckCircle2Icon class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                        <span>AI Selesai</span>
                      </span>
                    </div>

                    <Loader2Icon v-if="aiStatus.isRunning" class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 animate-spin ml-0.5" />
                  </button>
                </PopoverTrigger>

                <PopoverContent align="end" class="w-72 p-3.5 rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xl text-xs space-y-2.5 text-gray-900 dark:text-slate-100">
                  <div class="flex items-center justify-between border-b border-gray-100 dark:border-slate-800 pb-2">
                    <div class="flex items-center gap-1.5 font-semibold text-gray-900 dark:text-slate-100">
                      <BotIcon class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                      <span>Analisis AI di Latar Belakang</span>
                    </div>
                    <span
                      :class="[
                        'px-1.5 py-0.5 rounded text-[10px] font-medium uppercase',
                        aiStatus.isRunning ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300' : 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-400'
                      ]"
                    >
                      {{ aiStatus.isRunning ? 'Aktif' : 'Selesai' }}
                    </span>
                  </div>

                  <div v-if="aiStatus.isRunning" class="space-y-1.5">
                    <div class="flex justify-between text-gray-600 dark:text-slate-400 text-[11px]">
                      <span>Progres dokumen:</span>
                      <span class="font-mono font-semibold text-gray-900 dark:text-slate-100">
                        {{ aiStatus.runningInfo?.processed || 0 }} dari {{ aiStatus.runningInfo?.total || 0 }}
                      </span>
                    </div>

                    <!-- Progress bar -->
                    <div class="w-full bg-gray-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden">
                      <div
                        class="bg-emerald-500 h-1.5 rounded-full transition-all duration-300"
                        :style="{ width: progressPercentage + '%' }"
                      ></div>
                    </div>

                    <div v-if="aiStatus.runningInfo?.current_dealer" class="text-[11px] text-gray-500 dark:text-slate-400 truncate pt-0.5">
                      Memeriksa: <span class="font-medium text-gray-800 dark:text-slate-200">{{ aiStatus.runningInfo.current_dealer }}</span>
                    </div>
                  </div>

                  <div v-else class="text-[11px] text-gray-500 dark:text-slate-400">
                    Semua batch dokumen program telah selesai dianalisis.
                  </div>

                  <div class="pt-1 border-t border-gray-100 dark:border-slate-800 flex items-center justify-between text-[11px]">
                    <span class="text-gray-400 dark:text-slate-500 font-mono">Model: {{ aiStatus.currentModel || 'ag/gemini-3-flash' }}</span>
                    <router-link
                      to="/form-program"
                      class="text-gray-900 dark:text-slate-200 hover:text-black dark:hover:text-white font-medium hover:underline flex items-center gap-1"
                    >
                      <span>Ke Form Program</span>
                      <ExternalLinkIcon class="w-3 h-3" />
                    </router-link>
                  </div>
                </PopoverContent>
              </Popover>
            </div>

            <!-- Profile Popover: inisial nama, isi akun, mode gelap & keluar -->
            <Popover>
              <PopoverTrigger as-child>
                <button
                  type="button"
                  class="w-9 h-9 rounded-full overflow-hidden border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-gray-100 dark:hover:bg-slate-700 flex items-center justify-center text-sm font-medium text-gray-700 dark:text-slate-200 transition cursor-pointer focus:outline-none focus:ring-1 focus:ring-gray-900 dark:focus:ring-white"
                  :title="user.name || 'Akun Saya'"
                >
                  <img v-if="user.avatar_url" :src="user.avatar_url" alt="" class="w-full h-full object-cover" />
                  <span v-else>{{ userInitial }}</span>
                </button>
              </PopoverTrigger>
              <PopoverContent align="end" class="w-56 p-1.5 rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xl text-sm">
                <div class="px-3 py-2 mb-1 border-b border-gray-100 dark:border-slate-800">
                  <p class="font-medium text-gray-900 dark:text-slate-100 truncate">{{ user.name || 'Admin SCM' }}</p>
                  <p class="text-xs text-gray-500 dark:text-slate-400 truncate mt-0.5">{{ user.email || '-' }}</p>
                </div>

                <router-link
                  to="/profile"
                  :class="[
                    'block px-3 py-2 rounded-lg transition-colors',
                    isActive('/profile') ? 'bg-gray-100 dark:bg-slate-800 text-gray-900 dark:text-white font-medium' : 'text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800'
                  ]"
                >
                  Profil
                </router-link>

                <button
                  @click="toggleDarkMode"
                  type="button"
                  class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors cursor-pointer select-none"
                >
                  <span>Mode Gelap</span>
                  <span :class="['w-8 h-[18px] rounded-full p-0.5 flex items-center transition-colors', isDark ? 'bg-gray-900 dark:bg-white' : 'bg-gray-300 dark:bg-slate-700']">
                    <span :class="['w-3.5 h-3.5 rounded-full transition-transform', isDark ? 'translate-x-3.5 bg-white dark:bg-gray-900' : 'bg-white']"></span>
                  </span>
                </button>

                <button
                  @click="handleLogout"
                  type="button"
                  class="w-full text-left px-3 py-2 rounded-lg text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 transition-colors cursor-pointer"
                >
                  Keluar
                </button>
              </PopoverContent>
            </Popover>
          </div>
        </div>
      </div>

      <!-- Menu layar kecil: laci dari kiri, setinggi layar, sisa layar digelapkan -->
      <Teleport to="body">
        <Transition
          enter-active-class="transition-opacity duration-200"
          enter-from-class="opacity-0"
          leave-active-class="transition-opacity duration-150"
          leave-to-class="opacity-0"
        >
          <div v-if="isMobileNavOpen" class="lg:hidden fixed inset-0 z-50 bg-black/30" @click="isMobileNavOpen = false"></div>
        </Transition>
        <Transition
          enter-active-class="transition-transform duration-200 ease-out"
          enter-from-class="-translate-x-full"
          leave-active-class="transition-transform duration-150 ease-in"
          leave-to-class="-translate-x-full"
        >
          <aside
            v-if="isMobileNavOpen"
            class="lg:hidden fixed inset-y-0 left-0 z-50 w-72 max-w-[80%] bg-white dark:bg-slate-900 shadow-xl flex flex-col"
          >
            <div class="h-16 px-4 border-b border-gray-200 dark:border-slate-800 flex items-center justify-between shrink-0">
              <span class="text-sm font-semibold text-gray-900 dark:text-slate-100">Menu</span>
              <button
                type="button"
                @click="isMobileNavOpen = false"
                class="w-9 h-9 -mr-1.5 rounded-lg flex items-center justify-center text-gray-500 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 cursor-pointer"
                title="Tutup menu"
              >
                <XIcon class="w-5 h-5" />
              </button>
            </div>
      <nav class="flex-1 overflow-y-auto px-3 py-3 space-y-0.5">
        <!-- Sub-menu muncul saat menu diklik -->
        <div v-for="group in mobileNavGroups" :key="group.title">
          <button
            type="button"
            @click="openMobileGroup = openMobileGroup === group.title ? null : group.title"
            :class="[
              'w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm transition-colors cursor-pointer',
              group.links.some((link) => isActive(link.to)) ? 'text-gray-900 dark:text-white font-medium' : 'text-gray-700 dark:text-slate-300',
              'hover:bg-gray-100 dark:hover:bg-slate-800'
            ]"
          >
            <span>{{ group.title }}</span>
            <ChevronDownIcon
              class="w-4 h-4 text-gray-400 dark:text-slate-500 transition-transform duration-200"
              :class="openMobileGroup === group.title && 'rotate-180'"
            />
          </button>
          <div v-if="openMobileGroup === group.title" class="pl-3 pb-1 space-y-0.5">
            <router-link
              v-for="link in group.links"
              :key="link.to"
              :to="link.to"
              :class="[
                'block px-3 py-2 rounded-lg text-sm transition-colors',
                isActive(link.to)
                  ? 'bg-gray-100 dark:bg-slate-800 text-gray-900 dark:text-white font-medium'
                  : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800'
              ]"
            >
              {{ link.label }}
            </router-link>
          </div>
        </div>
      </nav>
          </aside>
        </Transition>
      </Teleport>
    </header>

    <!-- Main Content -->
    <main :class="['flex-1 w-full', isStandalonePage ? 'p-0 m-0' : 'mx-auto px-4 sm:px-6 py-5']">
      <router-view :key="`${route.name}-${route.params.brand || ''}-${route.params.area || ''}`" />
    </main>

    <!-- Notifikasi & konfirmasi untuk semua halaman (pengganti alert/confirm browser) -->
    <NotificationHost />
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useAuth } from '@/composables/useAuth';
import { useTheme } from '@/composables/useTheme';
import { AREAS, findArea } from '@/composables/useArea';
import { DEFAULT_BRAND, brandName, useBrands } from '@/composables/useBrands';
import {
  NavigationMenu,
  NavigationMenuItem,
  NavigationMenuLink,
  NavigationMenuList,
  navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import NotificationHost from '@/components/NotificationHost.vue';
import {
  Bot as BotIcon,
  CheckCircle2 as CheckCircle2Icon,
  Loader2 as Loader2Icon,
  ExternalLink as ExternalLinkIcon,
  ChevronDown as ChevronDownIcon,
  Menu as MenuIcon,
  X as XIcon,
} from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();
const { user, logout } = useAuth();
const { isDark, toggleDarkMode, initTheme } = useTheme();

const isRiwayatOpen = ref(false);
const isMasterOpen = ref(false);

// Menu yang datanya dipisah per area: dropdown Jawa Barat / Jawa Tengah
const areaMenus = computed(() => [
  { label: 'Draft', path: '/drafts', feature: 'drafts' },
  { label: 'Invoice', path: '/invoices', feature: 'invoices' },
  { label: 'Form Program', path: '/form-program', feature: 'form_program' },
  { label: 'Data Program', path: '/data-program', feature: 'data_program' },
].filter((menu) => canAccess(menu.feature)));
const openAreaMenu = ref(null);

const activeAreaShort = computed(() => findArea(route.params.area).short);

// Brand dari Master Brand (yang aktif) untuk menu; Realme tetap tampil selama daftar belum termuat
const { activeBrands, loadBrands } = useBrands();
const navBrands = computed(() => (activeBrands.value.length ? activeBrands.value : [{ slug: DEFAULT_BRAND, name: brandName(DEFAULT_BRAND) }]));
const activeBrandName = computed(() => brandName(route.params.brand));

// Huruf pertama nama akun untuk tombol menu profil
const userInitial = computed(() => (user.value?.name || user.value?.email || 'A').trim().charAt(0).toUpperCase());

const isLoginPage = computed(() => route.path === '/login');
const isStandalonePage = computed(() => {
  return (
    route.meta?.isStandalone === true ||
    route.path === '/login' ||
    route.path === '/register'
  );
});

const isActive = (path) => {
  if (path === '/dashboard') {
    return route.path === '/' || route.path === '/dashboard';
  }
  return route.path.startsWith(path);
};

// Role & Permission Access Control
const canAccess = (feature) => {
  if (!user.value) return true;

  // 1. Jika user memiliki daftar custom permissions
  if (Array.isArray(user.value.permissions) && user.value.permissions.length > 0) {
    if (feature === 'drafts') return user.value.permissions.includes('drafts');
    if (feature === 'invoices') return user.value.permissions.includes('invoices');
    if (feature === 'form_program') return user.value.permissions.includes('form_program');
    if (feature === 'data_program') return user.value.permissions.includes('data_program');
    if (feature === 'riwayat') {
      return (
        user.value.permissions.includes('riwayat_program') ||
        user.value.permissions.includes('email_logs') ||
        user.value.permissions.includes('dashboard')
      );
    }
    if (feature === 'riwayat_program') return user.value.permissions.includes('riwayat_program');
    if (feature === 'email_logs') return user.value.permissions.includes('email_logs');
    if (feature === 'dashboard') return user.value.permissions.includes('dashboard');
    if (feature === 'master') {
      return (
        user.value.permissions.includes('master_program') ||
        user.value.permissions.includes('master_dealer') ||
        user.value.permissions.includes('master_customer') ||
        user.value.permissions.includes('master_user')
      );
    }
    if (feature === 'master_program') return user.value.permissions.includes('master_program');
    if (feature === 'master_dealer') return user.value.permissions.includes('master_dealer');
    if (feature === 'master_customer') return user.value.permissions.includes('master_customer');
    if (feature === 'master_user') return user.value.permissions.includes('master_user');
    return user.value.permissions.includes(feature);
  }

  // 2. Fallback ke default role
  const role = user.value.role || 'scm';
  // Admin & SCM default bisa melihat semuanya
  if (role === 'admin' || role === 'scm') {
    return true;
  }

  if (feature === 'drafts') return role === 'ar';
  if (feature === 'invoices') return role === 'ar';
  if (feature === 'form_program') return role === 'telemarketing';
  if (feature === 'data_program') return false;
  if (feature === 'riwayat') return true;
  if (feature === 'riwayat_program') return true;
  if (feature === 'email_logs') return role === 'ar';
  if (feature === 'dashboard') return role === 'ar';
  if (feature === 'master') return false;
  if (feature === 'master_program') return false;
  if (feature === 'master_dealer') return false;
  if (feature === 'master_customer') return false;
  if (feature === 'master_user') return false;

  return false;
};

// Menu Master dibagi dua kolom: program & wilayah, lalu toko, badan usaha & akun
const masterColumns = computed(() => [
  [
    canAccess('master_program') && { label: 'Brand', to: '/brands' },
    canAccess('master_program') && { label: 'Program', to: '/programs' },
    canAccess('master_program') && { label: 'Region', to: '/regions' },
    canAccess('master_program') && { label: 'AR', to: '/ar' },
  ],
  [
    canAccess('master_dealer') && { label: 'Dealer', to: '/dealers' },
    canAccess('master_customer') && { label: 'Badan Usaha', to: '/badan-usaha' },
    canAccess('master_user') && { label: 'User', to: '/users' },
    canAccess('master_user') && { label: 'Jadwal Otomatis', to: '/jadwal-otomatis' },
    // Reset data transaksi khusus Admin
    user.value?.role === 'admin' && { label: 'Reset Data', to: '/reset-data' },
  ],
].map((column) => column.filter(Boolean)).filter((column) => column.length > 0));

// Menu layar kecil: semua menu navigasi atas, dikelompokkan seperti dropdown desktop
const isMobileNavOpen = ref(false);
const openMobileGroup = ref(null);

const mobileNavGroups = computed(() => {
  const areaLinks = (path) => navBrands.value.flatMap((brand) => AREAS.map((area) => ({
    label: `${brand.name} · ${area.label}`,
    to: `${path}/${brand.slug}/${area.value}`,
  })));

  return [
    ...areaMenus.value.map((menu) => ({ title: menu.label, path: menu.path, links: areaLinks(menu.path) })),
    canAccess('riwayat') && {
      title: 'Riwayat',
      links: [
        canAccess('riwayat_program') && { label: 'Riwayat Program', to: '/riwayat-program' },
        canAccess('riwayat_program') && { label: 'Riwayat Approval', to: '/riwayat-approval' },
        canAccess('email_logs') && { label: 'Riwayat Email', to: '/email-logs' },
        canAccess('dashboard') && { label: 'Dashboard', to: '/dashboard' },
      ],
    },
    canAccess('master') && { title: 'Master', links: masterColumns.value.flat() },
  ]
    .filter(Boolean)
    .map((group) => ({ ...group, links: group.links.filter(Boolean) }))
    .filter((group) => group.links.length > 0);
});

// Nama halaman yang sedang dibuka, mis. "Form Program · Jawa Barat"
const currentPageLabel = computed(() => {
  for (const group of mobileNavGroups.value) {
    const link = group.links.find((item) => isActive(item.to));
    if (link) return `${group.title} · ${link.label}`;
    // Halaman detail (mis. /drafts/12) tidak punya area di URL
    if (group.path && isActive(group.path)) return group.title;
  }

  return isActive('/profile') ? 'Profil' : 'Menu';
});

watch(() => route.fullPath, () => {
  isMobileNavOpen.value = false;
  openMobileGroup.value = null;
});

// Halaman di belakang laci menu tidak ikut ter-scroll
watch(isMobileNavOpen, (open) => {
  document.body.style.overflow = open ? 'hidden' : '';
});

const handleLogout = async () => {
  await logout();
  router.push('/login');
};

// Global AI Background Running State
const aiStatus = reactive({
  isRunning: false,
  runningInfo: null,
  currentModel: '',
  total2026: 0,
  unanalyzed2026: 0,
});
const aiRecentlyCompleted = ref(false);
let completedTimer = null;
let pollTimer = null;

const progressPercentage = computed(() => {
  const total = aiStatus.runningInfo?.total || 1;
  const processed = aiStatus.runningInfo?.processed || 0;
  return Math.min(100, Math.round((processed / total) * 100));
});

const checkAiStatus = async () => {
  // Status AI hanya untuk pengguna yang sudah login (API-nya butuh login)
  if (isStandalonePage.value || !user.value) return;
  try {
    const res = await axios.get('/api/program-submissions/ai-status');
    const data = res.data;
    const wasRunning = aiStatus.isRunning;
    aiStatus.isRunning = !!data.is_running;
    aiStatus.runningInfo = data.running_info || null;
    aiStatus.currentModel = data.current_model || '';
    aiStatus.total2026 = data.total_2026 || 0;
    aiStatus.unanalyzed2026 = data.unanalyzed_2026 || 0;

    // Detect transition from running to finished
    if (wasRunning && !aiStatus.isRunning) {
      aiRecentlyCompleted.value = true;
      if (completedTimer) clearTimeout(completedTimer);
      completedTimer = setTimeout(() => {
        aiRecentlyCompleted.value = false;
      }, 15000);
    }
  } catch (e) {
    // Ignore polling errors
  }
};

onMounted(() => {
  initTheme();
  if (!isStandalonePage.value) {
    checkAiStatus();
  }
  // Poll every 4 seconds for responsive background progress tracking
  pollTimer = setInterval(() => {
    if (!isStandalonePage.value) {
      checkAiStatus();
    }
  }, 4000);
});

// Daftar brand untuk menu dimuat setelah login
watch(user, (value) => {
  if (value) loadBrands();
}, { immediate: true });

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer);
  if (completedTimer) clearTimeout(completedTimer);
});
</script>
