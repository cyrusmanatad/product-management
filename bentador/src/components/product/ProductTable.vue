<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useProductStore } from '@/stores/products'
import {
  MagnifyingGlassIcon,
  ChevronDownIcon,
  CalendarIcon,
  MusicalNoteIcon,
  PencilSquareIcon,
  TrashIcon,
  FunnelIcon,
} from '@heroicons/vue/24/outline'
import Pagination from '../common/Pagination.vue'
import { storeToRefs } from 'pinia'
import { useDebounceFn } from '@vueuse/core'
import { getProductStatus, ProductStatus, ProductStatusLabel } from '@/types/enum'
import { useAuthStore } from '@/stores/auth'
import type { Status } from '@/types/data-types'
import TableSpinner from '../ui/TableSpinner.vue'

const productStore = useProductStore()
const authStore = useAuthStore()

const { search } = storeToRefs(productStore)

const selectedStatus = ref<Status<ProductStatus, ProductStatusLabel> | null>({
  code: ProductStatus.ALL,
  label: ProductStatusLabel.ALL,
})

const activeFilter = ref<'status' | 'dateRange' | 'filter' | null>(null)

const statusOpen = computed(() => activeFilter.value === 'status')
const dateRangeOpen = computed(() => activeFilter.value === 'dateRange')
const filterOpen = computed(() => activeFilter.value === 'filter')

const dateFrom = ref('')
const dateTo = ref('')
const selectedCategories = ref<string[]>([])

const debouncedFetch = useDebounceFn(() => {
  productStore.fetchProducts(search.value, 1, {
    category: selectedCategories.value,
    status: selectedStatus.value?.code,
  })
}, 400)

const selectStatus = (status: string) => {
  selectedStatus.value = getProductStatus(status)
  activeFilter.value = null
  productStore.fetchProducts(search.value, 1, { category: selectedCategories.value, status })
}

const handleApplyRange = () => {
  if (dateFrom.value != '' && dateTo.value != '') activeFilter.value = null
}

const handleApplyCategoryFilter = () => {
  activeFilter.value = null
  productStore.fetchProducts(search.value, 1, {
    category: selectedCategories.value,
    status: selectedStatus.value?.code,
  })
}

const handleResetFilter = () => {
  selectedCategories.value = []
}

const clearDateRange = () => {
  dateFrom.value = ''
  dateTo.value = ''
}

const onPageChange = (page: number) => {
  productStore.fetchProducts(search.value, page)
}

watch(search, () => {
  // productStore.fetchProducts(newVal, 1) // reset to page 1 on new search
  debouncedFetch()
})

onMounted(async () => {
  await Promise.all([productStore.fetchProducts(), productStore.fetchCategories()])
})
</script>

<template>
  <div
    class="bg-white dark:bg-dark-card rounded-2xl border border-gray-200 dark:border-dark-border shadow-sm overflow-hidden"
  >
    <!-- Filter Bar -->
    <div
      class="p-4 border-b border-gray-100 dark:border-dark-border flex flex-col lg:flex-row gap-4 justify-between bg-gray-50/30 dark:bg-slate-800/20"
    >
      <div class="relative max-w-md w-full">
        <MagnifyingGlassIcon class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" />
        <input
          type="text"
          v-model="productStore.search"
          placeholder="Search by name, SKU Code..."
          class="w-full pl-10 pr-4 py-2 bg-white dark:bg-slate-900 border border-gray-200 dark:border-dark-border rounded-xl text-sm dark:text-white focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition"
        />
      </div>
      <div class="flex flex-wrap gap-2 pb-1 lg:pb-0">
        <!-- Category Filter -->
        <div class="relative">
          <!-- Button -->
          <button
            @click.stop="activeFilter = 'filter'"
            type="button"
            class="p-2.5 border border-gray-200 dark:border-dark-border rounded-xl text-xs font-bold bg-white dark:bg-slate-900 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 transition flex items-center gap-2 group"
          >
            <FunnelIcon
              class="w-3 h-3 text-teal-500 dark:text-teal-400 group-hover:scale-110 transition-transform"
            />

            <!-- Badge -->
            <span
              v-if="selectedCategories.length > 0"
              class="absolute -top-1 -right-1 w-4 h-4 bg-teal-500 text-white text-[9px] flex items-center justify-center rounded-full border-2 border-white dark:border-dark-bg"
            >
              {{ selectedCategories.length }}
            </span>
          </button>

          <!-- Dropdown -->
          <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
          >
            <div
              v-if="filterOpen"
              v-outside-click="() => (activeFilter = null)"
              class="absolute left-0 mt-2 w-56 bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border shadow-xl rounded-2xl z-[75] overflow-hidden"
            >
              <div
                class="px-4 py-3 border-b dark:border-dark-border bg-gray-50/50 dark:bg-slate-800/30"
              >
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">
                  Filter Category
                </p>
              </div>

              <!-- Options -->
              <div class="p-2 max-h-60 overflow-y-auto custom-scrollbar space-y-0.5">
                <label
                  v-for="category in productStore.categories"
                  :key="category.id"
                  class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-teal-50 dark:hover:bg-teal-500/10 cursor-pointer group transition-colors"
                >
                  <div class="relative flex items-center">
                    <input
                      type="checkbox"
                      :value="category.id"
                      v-model="selectedCategories"
                      class="peer h-5 w-5 cursor-pointer appearance-none rounded-md border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 checked:bg-teal-500 checked:border-teal-500 transition-all"
                    />
                    <span
                      class="absolute text-white opacity-0 peer-checked:opacity-100 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 pointer-events-none"
                    >
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-3.5 w-3.5"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        stroke="currentColor"
                        stroke-width="1"
                      >
                        <path
                          fill-rule="evenodd"
                          d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                          clip-rule="evenodd"
                        />
                      </svg>
                    </span>
                  </div>

                  <span
                    class="text-sm text-gray-600 dark:text-slate-400 group-hover:text-teal-600 dark:group-hover:text-teal-400 transition"
                  >
                    {{ category.name }}
                  </span>
                </label>
              </div>

              <!-- Footer -->
              <div
                class="p-3 border-t dark:border-dark-border flex items-center justify-between bg-gray-50/30 dark:bg-slate-800/20"
              >
                <button
                  @click="handleResetFilter"
                  type="button"
                  class="text-[10px] font-bold text-gray-400 hover:text-red-500 transition"
                >
                  Reset
                </button>

                <button
                  @click="handleApplyCategoryFilter"
                  type="button"
                  class="px-3 py-1.5 bg-teal-500 text-white text-[10px] font-black rounded-lg hover:bg-teal-600 transition"
                >
                  Apply
                </button>
              </div>
            </div>
          </Transition>
        </div>

        <!-- Status Dropdown -->
        <div class="relative">
          <button
            @click.stop="activeFilter = 'status'"
            type="button"
            class="whitespace-nowrap px-4 py-2 border border-gray-200 dark:border-dark-border rounded-xl text-xs font-bold bg-white dark:bg-slate-900 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 transition flex items-center gap-2"
          >
            <span>{{ selectedStatus?.label }}</span>
            <ChevronDownIcon
              class="w-3 h-3 text-gray-400 transition-transform"
              :class="statusOpen && 'rotate-180'"
            />
          </button>
          <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
          >
            <div
              v-if="statusOpen"
              v-outside-click="() => (activeFilter = null)"
              class="absolute left-0 mt-2 w-44 bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border shadow-xl rounded-xl z-[70] py-1"
            >
              <button
                v-for="status in productStore.statuses"
                :key="status.code"
                @click="selectStatus(status.code)"
                class="w-full text-left px-4 py-2 text-sm text-gray-600 dark:text-slate-400 hover:bg-teal-50 dark:hover:bg-teal-500/10 hover:text-teal-600 dark:hover:text-teal-400 transition"
                type="button"
              >
                {{ status.label }}
              </button>
            </div>
          </Transition>
        </div>

        <!-- Date Range Dropdown -->
        <div class="relative">
          <button
            @click.stop="activeFilter = 'dateRange'"
            type="button"
            class="whitespace-nowrap px-4 py-2 border border-gray-200 dark:border-dark-border rounded-xl text-xs font-bold bg-white dark:bg-slate-900 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 transition flex items-center gap-2"
          >
            <CalendarIcon class="w-3 h-3 text-teal-500 dark:text-teal-400" />
            <span>{{ dateFrom && dateTo ? dateFrom + ' - ' + dateTo : 'Select Date Range' }}</span>
            <ChevronDownIcon
              class="w-3 h-3 text-gray-400 transition-transform"
              :class="dateRangeOpen && 'rotate-180'"
            />
          </button>
          <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
          >
            <div
              v-if="dateRangeOpen"
              v-outside-click="() => (activeFilter = null)"
              class="absolute right-0 mt-2 p-4 bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border shadow-2xl rounded-2xl z-[70] min-w-[280px]"
            >
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label
                    class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1"
                    >From</label
                  >
                  <input
                    type="date"
                    v-model="dateFrom"
                    class="w-full px-2 py-2 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-dark-border rounded-lg text-xs dark:text-white focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition font-medium"
                  />
                </div>
                <div>
                  <label
                    class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1"
                    >To</label
                  >
                  <input
                    type="date"
                    v-model="dateTo"
                    class="w-full px-2 py-2 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-dark-border rounded-lg text-xs dark:text-white focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition font-medium"
                  />
                </div>
              </div>
              <div
                class="mt-4 flex justify-between items-center border-t dark:border-dark-border pt-3"
              >
                <button
                  @click="clearDateRange"
                  type="button"
                  class="text-[10px] font-bold text-red-500 dark:text-red-400 hover:text-red-700 transition"
                >
                  Clear
                </button>
                <button
                  @click="handleApplyRange"
                  type="button"
                  class="px-4 py-2 bg-teal-500 text-white text-[10px] font-black rounded-lg hover:bg-teal-600 transition shadow-lg shadow-teal-500/20"
                >
                  Apply Range
                </button>
              </div>
            </div>
          </Transition>
        </div>
      </div>
    </div>

    <!-- Wrapper for the overlay positioning -->
    <div class="relative">
      <TableSpinner v-if="productStore.isLoading" :text="`Updating products...`" />

      <!-- Scrollable Table -->
      <div class="overflow-x-auto custom-scrollbar">
        <table class="w-full text-left min-w-[900px]">
          <thead>
            <tr
              class="bg-gray-50/50 dark:bg-slate-800/20 text-[11px] uppercase tracking-widest text-gray-400 dark:text-slate-500 font-bold border-b border-gray-100 dark:border-dark-border"
            >
              <th class="px-6 py-4">Product Name</th>
              <th class="px-6 py-4">SKU & Created</th>
              <th class="px-6 py-4">Price / Sale</th>
              <th class="px-6 py-4 text-center">Stock</th>
              <th class="px-6 py-4">Status</th>
              <th class="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-dark-border">
            <tr
              v-for="product in productStore.products"
              :key="product.id"
              class="hover:bg-gray-50/80 dark:hover:bg-slate-800/30 transition-colors group"
            >
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div
                    class="w-12 h-12 bg-gray-100 dark:bg-slate-800 rounded-xl flex items-center justify-center text-gray-400 dark:text-slate-500 group-hover:bg-white dark:group-hover:bg-slate-700 transition shadow-inner"
                  >
                    <MusicalNoteIcon class="w-6 h-6" />
                  </div>
                  <div>
                    <p class="text-sm font-bold text-gray-900 dark:text-white leading-tight">
                      {{ product.title }}
                    </p>
                    <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-0.5">
                      {{ product.category }}
                    </p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4">
                <p class="text-xs font-bold text-gray-900 dark:text-slate-300 font-mono">
                  {{ product.base_sku }}
                </p>
                <p class="text-[11px] text-gray-400 dark:text-slate-500">{{ product.createdAt }}</p>
              </td>
              <td class="px-6 py-4">
                <p class="text-sm font-black text-gray-900 dark:text-white">
                  &#8369;{{ product.price_humanize }}
                </p>
                <p class="text-[10px] text-teal-600 dark:text-teal-400 font-bold">
                  Sale: &#8369;{{ product.sp_humanize }}
                </p>
              </td>
              <td class="px-6 py-4 text-center text-sm font-bold text-gray-600 dark:text-slate-400">
                {{ product.stock_humanize }}
              </td>
              <td class="px-6 py-4">
                <span
                  class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider flex items-center gap-1.5 w-fit"
                  :class="{
                    'bg-green-100 dark:bg-green-500/10 text-green-700 dark:text-green-400':
                      product.status === ProductStatus.PUBLISHED,
                    'bg-red-100 dark:bg-red-500/10 text-red-700 dark:text-red-400':
                      product.status === ProductStatus.OUT_STOCK,
                    'bg-gray-100 dark:bg-gray-500/10 text-gray-700 dark:text-gray-400':
                      product.status === ProductStatus.DRAFT ||
                      product.status === ProductStatus.INACTIVE,
                  }"
                >
                  <span
                    class="w-1.5 h-1.5 rounded-full animate-pulse"
                    :class="{
                      'bg-green-600 dark:bg-green-400': product.status === ProductStatus.PUBLISHED,
                      'bg-red-600 dark:bg-red-400': product.status === ProductStatus.OUT_STOCK,
                      'bg-gray-600 dark:bg-gray-400':
                        product.status === ProductStatus.DRAFT ||
                        product.status === ProductStatus.INACTIVE,
                    }"
                  ></span>
                  {{ product.status_label }}
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button
                    v-if="authStore.hasPermission(['edit products'])"
                    @click="productStore.toggleModal('edit', true, product)"
                    :aria-label="`Edit ${product.title}`"
                    class="p-2 hover:bg-teal-50 dark:hover:bg-teal-500/10 text-gray-400 dark:text-slate-500 hover:text-teal-600 dark:hover:text-teal-400 rounded-lg transition"
                    type="button"
                  >
                    <PencilSquareIcon class="w-4 h-4" />
                  </button>
                  <button
                    v-if="authStore.hasPermission(['delete products'])"
                    @click="productStore.toggleModal('delete', true, product)"
                    class="p-2 hover:bg-red-50 dark:hover:bg-red-500/10 text-gray-400 dark:text-slate-500 hover:text-red-600 dark:hover:text-red-400 rounded-lg transition"
                    type="button"
                  >
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Pagination v-if="productStore.meta" :meta="productStore.meta" @page-change="onPageChange" />
  </div>
</template>
