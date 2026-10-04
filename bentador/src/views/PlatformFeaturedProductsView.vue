<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { MagnifyingGlassIcon, StarIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import axios from '@/utils/axios'
import WorkspaceLayout from '@/components/layouts/WorkspaceLayout.vue'
import AppHeader from '@/components/layouts/AppHeader.vue'
import FormField from '@/components/ui/FormField.vue'
import ActionButton from '@/components/ui/ActionButton.vue'
import InlineMessage from '@/components/ui/InlineMessage.vue'
import TableSpinner from '@/components/ui/TableSpinner.vue'
import Pagination from '@/components/common/Pagination.vue'
import { useToastStore } from '@/stores/toast'
import type { HomepageProduct, PageResult } from '@/types/homepage'
import type { Vendor } from '@/stores/tenant'

const toast = useToastStore()
const stores = ref<PageResult<Vendor> | null>(null)
const candidates = ref<PageResult<HomepageProduct> | null>(null)
const featured = ref<PageResult<HomepageProduct> | null>(null)
const selectedStore = ref(''),
  storeSearch = ref(''),
  productSearch = ref('')
const error = ref(''),
  busy = ref('')
const storesLoading = ref(true),
  candidatesLoading = ref(false),
  featuredLoading = ref(true)
let candidateRequest = 0
async function loadStores(page = 1) {
  storesLoading.value = true
  try {
    const { data } = await axios.get('/api/v1/homepage/stores', {
      params: { page, search: storeSearch.value },
    })
    stores.value = data
  } catch {
    error.value = 'Stores could not be loaded. Please try again.'
  } finally {
    storesLoading.value = false
  }
}
async function loadFeatured(page = 1) {
  featuredLoading.value = true
  try {
    const { data } = await axios.get('/api/v1/platform/featured-products', { params: { page } })
    featured.value = data
  } catch {
    error.value = 'Featured selections could not be loaded. Please try again.'
  } finally {
    featuredLoading.value = false
  }
}
async function loadCandidates(page = 1) {
  const request = ++candidateRequest
  candidates.value = null
  if (!selectedStore.value) {
    candidatesLoading.value = false
    return
  }
  candidatesLoading.value = true
  error.value = ''
  try {
    const { data } = await axios.get(
      `/api/v1/platform/vendors/${encodeURIComponent(selectedStore.value)}/feature-candidates`,
      { params: { page, search: productSearch.value } },
    )
    if (request === candidateRequest) candidates.value = data
  } catch {
    if (request === candidateRequest)
      error.value = 'Products could not be loaded. Please try again.'
  } finally {
    if (request === candidateRequest) candidatesLoading.value = false
  }
}
async function chooseStore(slug: string) {
  selectedStore.value = slug
  productSearch.value = ''
  await loadCandidates()
}
async function changeFeature(product: HomepageProduct, remove: boolean) {
  error.value = ''
  busy.value = `${product.store.slug}/${product.id}`
  const path = `/api/v1/platform/vendors/${encodeURIComponent(product.store.slug)}/featured-products`
  try {
    if (remove) await axios.delete(`${path}/${product.id}`)
    else await axios.post(path, { product_id: product.id })
    toast.addToast(
      remove ? 'Product removed from the homepage.' : 'Product featured on the homepage.',
      'success',
    )
    await Promise.allSettled([
      loadFeatured(),
      loadCandidates(candidates.value?.meta.current_page ?? 1),
    ])
  } catch (cause) {
    error.value = axios.isAxiosError(cause)
      ? (cause.response?.data?.message ?? 'Selection could not be saved.')
      : 'Selection could not be saved. Please try again.'
  } finally {
    busy.value = ''
  }
}
onMounted(() => Promise.allSettled([loadStores(), loadFeatured()]))
</script>

<template>
  <WorkspaceLayout>
    <AppHeader
      title="Homepage products"
      description="Select published products from any active store to feature on the homepage."
      :breadcrumb="[{ label: 'Platform' }, { label: 'Homepage products' }]"
      :show-menu-button="false"
    />
    <InlineMessage v-if="error" kind="error" class="mb-6">{{ error }}</InlineMessage>
    <div class="grid xl:grid-cols-2 gap-6 items-start">
      <section
        class="bg-white dark:bg-dark-card border border-gray-100 dark:border-dark-border rounded-3xl shadow-sm overflow-hidden"
      >
        <div
          class="p-6 bg-gray-50/50 dark:bg-slate-800/20 border-b border-gray-100 dark:border-dark-border"
        >
          <h2 class="text-lg font-bold text-gray-900 dark:text-white">Find a product</h2>
          <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">
            Choose a store, then select a product.
          </p>
        </div>
        <div class="p-6 space-y-5">
          <form @submit.prevent="loadStores()" class="flex flex-col sm:flex-row sm:items-end gap-3">
            <FormField
              id="feature-store-search"
              v-model="storeSearch"
              label="Find a store"
              placeholder="Store name"
              maxlength="100"
              :icon="MagnifyingGlassIcon"
              class="flex-1"
            />
            <ActionButton type="submit" variant="secondary" :loading="storesLoading"
              >Search stores</ActionButton
            >
          </form>
          <div v-if="storesLoading" class="relative min-h-32">
            <TableSpinner text="Loading stores..." />
          </div>
          <template v-else>
            <div class="flex flex-wrap gap-2" aria-label="Choose a store">
              <button
                v-for="store in stores?.data"
                :key="store.slug"
                type="button"
                :aria-pressed="selectedStore === store.slug"
                :disabled="!!busy"
                @click="chooseStore(store.slug)"
                class="min-h-11 px-4 py-3 rounded-xl text-sm font-bold border transition break-words disabled:opacity-50"
                :class="
                  selectedStore === store.slug
                    ? 'bg-teal-50 dark:bg-teal-500/10 text-teal-600 dark:text-teal-400 border-teal-200 dark:border-teal-500/30'
                    : 'border-gray-200 dark:border-dark-border text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800'
                "
              >
                {{ store.name }}
              </button>
            </div>
            <p v-if="!stores?.data.length" class="text-sm text-gray-500 dark:text-slate-400">
              No active stores match your search.
            </p>
            <Pagination
              v-if="stores && stores.meta.last_page > 1"
              :meta="stores.meta"
              @page-change="loadStores"
            />
          </template>
          <template v-if="selectedStore">
            <form
              @submit.prevent="loadCandidates()"
              class="flex flex-col sm:flex-row sm:items-end gap-3"
            >
              <FormField
                id="feature-product-search"
                v-model="productSearch"
                label="Find a product"
                placeholder="Product title"
                maxlength="100"
                :icon="MagnifyingGlassIcon"
                class="flex-1"
                :disabled="!!busy"
              />
              <ActionButton
                type="submit"
                variant="secondary"
                :loading="candidatesLoading"
                :disabled="!!busy"
                >Search products</ActionButton
              >
            </form>
            <div v-if="candidatesLoading" class="relative min-h-32">
              <TableSpinner text="Loading products..." />
            </div>
            <div v-else class="space-y-3">
              <article
                v-for="product in candidates?.data"
                :key="product.id"
                class="p-4 border border-gray-100 dark:border-dark-border rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-3"
              >
                <div class="min-w-0">
                  <h3 class="font-bold text-gray-900 dark:text-white break-words">
                    {{ product.title }}
                  </h3>
                  <p class="text-xs text-gray-500 dark:text-slate-400 mt-1 break-words">
                    {{ product.store.name }}
                  </p>
                </div>
                <ActionButton
                  :variant="product.feature_id ? 'secondary' : 'primary'"
                  :disabled="!!busy || !!product.feature_id"
                  :loading="busy === `${product.store.slug}/${product.id}`"
                  @click="changeFeature(product, false)"
                  ><PlusIcon v-if="!product.feature_id" class="w-4 h-4" aria-hidden="true" />{{
                    product.feature_id ? 'Already featured' : 'Feature product'
                  }}</ActionButton
                >
              </article>
              <p
                v-if="candidates && !candidates.data.length"
                class="text-sm text-gray-500 dark:text-slate-400 py-4"
              >
                No eligible products match. Products must be published and have an active variant in
                the store currency.
              </p>
              <Pagination
                v-if="candidates && candidates.meta.last_page > 1"
                :meta="candidates.meta"
                @page-change="loadCandidates"
              />
            </div>
          </template>
        </div>
      </section>
      <section
        class="bg-white dark:bg-dark-card border border-gray-100 dark:border-dark-border rounded-3xl shadow-sm overflow-hidden"
      >
        <div
          class="p-6 bg-gray-50/50 dark:bg-slate-800/20 border-b border-gray-100 dark:border-dark-border flex items-center gap-3"
        >
          <StarIcon class="w-6 h-6 text-teal-600 dark:text-teal-400" aria-hidden="true" />
          <div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Featured selections</h2>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">
              Newest selections appear first.
            </p>
          </div>
        </div>
        <div v-if="featuredLoading" class="relative min-h-48">
          <TableSpinner text="Loading selections..." />
        </div>
        <div v-else class="p-6 space-y-3">
          <article
            v-for="product in featured?.data"
            :key="product.feature_id ?? product.id"
            class="p-4 border border-gray-100 dark:border-dark-border rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-3"
          >
            <div class="min-w-0">
              <h3 class="font-bold text-gray-900 dark:text-white break-words">
                {{ product.title }}
              </h3>
              <p class="text-xs text-gray-500 dark:text-slate-400 mt-1 break-words">
                {{ product.store.name }}
              </p>
              <p
                class="text-xs font-bold mt-2"
                :class="
                  product.visible
                    ? 'text-green-600 dark:text-green-400'
                    : 'text-orange-600 dark:text-orange-400'
                "
              >
                {{
                  product.visible
                    ? 'Visible on homepage'
                    : 'Hidden — product or store is unavailable'
                }}
              </p>
            </div>
            <ActionButton
              variant="danger"
              :disabled="!!busy"
              :loading="busy === `${product.store.slug}/${product.id}`"
              @click="changeFeature(product, true)"
              ><TrashIcon class="w-4 h-4" aria-hidden="true" />Remove</ActionButton
            >
          </article>
          <p
            v-if="!featured?.data.length"
            class="text-sm text-gray-500 dark:text-slate-400 py-6 text-center"
          >
            No featured products yet. Choose a product to get started.
          </p>
          <Pagination
            v-if="featured && featured.meta.last_page > 1"
            :meta="featured.meta"
            @page-change="loadFeatured"
          />
        </div>
      </section>
    </div>
  </WorkspaceLayout>
</template>
