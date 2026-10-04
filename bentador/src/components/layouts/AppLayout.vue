<script setup lang="ts">
import { useUiStore } from '@/stores/ui'
import Sidebar from '@/components/layouts/Sidebar.vue'

import { useTenantStore } from '@/stores/tenant'
import { useRouter } from 'vue-router'
import { BuildingStorefrontIcon, ChevronDownIcon, Squares2X2Icon } from '@heroicons/vue/24/outline'
const tenant = useTenantStore()
const router = useRouter()
const switchVendor = (event: Event) =>
  router.push(`/vendors/${encodeURIComponent((event.target as HTMLSelectElement).value)}/products`)
const uiStore = useUiStore()
</script>

<template>
  <div
    class="min-h-screen bg-[#F9FAFB] dark:bg-dark-bg text-gray-800 dark:text-slate-200 overflow-x-hidden font-inter transition-colors duration-300"
  >
    <!-- MOBILE SIDEBAR OVERLAY -->
    <Transition
      enter-active-class="transition-opacity duration-300"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-300"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="uiStore.isSidebarOpen"
        @click="uiStore.setSidebar(false)"
        class="fixed inset-0 bg-black/50 z-40 lg:hidden backdrop-blur-sm"
      ></div>
    </Transition>

    <Sidebar />

    <!-- MAIN CONTENT -->
    <main class="lg:ml-64 min-h-screen transition-all duration-300">
      <div
        class="px-4 sm:px-6 lg:px-8 py-4 bg-white dark:bg-dark-card border-b border-gray-100 dark:border-dark-border flex flex-wrap gap-3 items-center justify-between"
      >
        <div class="w-full sm:w-auto">
          <label
            for="active-store"
            class="block text-[10px] font-black text-gray-400 dark:text-slate-400 uppercase tracking-widest mb-2"
            >Active store</label
          >
          <div class="relative">
            <BuildingStorefrontIcon
              class="absolute left-3 top-3 w-5 h-5 text-teal-600 dark:text-teal-400 pointer-events-none"
              aria-hidden="true"
            />
            <select
              id="active-store"
              :value="tenant.activeVendorSlug"
              @change="switchVendor"
              class="w-full sm:w-64 min-h-11 appearance-none pl-10 pr-10 py-3 bg-gray-50 dark:bg-slate-800/50 border border-gray-200 dark:border-dark-border rounded-xl text-sm font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition"
            >
              <option v-for="vendor in tenant.vendors" :key="vendor.id" :value="vendor.slug">
                {{ vendor.name }}
              </option>
            </select>
            <ChevronDownIcon
              class="absolute right-3 top-3.5 w-4 h-4 text-gray-400 pointer-events-none"
              aria-hidden="true"
            />
          </div>
        </div>
        <RouterLink
          to="/vendors"
          class="inline-flex min-h-11 items-center gap-2 px-4 py-2.5 border border-gray-200 dark:border-dark-border rounded-xl text-sm font-bold text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 transition active:scale-95"
          ><Squares2X2Icon class="w-4 h-4" aria-hidden="true" />All stores</RouterLink
        >
      </div>
      <router-view :key="tenant.activeVendorId ?? 'none'" />
    </main>
  </div>
</template>
