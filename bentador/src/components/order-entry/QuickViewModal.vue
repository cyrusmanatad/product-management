<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import type { Product } from '@/types/data-types'
import BaseModal from '@/components/common/BaseModal.vue'
import ProductImageCarousel from '@/components/product/ProductImageCarousel.vue'
import { XMarkIcon, CheckCircleIcon, ShoppingCartIcon } from '@heroicons/vue/24/outline'
import { StarIcon as StarIconSolid } from '@heroicons/vue/24/solid'

const props = defineProps<{
  show: boolean
  product: Product | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'add-to-cart', product: Product): void
}>()

const selectedAttributes = ref<Record<string, string>>({})

// Derive available options from variants
const availableOptions = computed(() => {
  if (!props.product?.variants) return []
  const optionsMap: Record<string, Set<string>> = {}

  props.product.variants.forEach((variant) => {
    Object.entries(variant.attributes).forEach(([key, value]) => {
      if (!optionsMap[key]) optionsMap[key] = new Set()
      optionsMap[key].add(value)
    })
  })

  return Object.entries(optionsMap).map(([name, values]) => ({
    name,
    values: Array.from(values),
  }))
})

// Initialize or reset selected attributes when product changes
watch(
  () => props.product,
  (newProduct) => {
    if (newProduct?.variants && newProduct.variants.length > 0) {
      const initialAttrs: Record<string, string> = {}
      // Set initial selection to the first variant's attributes
      const firstVariant = newProduct.variants[0]
      Object.assign(initialAttrs, firstVariant?.attributes)
      selectedAttributes.value = initialAttrs
    } else {
      selectedAttributes.value = {}
    }
  },
  { immediate: true },
)

const selectedVariant = computed(() => {
  if (!props.product?.variants) return null
  return (
    props.product.variants.find((v) =>
      Object.entries(selectedAttributes.value).every(([key, value]) => v.attributes[key] === value),
    ) || null
  )
})

const currentPrice = computed(() => {
  return selectedVariant.value?.price ?? props.product?.price ?? 0
})

const currentStock = computed(() => {
  return selectedVariant.value?.stock ?? props.product?.stock ?? 0
})

const handleAddToCart = () => {
  if (!props.product) return

  // Create a copy of the product with the selected variant's details
  const productToDispatch = {
    ...props.product,
    price: currentPrice.value,
    stock: currentStock.value,
    base_sku: selectedVariant.value?.sku ?? props.product.base_sku,
  }

  emit('add-to-cart', productToDispatch)
}
</script>

<template>
  <BaseModal :show="show" max-width="max-w-4xl" @close="emit('close')">
    <div v-if="product" class="flex flex-col md:flex-row relative overflow-y-auto">
      <button
        class="absolute top-6 right-6 z-10 p-2 bg-white/80 dark:bg-dark-card/80 backdrop-blur rounded-full text-gray-500 hover:text-gray-900 dark:hover:text-white transition active:scale-95"
        @click="emit('close')"
        type="button"
        aria-label="Close product details"
      >
        <XMarkIcon class="w-6 h-6" />
      </button>

      <div class="md:w-1/2 md:shrink-0 bg-gray-50 dark:bg-slate-900/50 p-6 pt-16 md:pt-6">
        <ProductImageCarousel
          :key="`${product.id}-${show}`"
          :images="product.images ?? []"
          :title="product.title"
        />
      </div>

      <div class="md:w-1/2 min-w-0 p-6 lg:p-8 flex flex-col custom-scrollbar">
        <div class="mb-6">
          <span
            class="px-4 py-1.5 bg-teal-50 dark:bg-teal-500/10 text-teal-600 dark:text-teal-400 rounded-full text-[10px] font-black uppercase tracking-widest mb-4 inline-block"
          >
            {{ product.category }}
          </span>
          <h2 class="text-3xl font-black text-gray-900 dark:text-white mb-2 tracking-tight">
            {{ product.title }}
          </h2>
          <div class="flex items-center gap-3">
            <div class="flex text-orange-400">
              <StarIconSolid v-for="i in 5" :key="i" class="w-4 h-4" />
            </div>
            <span class="text-sm font-bold text-gray-400">4.9 (128 Reviews)</span>
          </div>
        </div>

        <p class="text-gray-500 dark:text-slate-400 text-sm leading-relaxed mb-8">
          {{ product.description }}
        </p>

        <!-- Variants Selection derived from product.variants -->
        <div v-if="availableOptions.length > 0" class="space-y-6 mb-8">
          <div v-for="option in availableOptions" :key="option.name">
            <h4
              class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-widest mb-3"
            >
              Select {{ option.name }}
            </h4>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="value in option.values"
                :key="value"
                type="button"
                class="px-4 py-2 rounded-xl border-2 text-sm font-bold transition-all active:scale-95"
                :class="[
                  selectedAttributes[option.name] === value
                    ? 'border-teal-500 bg-teal-50 dark:bg-teal-500/10 text-teal-600 dark:text-teal-400 shadow-lg shadow-teal-500/10'
                    : 'border-gray-100 dark:border-dark-border bg-white dark:bg-slate-900 text-gray-600 dark:text-slate-400 hover:border-teal-200',
                ]"
                @click="selectedAttributes[option.name] = value"
              >
                {{ value }}
              </button>
            </div>
          </div>
        </div>

        <div class="space-y-4 mb-10">
          <h4 class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-widest">
            Key Features
          </h4>
          <div class="grid grid-cols-1 gap-3">
            <div
              v-for="spec in ['Premium Quality', 'Fast Shipping', 'Secure Payment']"
              :key="spec"
              class="flex items-center gap-3 text-sm text-gray-600 dark:text-slate-400"
            >
              <CheckCircleIcon class="w-5 h-5 text-teal-500" />
              <span>{{ spec }}</span>
            </div>
          </div>
        </div>

        <div
          class="mt-auto pt-8 border-t dark:border-dark-border flex flex-wrap gap-4 items-center justify-between"
        >
          <div>
            <p class="text-xs text-gray-400 font-bold mb-1">
              {{ selectedVariant ? 'Variant Price' : 'Price' }}
            </p>
            <p class="text-3xl font-black text-gray-900 dark:text-white">
              &#8369;{{ currentPrice.toLocaleString() }}
            </p>
            <p
              v-if="currentStock <= 5"
              class="text-[10px] text-red-500 font-black uppercase tracking-widest mt-1"
            >
              Only {{ currentStock }} left in stock!
            </p>
          </div>
          <button
            type="button"
            :disabled="currentStock === 0"
            class="px-5 py-4 bg-teal-500 text-white rounded-2xl font-black text-sm transition-all shadow-xl flex items-center gap-3"
            :class="
              currentStock === 0
                ? 'opacity-50 cursor-not-allowed'
                : 'hover:bg-teal-600 shadow-teal-500/30 active:scale-95'
            "
            @click="handleAddToCart"
          >
            <ShoppingCartIcon class="w-5 h-5" /> Add to Cart
          </button>
        </div>
      </div>
    </div>
  </BaseModal>
</template>
