import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from '@/utils/axios'
import { activeVendorId, activeVendorSlug, changeContext, staffPath } from '@/utils/tenantContext'
import { useAuthStore } from './auth'

export interface Vendor {
  id: number
  name: string
  slug: string
  currency: string
  is_active: boolean
}

export const useTenantStore = defineStore('tenant', () => {
  const vendors = ref<Vendor[]>([])
  const store = ref<Vendor | null>(null)
  const error = ref('')
  window.addEventListener('session:cleared', () => {
    vendors.value = []
    store.value = null
  })
  async function fetchMemberships() {
    const { data } = await axios.get('/api/v1/me/vendors')
    vendors.value = data.data
  }
  async function selectVendor(slug: string) {
    if (!vendors.value.some((v) => v.slug === slug)) await fetchMemberships()
    const vendor = vendors.value.find((v) => v.slug === slug)
    if (!vendor) throw new Error('Vendor access is unavailable.')
    changeContext(vendor.id, '', vendor.slug)
    store.value = null
    try {
      const { data } = await axios.get(staffPath('me'))
      useAuthStore().user = data.data
      store.value = vendor
    } catch (error) {
      if (activeVendorSlug.value === slug) changeContext(null)
      throw error
    }
  }
  async function openStore(slug: string) {
    changeContext(null, slug)
    store.value = null
    error.value = ''
    try {
      const { data } = await axios.get(`/api/v1/stores/${encodeURIComponent(slug)}`)
      store.value = data.data
    } catch {
      error.value = 'This store is unavailable.'
    }
  }
  function leaveStore() {
    changeContext(null)
    store.value = null
    error.value = ''
  }
  return {
    vendors,
    store,
    error,
    activeVendorId,
    activeVendorSlug,
    fetchMemberships,
    selectVendor,
    openStore,
    leaveStore,
  }
})
