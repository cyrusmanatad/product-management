<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  BuildingStorefrontIcon,
  ArrowRightIcon,
  ArrowTopRightOnSquareIcon,
} from '@heroicons/vue/24/outline'
import { useTenantStore } from '@/stores/tenant'
import { useAuthStore } from '@/stores/auth'
import WorkspaceLayout from '@/components/layouts/WorkspaceLayout.vue'
import AppHeader from '@/components/layouts/AppHeader.vue'
import InlineMessage from '@/components/ui/InlineMessage.vue'
import TableSpinner from '@/components/ui/TableSpinner.vue'

const tenant = useTenantStore()
const auth = useAuthStore()
const loading = ref(true)
const error = ref('')
onMounted(async () => {
  try {
    await tenant.fetchMemberships()
  } catch {
    error.value = 'Your stores could not be loaded. Please try again.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <WorkspaceLayout>
    <AppHeader
      title="Your stores"
      description="Select a store to manage its catalog, orders, and staff."
      :breadcrumb="[{ label: 'Workspace' }, { label: 'Your stores' }]"
      :show-menu-button="false"
    />
    <InlineMessage v-if="error" kind="error" class="mb-6">{{ error }}</InlineMessage>
    <section class="relative min-h-48" :aria-busy="loading" aria-label="Your stores">
      <TableSpinner v-if="loading" text="Loading your stores..." />
      <div v-else-if="tenant.vendors.length" class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        <article
          v-for="vendor in tenant.vendors"
          :key="vendor.id"
          class="bg-white dark:bg-dark-card border border-gray-100 dark:border-dark-border rounded-3xl shadow-sm p-6 flex flex-col"
        >
          <div class="flex items-start justify-between gap-4 mb-6">
            <div
              class="w-12 h-12 bg-teal-50 dark:bg-teal-500/10 text-teal-600 dark:text-teal-400 rounded-2xl flex items-center justify-center"
            >
              <BuildingStorefrontIcon class="w-6 h-6" aria-hidden="true" />
            </div>
            <span
              class="px-3 py-1 rounded-full bg-green-50 dark:bg-green-500/10 text-green-700 dark:text-green-400 text-[11px] font-bold"
              >Active</span
            >
          </div>
          <h2 class="text-lg font-bold text-gray-900 dark:text-white break-words">
            {{ vendor.name }}
          </h2>
          <p class="text-sm text-gray-500 dark:text-slate-400 mt-1 mb-6 break-all">
            /stores/{{ vendor.slug }}
          </p>
          <div class="mt-auto space-y-3">
            <RouterLink
              :to="`/vendors/${encodeURIComponent(vendor.slug)}/products`"
              class="flex min-h-11 items-center justify-center gap-2 px-5 py-3 bg-teal-500 hover:bg-teal-600 text-white text-sm font-bold rounded-xl shadow-lg shadow-teal-500/20 transition active:scale-95"
              >Manage store <ArrowRightIcon class="w-4 h-4" aria-hidden="true"
            /></RouterLink>
            <RouterLink
              :to="`/stores/${vendor.slug}`"
              class="flex min-h-11 items-center justify-center gap-2 px-5 py-3 border border-gray-200 dark:border-dark-border text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 text-sm font-bold rounded-xl transition"
              >Open storefront <ArrowTopRightOnSquareIcon class="w-4 h-4" aria-hidden="true"
            /></RouterLink>
          </div>
        </article>
      </div>
      <div
        v-else-if="!error"
        class="bg-white dark:bg-dark-card border border-gray-100 dark:border-dark-border rounded-3xl shadow-sm p-8 sm:p-12 text-center"
      >
        <div
          class="w-16 h-16 bg-gray-50 dark:bg-slate-800/50 rounded-2xl flex items-center justify-center text-gray-400 mx-auto mb-5"
        >
          <BuildingStorefrontIcon class="w-8 h-8" aria-hidden="true" />
        </div>
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">No stores yet</h2>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-2">
          You have no active staff memberships.
        </p>
        <RouterLink
          v-if="auth.user?.is_platform_admin"
          to="/platform/vendors"
          class="inline-flex items-center gap-2 min-h-11 mt-5 text-sm font-bold text-teal-600 dark:text-teal-400 hover:underline"
          >Manage platform vendors <ArrowRightIcon class="w-4 h-4"
        /></RouterLink>
      </div>
    </section>
  </WorkspaceLayout>
</template>
