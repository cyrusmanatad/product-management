<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'
import type { ProductImage } from '@/types/data-types'
import ProductImageDisplay from './ProductImageDisplay.vue'
const props = defineProps<{ images: ProductImage[]; title: string }>()
const index = ref(0)
const ordered = computed(() =>
  [...props.images].sort(
    (a, b) =>
      Number(b.is_primary) - Number(a.is_primary) || a.sort_order - b.sort_order || a.id - b.id,
  ),
)
watch(
  () => props.images,
  () => {
    index.value = 0
  },
  { immediate: true },
)
const move = (amount: number) => {
  if (ordered.value.length)
    index.value = (index.value + amount + ordered.value.length) % ordered.value.length
}
const touchStart = ref(0)
const swipe = (event: TouchEvent) => {
  const end = event.changedTouches[0]?.clientX ?? touchStart.value
  if (Math.abs(end - touchStart.value) > 50) move(end < touchStart.value ? 1 : -1)
}
</script>
<template>
  <section
    aria-roledescription="carousel"
    :aria-label="`${title} images`"
    tabindex="0"
    class="space-y-3 outline-none focus-visible:ring-2 focus-visible:ring-teal-500 rounded-2xl"
    @keydown.left.prevent="move(-1)"
    @keydown.right.prevent="move(1)"
  >
    <div
      class="relative aspect-square"
      @touchstart.passive="touchStart = $event.touches[0]?.clientX ?? 0"
      @touchend.passive="swipe"
    >
      <ProductImageDisplay :src="ordered[index]?.url" :alt="`${title}, image ${index + 1}`" />
      <template v-if="ordered.length > 1">
        <button
          type="button"
          aria-label="Previous product image"
          class="absolute left-2 top-1/2 -translate-y-1/2 min-w-11 min-h-11 rounded-full shadow-sm bg-white/90 dark:bg-dark-card/90 text-gray-700 dark:text-white flex items-center justify-center"
          @click="move(-1)"
        >
          <ChevronLeftIcon class="w-5 h-5" />
        </button>
        <button
          type="button"
          aria-label="Next product image"
          class="absolute right-2 top-1/2 -translate-y-1/2 min-w-11 min-h-11 rounded-full shadow-sm bg-white/90 dark:bg-dark-card/90 text-gray-700 dark:text-white flex items-center justify-center"
          @click="move(1)"
        >
          <ChevronRightIcon class="w-5 h-5" />
        </button>
        <p
          class="absolute bottom-2 left-1/2 -translate-x-1/2 rounded-full bg-white/90 dark:bg-dark-card/90 px-3 py-1 text-xs font-bold text-gray-700 dark:text-slate-300"
          aria-live="polite"
        >
          {{ index + 1 }} / {{ ordered.length }}
        </p>
      </template>
    </div>
    <div
      v-if="ordered.length > 1"
      class="flex gap-2 overflow-x-auto pb-2"
      aria-label="Choose product image"
    >
      <button
        v-for="(image, position) in ordered"
        :key="image.id"
        type="button"
        :aria-label="`Show product image ${position + 1}`"
        :aria-pressed="position === index"
        class="w-14 h-14 shrink-0 rounded-xl overflow-hidden border-2 bg-white dark:bg-dark-card"
        :class="position === index ? 'border-teal-500' : 'border-transparent'"
        @click="index = position"
      >
        <ProductImageDisplay :src="image.url" :alt="`${title} thumbnail ${position + 1}`" />
      </button>
    </div>
  </section>
</template>
