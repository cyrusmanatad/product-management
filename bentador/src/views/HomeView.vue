<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  BuildingStorefrontIcon,
  ArrowRightIcon,
  MagnifyingGlassIcon,
  StarIcon,
} from '@heroicons/vue/24/outline'
import axios from '@/utils/axios'
import WorkspaceLayout from '@/components/layouts/WorkspaceLayout.vue'
import FeaturedProductCard from '@/components/homepage/FeaturedProductCard.vue'
import FormField from '@/components/ui/FormField.vue'
import ActionButton from '@/components/ui/ActionButton.vue'
import InlineMessage from '@/components/ui/InlineMessage.vue'
import TableSpinner from '@/components/ui/TableSpinner.vue'
import Pagination from '@/components/common/Pagination.vue'
import type { HomepageProduct, PageResult } from '@/types/homepage'
import type { Vendor } from '@/stores/tenant'

const featured = ref<PageResult<HomepageProduct> | null>(null)
const stores = ref<PageResult<Vendor> | null>(null)
const featuredLoading = ref(true),
  storesLoading = ref(true)
const featuredError = ref(''),
  storesError = ref(''),
  search = ref('')
let storeRequest = 0
async function loadFeatured(page = 1) {
  featuredLoading.value = true
  featuredError.value = ''
  try {
    const { data } = await axios.get('/api/v1/homepage/featured-products', { params: { page } })
    featured.value = data
  } catch {
    featuredError.value = 'Featured products could not be loaded. Please try again.'
  } finally {
    featuredLoading.value = false
  }
}
async function loadStores(page = 1) {
  const request = ++storeRequest
  storesLoading.value = true
  storesError.value = ''
  try {
    const { data } = await axios.get('/api/v1/homepage/stores', {
      params: { page, search: search.value },
    })
    if (request === storeRequest) stores.value = data
  } catch {
    if (request === storeRequest)
      storesError.value = 'Stores could not be loaded. Please try again.'
  } finally {
    if (request === storeRequest) storesLoading.value = false
  }
}
onMounted(() => Promise.allSettled([loadFeatured(), loadStores()]))
</script>

<template>
  <WorkspaceLayout>
    <section
      class="relative overflow-hidden bg-gradient-to-br from-teal-500 to-teal-700 rounded-[2.5rem] p-6 sm:p-10 lg:p-12 text-white shadow-xl shadow-teal-500/20 mb-10"
    >
      <div class="relative z-10 max-w-2xl">
        <span
          class="inline-flex px-4 py-1.5 bg-white/20 rounded-full text-[10px] font-black uppercase tracking-[0.2em] mb-5"
          >Welcome to Benta Door</span
        >
        <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight leading-tight mb-4">
          Discover products.<br />Find your next favorite store.
        </h1>
        <p class="text-sm sm:text-base text-white/90 max-w-xl mb-7">
          Explore featured finds from independent stores, then shop directly with the store you
          choose.
        </p>
        <a
          href="#stores"
          class="inline-flex min-h-11 items-center gap-2 bg-white text-teal-700 rounded-xl px-5 py-3 text-sm font-bold shadow-sm hover:bg-teal-50 transition active:scale-95"
          >Explore stores <ArrowRightIcon class="w-4 h-4" aria-hidden="true"
        /></a>
      </div>
      <BuildingStorefrontIcon
        class="absolute right-6 bottom-0 w-64 h-64 text-white/10 hidden lg:block"
        aria-hidden="true"
      />
    </section>

    <section aria-labelledby="featured-heading" class="mb-12">
      <div class="flex items-center gap-3 mb-6">
        <div
          class="w-11 h-11 rounded-xl bg-teal-50 dark:bg-teal-500/10 flex items-center justify-center text-teal-600 dark:text-teal-400"
        >
          <StarIcon class="w-5 h-5" aria-hidden="true" />
        </div>
        <div>
          <h2 id="featured-heading" class="text-xl font-bold text-gray-900 dark:text-white">
            Featured products
          </h2>
          <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">
            Selected finds from across our stores.
          </p>
        </div>
      </div>
      <div v-if="featuredLoading" class="relative min-h-48" aria-busy="true">
        <TableSpinner text="Loading featured products..." />
      </div>
      <InlineMessage v-else-if="featuredError" kind="error"
        >{{ featuredError
        }}<ActionButton variant="secondary" class="ml-3" @click="loadFeatured()"
          >Retry</ActionButton
        ></InlineMessage
      >
      <template v-else-if="featured?.data.length">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
          <FeaturedProductCard
            v-for="product in featured.data"
            :key="product.feature_id ?? product.id"
            :product="product"
          />
        </div>
        <Pagination
          v-if="featured.meta.last_page > 1"
          :meta="featured.meta"
          @page-change="loadFeatured"
          class="mt-6 rounded-2xl"
        />
      </template>
      <div
        v-else
        class="bg-white dark:bg-dark-card border border-gray-100 dark:border-dark-border rounded-3xl p-8 text-center"
      >
        <h3 class="font-bold text-gray-900 dark:text-white">More featured finds are on the way</h3>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-2">
          Explore the stores below to discover their products.
        </p>
      </div>
    </section>

    <section id="stores" aria-labelledby="stores-heading" class="scroll-mt-6">
      <div class="mb-6">
        <h2 id="stores-heading" class="text-xl font-bold text-gray-900 dark:text-white">
          Explore stores
        </h2>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">
          Browse independent storefronts and their catalogs.
        </p>
      </div>
      <form
        @submit.prevent="loadStores()"
        class="flex flex-col sm:flex-row sm:items-end gap-3 mb-6 max-w-xl"
      >
        <FormField
          id="store-search"
          v-model="search"
          label="Find a store"
          :icon="MagnifyingGlassIcon"
          placeholder="Search by store name"
          maxlength="100"
          class="flex-1"
        />
        <ActionButton type="submit" :loading="storesLoading">Search stores</ActionButton>
      </form>
      <div v-if="storesLoading" class="relative min-h-48" aria-busy="true">
        <TableSpinner text="Loading stores..." />
      </div>
      <InlineMessage v-else-if="storesError" kind="error"
        >{{ storesError
        }}<ActionButton variant="secondary" class="ml-3" @click="loadStores()"
          >Retry</ActionButton
        ></InlineMessage
      >
      <template v-else-if="stores?.data.length">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <article
            v-for="store in stores.data"
            :key="store.slug"
            class="bg-white dark:bg-dark-card border border-gray-100 dark:border-dark-border rounded-3xl shadow-sm p-6"
          >
            <div
              class="w-12 h-12 rounded-2xl bg-teal-50 dark:bg-teal-500/10 text-teal-600 dark:text-teal-400 flex items-center justify-center mb-5"
            >
              <BuildingStorefrontIcon class="w-6 h-6" aria-hidden="true" />
            </div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white break-words">
              {{ store.name }}
            </h3>
            <p class="text-xs text-gray-500 dark:text-slate-400 break-all mt-2 mb-5">
              /stores/{{ store.slug }}
            </p>
            <RouterLink
              :to="`/stores/${encodeURIComponent(store.slug)}`"
              class="inline-flex min-h-11 items-center gap-2 text-sm font-bold text-teal-600 dark:text-teal-400 hover:text-teal-700 transition"
              >Visit store <ArrowRightIcon class="w-4 h-4" aria-hidden="true"
            /></RouterLink>
          </article>
        </div>
        <Pagination
          v-if="stores.meta.last_page > 1"
          :meta="stores.meta"
          @page-change="loadStores"
          class="mt-6 rounded-2xl"
        />
      </template>
      <div
        v-else
        class="bg-white dark:bg-dark-card border border-gray-100 dark:border-dark-border rounded-3xl p-8 text-center"
      >
        <h3 class="font-bold text-gray-900 dark:text-white">
          {{ search ? 'No matching stores' : 'Stores are coming soon' }}
        </h3>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-2">
          {{ search ? 'Try a different store name.' : 'Check back soon to discover new stores.' }}
        </p>
      </div>
    </section>
  </WorkspaceLayout>
</template>
