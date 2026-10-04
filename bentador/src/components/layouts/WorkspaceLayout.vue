<script setup lang="ts">
import {
  Squares2X2Icon,
  SunIcon,
  MoonIcon,
  ArrowRightStartOnRectangleIcon,
  Cog6ToothIcon,
} from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useProductStore } from '@/stores/products'

withDefaults(defineProps<{ storefrontPath?: string }>(), { storefrontPath: '' })
const auth = useAuthStore()
const ui = useUiStore()
const products = useProductStore()
</script>

<template>
  <div
    class="min-h-screen bg-[#F9FAFB] dark:bg-dark-bg text-gray-800 dark:text-slate-200 transition-colors duration-300"
  >
    <header class="bg-white dark:bg-dark-card border-b border-gray-200 dark:border-dark-border">
      <div
        class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-wrap items-center justify-between gap-4"
      >
        <RouterLink to="/" class="flex items-center gap-2 shrink-0" aria-label="Benta Door home">
          <span
            class="w-9 h-9 bg-teal-500 rounded-xl flex items-center justify-center text-white shadow-lg shadow-teal-500/20"
            ><Squares2X2Icon class="w-5 h-5" aria-hidden="true"
          /></span>
          <span class="text-xl font-bold text-gray-900 dark:text-white tracking-tight"
            >Benta Door</span
          >
        </RouterLink>
        <div class="flex items-center gap-2">
          <span
            class="hidden sm:block text-sm font-medium text-gray-500 dark:text-slate-400 max-w-48 truncate mr-2"
            >{{ auth.user?.name }}</span
          >
          <button
            type="button"
            :aria-label="ui.isDarkMode ? 'Use light mode' : 'Use dark mode'"
            @click="ui.toggleDarkMode()"
            class="p-3 rounded-xl text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800 transition active:scale-95"
          >
            <component :is="ui.isDarkMode ? SunIcon : MoonIcon" class="w-5 h-5" />
          </button>
          <RouterLink
            v-if="auth.user"
            to="/account"
            aria-label="Account settings"
            class="p-3 rounded-xl text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800 transition"
            ><Cog6ToothIcon class="w-5 h-5"
          /></RouterLink>
          <button
            v-if="auth.user"
            type="button"
            aria-label="Sign out"
            @click="products.toggleModal('logout', true)"
            class="p-3 rounded-xl text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800 transition active:scale-95"
          >
            <ArrowRightStartOnRectangleIcon class="w-5 h-5" />
          </button>
          <RouterLink
            v-else
            :to="{ path: '/login', query: { redirect: $route.fullPath } }"
            class="min-h-11 inline-flex items-center px-4 py-3 rounded-xl bg-teal-500 hover:bg-teal-600 text-sm font-bold text-white transition"
            >Sign in</RouterLink
          >
        </div>
        <nav
          aria-label="Main navigation"
          class="w-full flex flex-wrap items-center gap-2 text-sm font-bold"
        >
          <RouterLink
            to="/"
            exact-active-class="bg-teal-50 dark:bg-teal-500/10 text-teal-600 dark:text-teal-400"
            class="px-4 py-3 rounded-xl text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800/50 transition"
            >Home</RouterLink
          >
          <RouterLink
            v-if="auth.user"
            to="/vendors"
            exact-active-class="bg-teal-50 dark:bg-teal-500/10 text-teal-600 dark:text-teal-400"
            class="px-4 py-3 rounded-xl text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800/50 transition"
            >Your stores</RouterLink
          >
          <RouterLink
            v-if="auth.user?.is_platform_admin"
            to="/platform/vendors"
            active-class="bg-teal-50 dark:bg-teal-500/10 text-teal-600 dark:text-teal-400"
            class="px-4 py-3 rounded-xl text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800/50 transition"
            >Platform vendors</RouterLink
          >
          <RouterLink
            v-if="auth.user?.is_platform_admin"
            to="/platform/featured-products"
            active-class="bg-teal-50 dark:bg-teal-500/10 text-teal-600 dark:text-teal-400"
            class="px-4 py-3 rounded-xl text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800/50 transition"
            >Homepage products</RouterLink
          >
          <RouterLink
            v-if="storefrontPath"
            :to="storefrontPath"
            class="px-4 py-3 rounded-xl text-teal-600 dark:text-teal-400 hover:bg-gray-50 dark:hover:bg-slate-800/50 transition"
            >Back to store</RouterLink
          >
        </nav>
      </div>
    </header>
    <main class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8"><slot /></main>
  </div>
</template>
