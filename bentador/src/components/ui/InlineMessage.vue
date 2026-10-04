<script setup lang="ts">
import {
  CheckCircleIcon,
  ExclamationCircleIcon,
  InformationCircleIcon,
} from '@heroicons/vue/24/outline'
withDefaults(defineProps<{ kind?: 'success' | 'error' | 'info' }>(), { kind: 'info' })
</script>

<template>
  <div
    :role="kind === 'error' ? 'alert' : 'status'"
    class="flex items-start gap-3 p-4 rounded-2xl border text-sm leading-relaxed"
    :class="{
      'bg-red-50 dark:bg-red-500/10 border-red-100 dark:border-red-500/20 text-red-700 dark:text-red-400':
        kind === 'error',
      'bg-green-50 dark:bg-green-500/10 border-green-100 dark:border-green-500/20 text-green-700 dark:text-green-400':
        kind === 'success',
      'bg-gray-50 dark:bg-slate-800/50 border-gray-200 dark:border-dark-border text-gray-600 dark:text-slate-300':
        kind === 'info',
    }"
  >
    <component
      :is="
        kind === 'error'
          ? ExclamationCircleIcon
          : kind === 'success'
            ? CheckCircleIcon
            : InformationCircleIcon
      "
      class="w-5 h-5 shrink-0 mt-0.5"
      aria-hidden="true"
    />
    <div class="min-w-0 flex-1"><slot /></div>
  </div>
</template>
