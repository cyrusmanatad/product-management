<script setup lang="ts">
import { ref, watch } from 'vue'
import { PhotoIcon } from '@heroicons/vue/24/outline'
import axios from '@/utils/axios'
const props = defineProps<{ src?: string | null; alt: string }>()
const failed = ref(false)
watch(
  () => props.src,
  () => {
    failed.value = false
  },
)
</script>
<template>
  <img
    v-if="src && !failed"
    :src="axios.getUri({ url: src })"
    :alt="alt"
    class="w-full h-full object-contain"
    loading="lazy"
    @error="failed = true"
  />
  <div v-else class="w-full h-full flex items-center justify-center">
    <PhotoIcon class="w-20 h-20 text-gray-300 dark:text-slate-700" aria-hidden="true" /><span
      class="sr-only"
      >No image available for {{ alt }}</span
    >
  </div>
</template>
