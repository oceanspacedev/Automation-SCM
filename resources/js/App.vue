<template>
  <div class="min-h-screen bg-white text-gray-900 flex flex-col font-sans">
    <!-- Navbar Putih Sederhana -->
    <header class="border-b border-gray-200 bg-white sticky top-0 z-20">
      <div class="w-full mx-auto px-3 sm:px-5">
        <div class="flex justify-between items-center h-14">
          <div class="flex items-center space-x-8">
            <span class="font-bold text-base tracking-tight text-black">
              SCM INVOICE
            </span>
            <nav class="flex space-x-6 text-sm">
              <router-link
                to="/dashboard"
                class="py-4 px-1 text-gray-600 hover:text-black transition"
              >
                Dashboard
              </router-link>
              <router-link
                to="/drafts"
                class="py-4 px-1 text-gray-600 hover:text-black transition"
              >
                Draft
              </router-link>
              <router-link
                to="/invoices"
                class="py-4 px-1 text-gray-600 hover:text-black transition"
              >
                Invoice
              </router-link>
              <router-link
                to="/email-logs"
                class="py-4 px-1 text-gray-600 hover:text-black transition"
              >
                Riwayat Email
              </router-link>
            </nav>
          </div>
          <div class="text-xs text-gray-500">
            Sistem Manajemen Invoice Excel → Web
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 w-full mx-auto px-3 sm:px-5 py-4">
      <router-view />
    </main>
  </div>
</template>

<script setup>
</script>
