<script setup lang="ts">
import { ArrowPathIcon } from '@heroicons/vue/24/outline'

withDefaults(
  defineProps<{
    type?: 'button' | 'submit'
    variant?: 'primary' | 'secondary' | 'danger'
    loading?: boolean
    disabled?: boolean
  }>(),
  { type: 'button', variant: 'primary', loading: false, disabled: false },
)
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :aria-busy="loading"
    class="inline-flex min-h-11 items-center justify-center gap-2 px-5 py-3 rounded-xl text-sm font-bold transition-all active:scale-95 disabled:opacity-60 disabled:cursor-not-allowed focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
    :class="{
      'bg-teal-500 hover:bg-teal-600 text-white shadow-lg shadow-teal-500/20':
        variant === 'primary',
      'bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800':
        variant === 'secondary',
      'bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-700 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-500/20':
        variant === 'danger',
    }"
  >
    <ArrowPathIcon v-if="loading" aria-hidden="true" class="w-4 h-4 animate-spin shrink-0" />
    <slot />
  </button>
</template>
