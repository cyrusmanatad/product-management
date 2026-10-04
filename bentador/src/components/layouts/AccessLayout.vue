<script setup lang="ts">
import { Squares2X2Icon, SunIcon, MoonIcon } from '@heroicons/vue/24/outline'
import { useUiStore } from '@/stores/ui'

defineProps<{ title: string; description: string }>()
const ui = useUiStore()
</script>

<template>
  <main
    class="min-h-screen bg-[#F9FAFB] dark:bg-dark-bg text-gray-800 dark:text-slate-200 flex flex-col items-center justify-center px-4 py-24 transition-colors duration-300"
  >
    <button
      type="button"
      :aria-label="ui.isDarkMode ? 'Use light mode' : 'Use dark mode'"
      @click="ui.toggleDarkMode()"
      class="absolute top-6 right-6 p-3 bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border rounded-2xl shadow-sm hover:bg-gray-50 dark:hover:bg-slate-800 transition active:scale-95"
    >
      <component
        :is="ui.isDarkMode ? SunIcon : MoonIcon"
        class="w-5 h-5 text-teal-600 dark:text-teal-400"
      />
    </button>
    <div class="w-full max-w-md">
      <RouterLink to="/login" class="flex flex-col items-center mb-8">
        <div
          class="w-16 h-16 bg-teal-500 rounded-2xl flex items-center justify-center text-white shadow-xl shadow-teal-500/30 mb-4"
        >
          <Squares2X2Icon class="w-10 h-10" aria-hidden="true" />
        </div>
        <span class="text-3xl font-black text-gray-900 dark:text-white tracking-tight"
          >Benta Door</span
        >
      </RouterLink>
      <section
        class="relative overflow-hidden bg-white dark:bg-dark-card border border-gray-100 dark:border-dark-border p-6 sm:p-8 rounded-[2.5rem] shadow-2xl shadow-slate-200/50 dark:shadow-none"
      >
        <div
          aria-hidden="true"
          class="absolute top-0 right-0 w-32 h-32 bg-teal-500/5 dark:bg-teal-500/10 rounded-full -translate-y-16 translate-x-16"
        ></div>
        <div class="relative">
          <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ title }}</h1>
          <p class="text-sm text-gray-500 dark:text-slate-400 mt-2 mb-6 leading-relaxed">
            {{ description }}
          </p>
          <slot />
        </div>
      </section>
      <div class="mt-6 text-center text-sm text-gray-500 dark:text-slate-400">
        <slot name="footer" />
      </div>
    </div>
  </main>
</template>
