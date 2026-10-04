<script setup lang="ts">
import ProductImageDisplay from '@/components/product/ProductImageDisplay.vue'
import type { Product } from '@/types/data-types'
import { PlusIcon, StarIcon } from '@heroicons/vue/24/solid'

defineProps<{
  product: Product
  rating: number // temporary
}>()

const emit = defineEmits<{
  (e: 'add-to-cart', product: Product): void
  (e: 'quick-view', product: Product): void
}>()
</script>

<template>
  <div
    class="bg-white dark:bg-dark-card rounded-3xl border border-gray-200 dark:border-dark-border shadow-sm hover:shadow-2xl hover:-translate-y-1 transition-all duration-300 group overflow-hidden flex flex-col cursor-pointer"
    @click="emit('quick-view', product)"
  >
    <div class="relative aspect-[4/3] overflow-hidden bg-gray-50 dark:bg-slate-900/50">
      <ProductImageDisplay :src="product.primary_image_url" :alt="product.title" />

      <!-- Badges -->
      <div class="absolute top-4 left-4 flex flex-col gap-2">
        <span
          class="px-3 py-1 bg-white/90 dark:bg-dark-card/90 backdrop-blur shadow-sm rounded-full text-[10px] font-black text-teal-600 dark:text-teal-400 uppercase tracking-widest"
        >
          {{ product.category }}
        </span>
        <span
          v-if="product.stock === 0"
          class="px-3 py-1 bg-red-500 text-white shadow-lg shadow-red-500/30 rounded-full text-[10px] font-black uppercase tracking-widest"
        >
          Out of Stock
        </span>
      </div>
    </div>

    <div class="p-6 flex-1 flex flex-col">
      <div class="mb-4">
        <h3
          class="text-base font-bold text-gray-900 dark:text-white mb-1 group-hover:text-teal-500 transition-colors"
        >
          {{ product.title }}
        </h3>
        <div class="flex items-center gap-2">
          <div class="flex text-orange-400">
            <StarIcon v-for="i in rating" :key="i" class="w-3 h-3" />
          </div>
          <span class="text-[10px] font-bold text-gray-400"
            >( {{ (rating !== 5 ? Math.random() + rating : rating).toFixed(2) }})</span
          >
        </div>
      </div>

      <div class="mt-auto flex items-center justify-between">
        <div>
          <p class="text-xs text-gray-400 font-medium">Price</p>
          <p class="text-xl font-black text-gray-900 dark:text-white">
            &#8369;{{ product.price.toLocaleString() }}
          </p>
        </div>
        <div class="text-right">
          <p
            class="text-[10px] font-black uppercase tracking-widest"
            :class="product.stock < 10 ? 'text-orange-500' : 'text-gray-400'"
          >
            {{ product.stock }} left
          </p>
          <p class="text-[9px] text-gray-400 font-bold">Fast Delivery</p>
        </div>
      </div>
      <button
        type="button"
        :disabled="product.stock === 0"
        class="mt-4 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-teal-700 px-4 text-sm font-bold text-white disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-700"
        @click.stop="emit('add-to-cart', product)"
      >
        <PlusIcon class="w-4 h-4" />
        {{ product.stock === 0 ? 'Out of stock' : 'Add to cart' }}
      </button>
    </div>
  </div>
</template>
