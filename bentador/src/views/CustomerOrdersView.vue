<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ClipboardDocumentListIcon, ShoppingBagIcon } from '@heroicons/vue/24/outline'
import axios from '@/utils/axios'
import { storePath } from '@/utils/tenantContext'
import { useTenantStore } from '@/stores/tenant'
import type { Order } from '@/types/order'
import WorkspaceLayout from '@/components/layouts/WorkspaceLayout.vue'
import AppHeader from '@/components/layouts/AppHeader.vue'
import InlineMessage from '@/components/ui/InlineMessage.vue'
import TableSpinner from '@/components/ui/TableSpinner.vue'

const route = useRoute()
const tenant = useTenantStore()
const orders = ref<Order[]>([]),
  error = ref(''),
  loading = ref(true)
const label = (value: string) =>
  value.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
const amount = (value: string, currency = 'PHP') =>
  new Intl.NumberFormat('en-PH', { style: 'currency', currency }).format(Number(value))
onMounted(async () => {
  try {
    const { data } = await axios.get(storePath('orders'))
    orders.value = data.data.data
  } catch {
    error.value = 'Orders are unavailable. Please try again.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <WorkspaceLayout :storefront-path="`/stores/${route.params.slug}`">
    <AppHeader
      title="Your orders"
      :description="`Track your purchases from ${tenant.store?.name ?? 'this store'}.`"
      :breadcrumb="[
        { label: tenant.store?.name ?? 'Store', to: `/stores/${route.params.slug}` },
        { label: 'Your orders' },
      ]"
      :show-menu-button="false"
    />
    <InlineMessage v-if="error" kind="error" class="mb-6">{{ error }}</InlineMessage>
    <section
      class="relative min-h-48 max-w-3xl mx-auto space-y-6"
      :aria-busy="loading"
      aria-label="Order history"
    >
      <TableSpinner v-if="loading" text="Loading your orders..." />
      <div
        v-else-if="!orders.length && !error"
        class="bg-white dark:bg-dark-card border border-gray-100 dark:border-dark-border rounded-3xl shadow-sm p-8 sm:p-12 text-center"
      >
        <div
          class="w-16 h-16 bg-gray-50 dark:bg-slate-800/50 text-gray-400 rounded-2xl flex items-center justify-center mx-auto mb-5"
        >
          <ShoppingBagIcon class="w-8 h-8" aria-hidden="true" />
        </div>
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">No orders yet</h2>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-2">No orders in this store yet.</p>
        <RouterLink
          :to="`/stores/${route.params.slug}`"
          class="inline-flex min-h-11 items-center mt-4 text-sm font-bold text-teal-600 dark:text-teal-400 hover:underline"
          >Browse the storefront</RouterLink
        >
      </div>
      <article
        v-for="order in orders"
        :key="order.id"
        class="bg-white dark:bg-dark-card border border-gray-100 dark:border-dark-border rounded-3xl shadow-sm overflow-hidden"
      >
        <div
          class="p-5 sm:p-6 bg-gray-50/50 dark:bg-slate-800/20 border-b border-gray-100 dark:border-dark-border flex flex-wrap items-start justify-between gap-4"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div
              class="w-10 h-10 bg-teal-50 dark:bg-teal-500/10 text-teal-600 dark:text-teal-400 rounded-xl flex items-center justify-center shrink-0"
            >
              <ClipboardDocumentListIcon class="w-5 h-5" aria-hidden="true" />
            </div>
            <div class="min-w-0">
              <h2 class="text-sm font-bold text-gray-900 dark:text-white break-all">
                {{ order.order_number }}
              </h2>
              <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">
                {{ label(order.payment_method || 'cash') }}
              </p>
            </div>
          </div>
          <span
            class="px-3 py-1.5 text-[11px] font-bold rounded-full"
            :class="
              order.status === 'cancelled'
                ? 'bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400'
                : order.status === 'delivered'
                  ? 'bg-green-50 dark:bg-green-500/10 text-green-700 dark:text-green-400'
                  : 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-300'
            "
            >{{ label(order.status) }}</span
          >
        </div>
        <div class="p-5 sm:p-6 space-y-4">
          <div
            v-for="item in order.items"
            :key="item.id"
            class="flex justify-between gap-4 text-sm"
          >
            <div class="min-w-0">
              <p class="font-medium text-gray-900 dark:text-white break-words">
                {{ item.product_name }}
              </p>
              <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">
                Quantity: {{ item.quantity }}
              </p>
            </div>
            <p class="font-bold text-gray-700 dark:text-slate-300 shrink-0">
              {{ amount(item.subtotal, order.currency) }}
            </p>
          </div>
          <div
            class="border-t border-gray-100 dark:border-dark-border pt-4 flex flex-wrap items-center justify-between gap-3"
          >
            <span
              class="text-xs font-bold px-3 py-1.5 rounded-full"
              :class="
                order.payment_status === 'paid'
                  ? 'bg-green-50 dark:bg-green-500/10 text-green-700 dark:text-green-400'
                  : 'bg-orange-50 dark:bg-orange-500/10 text-orange-700 dark:text-orange-400'
              "
              >{{ label(order.payment_status) }}</span
            >
            <p class="text-sm text-gray-500 dark:text-slate-400">
              Total
              <span class="text-lg font-bold text-gray-900 dark:text-white ml-2">{{
                amount(order.total, order.currency)
              }}</span>
            </p>
          </div>
        </div>
      </article>
    </section>
  </WorkspaceLayout>
</template>
