import { ref } from 'vue'

export const activeVendorId = ref<number | null>(null)
export const activeVendorSlug = ref('')
export const storefrontSlug = ref('')
export const contextRevision = ref(0)

export function changeContext(vendor: number | null, slug = '', vendorSlug = '') {
  contextRevision.value++
  activeVendorId.value = vendor
  activeVendorSlug.value = vendor ? vendorSlug : ''
  storefrontSlug.value = slug
  window.dispatchEvent(new Event('tenant:reset'))
}

export function staffPath(path: string) {
  if (!activeVendorId.value || !activeVendorSlug.value) throw new Error('Choose a vendor first.')
  return `/api/v1/vendors/${encodeURIComponent(activeVendorSlug.value)}/${path}`
}

export function storePath(path: string) {
  if (!storefrontSlug.value) throw new Error('Open a storefront first.')
  return `/api/v1/stores/${encodeURIComponent(storefrontSlug.value)}/${path}`
}
