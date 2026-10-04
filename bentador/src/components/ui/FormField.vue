<script setup lang="ts">
import type { Component } from 'vue'

defineOptions({ inheritAttrs: false })
withDefaults(defineProps<{ id: string; label: string; type?: string; icon?: Component }>(), {
  type: 'text',
})
const model = defineModel<string>({ default: '' })
</script>

<template>
  <div>
    <label
      :for="id"
      class="block text-xs font-bold text-gray-500 dark:text-slate-400 uppercase tracking-wider mb-2"
      >{{ label }}</label
    >
    <div class="relative">
      <component
        :is="icon"
        v-if="icon"
        aria-hidden="true"
        class="absolute left-3 top-3.5 w-5 h-5 text-gray-400 pointer-events-none"
      />
      <input
        :id="id"
        v-model="model"
        :type="type"
        v-bind="$attrs"
        :class="icon ? 'pl-10' : 'pl-4'"
        class="w-full min-h-11 pr-4 py-3 bg-gray-50 dark:bg-slate-800/50 border border-gray-200 dark:border-dark-border rounded-xl text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition disabled:opacity-60"
      />
    </div>
  </div>
</template>
