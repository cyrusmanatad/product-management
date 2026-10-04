import { describe, it, expect, beforeEach, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import router from '@/router'
import axios from '@/utils/axios'
import { useTenantStore } from '@/stores/tenant'
import { useAuthStore } from '@/stores/auth'
import { changeContext, storefrontSlug } from '@/utils/tenantContext'

vi.mock('@/utils/axios', () => ({
  default: { get: vi.fn(), defaults: { headers: { common: {} } } },
}))

describe('vendor routes', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
    changeContext(null)
    vi.mocked(axios.get).mockResolvedValue({
      data: { data: { id: 1, slug: 'benta-door', name: 'Benta Door' } },
    })
  })
  it('opens the homepage without choosing a default storefront', async () => {
    await router.push('/')
    await router.isReady()
    expect(router.currentRoute.value.path).toBe('/')
    expect(router.currentRoute.value.name).toBe('home')
    expect(useTenantStore().activeVendorId).toBeNull()
    expect(storefrontSlug.value).toBe('')
  })
  it('opens an independent public storefront', async () => {
    await router.push('/stores/acme')
    expect(router.currentRoute.value.params.slug).toBe('acme')
    expect(useTenantStore().activeVendorId).toBeNull()
  })
  it('redirects guests away from vendor administration', async () => {
    await router.push('/vendors/benta-door/products')
    expect(router.currentRoute.value.name).toBe('login')
  })
  it('opens staff administration by slug and uses slug permissions endpoints', async () => {
    const auth = useAuthStore()
    const user = {
      id: 10,
      name: 'Owner',
      email: 'owner@example.com',
      created_at: '',
      roles: ['Owner'],
      permissions: ['view products'],
    }
    auth.user = user
    useTenantStore().vendors = [
      { id: 2, slug: 'acme', name: 'Acme', currency: 'PHP', is_active: true },
    ]
    vi.mocked(axios.get).mockResolvedValue({ data: { data: user } })
    await router.push('/vendors/acme')
    expect(router.currentRoute.value.path).toBe('/vendors/acme/products')
    expect(axios.get).toHaveBeenCalledWith('/api/v1/vendors/acme/me')
    expect(useTenantStore().activeVendorId).toBe(2)
    expect(useTenantStore().activeVendorSlug).toBe('acme')

    vi.mocked(axios.get).mockResolvedValue({ data: { data: { slug: 'acme' } } })
    await router.push('/stores/acme')
    expect(storefrontSlug.value).toBe('acme')
    expect(useTenantStore().activeVendorId).toBeNull()
    expect(useTenantStore().activeVendorSlug).toBe('')
  })
  it('rejects a numeric ID instead of treating it as a store slug', async () => {
    useAuthStore().user = {
      id: 10,
      name: 'Owner',
      email: 'owner@example.com',
      created_at: '',
      roles: ['Owner'],
      permissions: ['view products'],
    }
    vi.mocked(axios.get).mockResolvedValue({
      data: { data: [{ id: 2, slug: 'acme', name: 'Acme', currency: 'PHP', is_active: true }] },
    })
    await router.push('/vendors/2/products')
    expect(router.currentRoute.value.path).toBe('/vendors')
    expect(useTenantStore().activeVendorId).toBeNull()
  })
})
