<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { BuildingStorefrontIcon, EnvelopeIcon, LinkIcon, PlusIcon } from '@heroicons/vue/24/outline'
import axios from '@/utils/axios'
import type { Vendor } from '@/stores/tenant'
import WorkspaceLayout from '@/components/layouts/WorkspaceLayout.vue'
import AppHeader from '@/components/layouts/AppHeader.vue'
import FormField from '@/components/ui/FormField.vue'
import ActionButton from '@/components/ui/ActionButton.vue'
import InlineMessage from '@/components/ui/InlineMessage.vue'
import TableSpinner from '@/components/ui/TableSpinner.vue'
import { useToastStore } from '@/stores/toast'

const toast = useToastStore()
const vendors = ref<Vendor[]>([])
const name = ref(''),
  slug = ref(''),
  email = ref(''),
  link = ref(''),
  error = ref('')
const loading = ref(true),
  saving = ref(false),
  updating = ref<number | null>(null)
async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/platform/vendors')
    vendors.value = data.data.data
  } catch {
    error.value = 'Vendors could not be loaded. Please try again.'
  } finally {
    loading.value = false
  }
}
async function create() {
  error.value = ''
  link.value = ''
  saving.value = true
  try {
    const { data } = await axios.post('/api/v1/platform/vendors', {
      name: name.value,
      slug: slug.value,
      owner_email: email.value,
    })
    link.value = data.invitation_link
    name.value = slug.value = email.value = ''
    toast.addToast('Vendor created. Share the owner invitation.', 'success')
    await load()
  } catch {
    error.value = 'Vendor could not be created. Check the name, unique slug, and email.'
  } finally {
    saving.value = false
  }
}
async function toggle(vendor: Vendor) {
  error.value = ''
  updating.value = vendor.id
  try {
    await axios.patch(`/api/v1/platform/vendors/${encodeURIComponent(vendor.slug)}`, {
      is_active: !vendor.is_active,
    })
    toast.addToast(vendor.is_active ? 'Vendor suspended.' : 'Vendor activated.', 'success')
    await load()
  } catch {
    error.value = 'Vendor status could not be updated. Please try again.'
  } finally {
    updating.value = null
  }
}
onMounted(load)
</script>

<template>
  <WorkspaceLayout>
    <AppHeader
      title="Platform vendors"
      description="Create independent stores and manage vendor access."
      :breadcrumb="[{ label: 'Platform' }, { label: 'Vendors' }]"
      :show-menu-button="false"
    />
    <InlineMessage v-if="error" kind="error" class="mb-6">{{ error }}</InlineMessage>
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)] items-start">
      <section
        class="bg-white dark:bg-dark-card border border-gray-100 dark:border-dark-border rounded-3xl shadow-sm overflow-hidden"
      >
        <div
          class="p-6 border-b border-gray-100 dark:border-dark-border flex items-center gap-3 bg-gray-50/50 dark:bg-slate-800/20"
        >
          <div
            class="w-11 h-11 bg-teal-50 dark:bg-teal-500/10 text-teal-600 dark:text-teal-400 rounded-xl flex items-center justify-center"
          >
            <BuildingStorefrontIcon class="w-5 h-5" aria-hidden="true" />
          </div>
          <div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Create vendor</h2>
            <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">
              Invite an owner to manage their store.
            </p>
          </div>
        </div>
        <form @submit.prevent="create" class="p-6 space-y-5">
          <FormField
            id="vendor-name"
            v-model="name"
            label="Store name"
            :icon="BuildingStorefrontIcon"
            required
            maxlength="255"
            placeholder="Your store name"
            :disabled="saving"
          />
          <div>
            <FormField
              id="vendor-slug"
              v-model="slug"
              label="Store slug"
              :icon="LinkIcon"
              required
              pattern="[a-z0-9]+(-[a-z0-9]+)*"
              placeholder="your-store"
              :disabled="saving"
              aria-describedby="slug-help"
            />
            <p id="slug-help" class="text-xs text-gray-500 dark:text-slate-400 mt-2">
              Used in the storefront address: /stores/your-store
            </p>
          </div>
          <FormField
            id="owner-email"
            v-model="email"
            label="Owner email"
            type="email"
            :icon="EnvelopeIcon"
            required
            autocomplete="email"
            placeholder="owner@example.com"
            :disabled="saving"
          />
          <ActionButton type="submit" :loading="saving" class="w-full"
            ><PlusIcon v-if="!saving" class="w-4 h-4" aria-hidden="true" />{{
              saving ? 'Creating vendor...' : 'Create vendor and invite owner'
            }}</ActionButton
          >
          <InlineMessage v-if="link" kind="success"
            ><p class="font-bold mb-1">Owner invitation ready</p>
            <p>Share this link with the store owner.</p>
            <a :href="link" class="block mt-2 underline break-all font-medium">{{
              link
            }}</a></InlineMessage
          >
        </form>
      </section>
      <section
        class="relative min-h-60 bg-white dark:bg-dark-card border border-gray-100 dark:border-dark-border rounded-3xl shadow-sm overflow-hidden"
        :aria-busy="loading"
      >
        <div class="p-6 border-b border-gray-100 dark:border-dark-border">
          <h2 class="text-lg font-bold text-gray-900 dark:text-white">Vendor directory</h2>
          <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">
            Suspended vendors cannot access their store.
          </p>
        </div>
        <TableSpinner v-if="loading" text="Loading vendors..." />
        <div v-else-if="vendors.length" class="divide-y divide-gray-100 dark:divide-dark-border">
          <article
            v-for="vendor in vendors"
            :key="vendor.id"
            class="p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
          >
            <div class="min-w-0">
              <h3 class="text-sm font-bold text-gray-900 dark:text-white break-words">
                {{ vendor.name }}
              </h3>
              <p class="text-xs text-gray-500 dark:text-slate-400 mt-1 break-all">
                /stores/{{ vendor.slug }}
              </p>
              <span
                class="inline-flex px-2.5 py-1 mt-3 text-[11px] font-bold rounded-full"
                :class="
                  vendor.is_active
                    ? 'bg-green-50 dark:bg-green-500/10 text-green-700 dark:text-green-400'
                    : 'bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400'
                "
                >{{ vendor.is_active ? 'Active' : 'Suspended' }}</span
              >
            </div>
            <ActionButton
              :variant="vendor.is_active ? 'danger' : 'secondary'"
              :loading="updating === vendor.id"
              :disabled="updating !== null"
              :aria-label="`${vendor.is_active ? 'Suspend' : 'Activate'} ${vendor.name}`"
              @click="toggle(vendor)"
              >{{ vendor.is_active ? 'Suspend' : 'Activate' }}</ActionButton
            >
          </article>
        </div>
        <p v-else class="p-10 text-center text-sm text-gray-500 dark:text-slate-400">
          No vendors to display.
        </p>
      </section>
    </div>
  </WorkspaceLayout>
</template>
