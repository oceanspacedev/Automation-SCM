<template>
  <div class="min-h-screen bg-white text-gray-900 flex flex-col font-sans">
    <!-- Header (Hidden on Login Page) -->
    <header
      v-if="!isLoginPage"
      class="border-b border-gray-200 bg-white sticky top-0 z-30"
    >
      <div class="w-full mx-auto px-4 sm:px-6">
        <div class="flex justify-between items-center h-16">
          <div class="flex items-center space-x-6">
            <!-- Brand with SCM Logo (Besar & Tanpa Teks SCM Tambahan) -->
            <router-link to="/dashboard" class="flex items-center group mr-3" title="SCM - Supply Chain Management">
              <img
                src="/images/scm-logo.png"
                alt="SCM Supply Chain Management"
                class="h-11 w-auto max-w-[130px] object-contain group-hover:scale-105 transition"
              />
            </router-link>

            <!-- Shadcn-Vue NavigationMenu (Direct Links without Sub-menu) -->
            <NavigationMenu>
              <NavigationMenuList class="flex items-center gap-1">
                <!-- 1. Dashboard -->
                <NavigationMenuItem>
                  <NavigationMenuLink as-child>
                    <router-link
                      to="/dashboard"
                      :class="[
                        navigationMenuTriggerStyle(),
                        isActive('/dashboard') && 'bg-gray-100 text-gray-900 font-semibold'
                      ]"
                    >
                      Dashboard
                    </router-link>
                  </NavigationMenuLink>
                </NavigationMenuItem>

                <!-- 2. Draft -->
                <NavigationMenuItem>
                  <NavigationMenuLink as-child>
                    <router-link
                      to="/drafts"
                      :class="[
                        navigationMenuTriggerStyle(),
                        isActive('/drafts') && 'bg-gray-100 text-gray-900 font-semibold'
                      ]"
                    >
                      Draft
                    </router-link>
                  </NavigationMenuLink>
                </NavigationMenuItem>

                <!-- 3. Invoice -->
                <NavigationMenuItem>
                  <NavigationMenuLink as-child>
                    <router-link
                      to="/invoices"
                      :class="[
                        navigationMenuTriggerStyle(),
                        isActive('/invoices') && 'bg-gray-100 text-gray-900 font-semibold'
                      ]"
                    >
                      Invoice
                    </router-link>
                  </NavigationMenuLink>
                </NavigationMenuItem>

                <!-- 4. Riwayat Email -->
                <NavigationMenuItem>
                  <NavigationMenuLink as-child>
                    <router-link
                      to="/email-logs"
                      :class="[
                        navigationMenuTriggerStyle(),
                        isActive('/email-logs') && 'bg-gray-100 text-gray-900 font-semibold'
                      ]"
                    >
                      Riwayat Email
                    </router-link>
                  </NavigationMenuLink>
                </NavigationMenuItem>

                <!-- 5. Form Program -->
                <NavigationMenuItem>
                  <NavigationMenuLink as-child>
                    <router-link
                      to="/form-program"
                      :class="[
                        navigationMenuTriggerStyle(),
                        isActive('/form-program') && 'bg-gray-100 text-gray-900 font-semibold'
                      ]"
                    >
                      Form Program
                    </router-link>
                  </NavigationMenuLink>
                </NavigationMenuItem>

                <!-- 6. Data Program -->
                <NavigationMenuItem>
                  <NavigationMenuLink as-child>
                    <router-link
                      to="/data-program"
                      :class="[
                        navigationMenuTriggerStyle(),
                        isActive('/data-program') && 'bg-gray-100 text-gray-900 font-semibold'
                      ]"
                    >
                      Data Program
                    </router-link>
                  </NavigationMenuLink>
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
                      'h-9 px-3 rounded-full border transition-all duration-300 flex items-center gap-2 text-xs font-medium cursor-pointer select-none focus:outline-none focus:ring-1 focus:ring-black',
                      aiStatus.isRunning
                        ? 'bg-emerald-50/90 border-emerald-300 text-emerald-900 shadow-xs hover:bg-emerald-100/90'
                        : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'
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
                        aiStatus.isRunning ? 'text-emerald-600 animate-pulse' : 'text-gray-500'
                      ]"
                    />

                    <div class="flex items-center gap-1.5">
                      <span v-if="aiStatus.isRunning" class="font-medium text-emerald-900">
                        AI Memproses
                        <span v-if="aiStatus.runningInfo?.total" class="font-mono text-[11px] font-semibold text-emerald-700 ml-0.5">
                          ({{ aiStatus.runningInfo.processed }}/{{ aiStatus.runningInfo.total }})
                        </span>
                      </span>
                      <span v-else class="text-gray-700 font-medium flex items-center gap-1">
                        <CheckCircle2Icon class="w-3.5 h-3.5 text-emerald-600" />
                        <span>AI Selesai</span>
                      </span>
                    </div>

                    <Loader2Icon v-if="aiStatus.isRunning" class="w-3.5 h-3.5 text-emerald-600 animate-spin ml-0.5" />
                  </button>
                </PopoverTrigger>

                <PopoverContent align="end" class="w-72 p-3.5 rounded-xl border border-gray-200 bg-white shadow-xl text-xs space-y-2.5">
                  <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                    <div class="flex items-center gap-1.5 font-semibold text-gray-900">
                      <BotIcon class="w-4 h-4 text-emerald-600" />
                      <span>Analisis AI di Latar Belakang</span>
                    </div>
                    <span
                      :class="[
                        'px-1.5 py-0.5 rounded text-[10px] font-medium uppercase',
                        aiStatus.isRunning ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'
                      ]"
                    >
                      {{ aiStatus.isRunning ? 'Aktif' : 'Selesai' }}
                    </span>
                  </div>

                  <div v-if="aiStatus.isRunning" class="space-y-1.5">
                    <div class="flex justify-between text-gray-600 text-[11px]">
                      <span>Progres dokumen:</span>
                      <span class="font-mono font-semibold text-gray-900">
                        {{ aiStatus.runningInfo?.processed || 0 }} dari {{ aiStatus.runningInfo?.total || 0 }}
                      </span>
                    </div>

                    <!-- Progress bar -->
                    <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                      <div
                        class="bg-emerald-500 h-1.5 rounded-full transition-all duration-300"
                        :style="{ width: progressPercentage + '%' }"
                      ></div>
                    </div>

                    <div v-if="aiStatus.runningInfo?.current_dealer" class="text-[11px] text-gray-500 truncate pt-0.5">
                      Memeriksa: <span class="font-medium text-gray-800">{{ aiStatus.runningInfo.current_dealer }}</span>
                    </div>
                  </div>

                  <div v-else class="text-[11px] text-gray-500">
                    Semua batch dokumen program telah selesai dianalisis.
                  </div>

                  <div class="pt-1 border-t border-gray-100 flex items-center justify-between text-[11px]">
                    <span class="text-gray-400 font-mono">Model: {{ aiStatus.currentModel || 'ag/gemini-3-flash' }}</span>
                    <router-link
                      to="/form-program"
                      class="text-gray-900 hover:text-black font-medium hover:underline flex items-center gap-1"
                    >
                      <span>Ke Form Program</span>
                      <ExternalLinkIcon class="w-3 h-3" />
                    </router-link>
                  </div>
                </PopoverContent>
              </Popover>
            </div>

            <!-- Profile Popover -->
            <Popover>
              <PopoverTrigger as-child>
                <button
                  type="button"
                  class="w-9 h-9 rounded-full border border-neutral-200 bg-white hover:bg-neutral-100 flex items-center justify-center text-neutral-700 transition cursor-pointer shadow-2xs focus:outline-none focus:ring-1 focus:ring-black"
                  title="Akun Saya"
                >
                  <UserIcon class="w-4 h-4 text-neutral-700" />
                </button>
              </PopoverTrigger>
              <PopoverContent align="end" class="w-56 p-2 rounded-xl border border-neutral-200 bg-white shadow-lg">
                <!-- Info Akun -->
                <div class="px-2.5 py-2 border-b border-neutral-100">
                  <div class="font-semibold text-sm text-neutral-900 leading-tight">
                    {{ user.name || 'Admin SCM' }}
                  </div>
                  <div class="text-xs text-neutral-500 truncate mt-0.5">
                    {{ user.email || 'admin@scm.com' }}
                  </div>
                </div>

                <!-- Tombol Logout -->
                <div class="pt-1.5">
                  <button
                    @click="handleLogout"
                    type="button"
                    class="w-full px-2.5 py-2 rounded-lg text-left text-xs font-medium text-neutral-700 hover:bg-neutral-100 hover:text-neutral-900 transition cursor-pointer flex items-center gap-2"
                  >
                    <LogOutIcon class="w-4 h-4 text-neutral-500" />
                    <span>Keluar</span>
                  </button>
                </div>
              </PopoverContent>
            </Popover>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main :class="['flex-1 w-full', isLoginPage ? '' : 'mx-auto px-4 sm:px-6 py-5']">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useAuth } from '@/composables/useAuth';
import {
  NavigationMenu,
  NavigationMenuItem,
  NavigationMenuLink,
  NavigationMenuList,
  navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import {
  User as UserIcon,
  LogOut as LogOutIcon,
  Bot as BotIcon,
  CheckCircle2 as CheckCircle2Icon,
  Loader2 as Loader2Icon,
  ExternalLink as ExternalLinkIcon,
} from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();
const { user, logout } = useAuth();

const isLoginPage = computed(() => route.path === '/login');

const isActive = (path) => {
  if (path === '/dashboard') {
    return route.path === '/' || route.path === '/dashboard';
  }
  return route.path.startsWith(path);
};

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
  if (isLoginPage.value) return;
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
  checkAiStatus();
  // Poll every 4 seconds for responsive background progress tracking
  pollTimer = setInterval(checkAiStatus, 4000);
});

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer);
  if (completedTimer) clearTimeout(completedTimer);
});
</script>
