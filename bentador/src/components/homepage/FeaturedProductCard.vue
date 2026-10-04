<script setup lang="ts">
import ProductImageDisplay from '@/components/product/ProductImageDisplay.vue'
import { ArrowRightIcon } from '@heroicons/vue/24/outline'
import { StarIcon } from '@heroicons/vue/24/solid'
import type { HomepageProduct } from '@/types/homepage'

defineProps<{ product: HomepageProduct }>()
const price = (product: HomepageProduct) =>
  product.price === null
    ? 'Unavailable'
    : new Intl.NumberFormat('en-PH', { style: 'currency', currency: product.currency }).format(
        Number(product.price),
      )
</script>

<template>
  <article
    class="bg-white dark:bg-dark-card rounded-3xl border border-gray-100 dark:border-dark-border shadow-sm overflow-hidden flex flex-col group"
  >
    <div
      class="relative aspect-[4/3] bg-gray-50 dark:bg-slate-900/50 flex items-center justify-center"
    >
      <ProductImageDisplay :src="product.primary_image_url" :alt="product.title" />
      <span
        class="absolute top-4 left-4 inline-flex items-center gap-1.5 px-3 py-1.5 bg-white dark:bg-dark-card text-teal-600 dark:text-teal-400 rounded-full text-[10px] font-black uppercase tracking-widest shadow-sm"
      >
        <StarIcon class="w-3 h-3" aria-hidden="true" />Featured
      </span>
    </div>
    <div class="p-6 flex flex-col flex-1">
      <p class="text-xs font-bold text-teal-600 dark:text-teal-400 mb-2 break-words">
        {{ product.store.name }}
      </p>
      <h3 class="text-lg font-bold text-gray-900 dark:text-white break-words mb-4">
        {{ product.title }}
      </h3>
      <p class="mt-auto text-xs text-gray-500 dark:text-slate-400">From</p>
      <p class="text-xl font-black text-gray-900 dark:text-white mt-1 mb-5">{{ price(product) }}</p>
      <RouterLink
        :to="{
          path: `/stores/${encodeURIComponent(product.store.slug)}`,
          query: { product: product.slug },
        }"
        class="inline-flex min-h-11 items-center justify-center gap-2 bg-teal-500 hover:bg-teal-600 text-white rounded-xl text-sm font-bold px-4 py-3 shadow-lg shadow-teal-500/20 transition active:scale-95"
      >
        View product <ArrowRightIcon class="w-4 h-4" aria-hidden="true" />
      </RouterLink>
      <slot />
    </div>
  </article>
</template>
