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
              </NavigationMenuList>
            </NavigationMenu>
          </div>

          <!-- Right: Icon Orang Saja (Klik untuk Dropdown Logout) -->
          <div v-if="user" class="flex items-center">
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
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuth } from '@/composables/useAuth';
import {
  NavigationMenu,
  NavigationMenuItem,
  NavigationMenuLink,
  NavigationMenuList,
  navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { User as UserIcon, LogOut as LogOutIcon } from 'lucide-vue-next';

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
</script>
