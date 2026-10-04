import { describe, it, expect, beforeEach } from 'vitest'
import axios from '@/utils/axios'
import { changeContext } from '@/utils/tenantContext'
import { useAuthStore } from '@/stores/auth'
import { createPinia, setActivePinia } from 'pinia'

describe('tenant API client', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
    changeContext(null)
  })
  it('fails closed when staff context is missing', async () => {
    await expect(axios.get('/api/v1/products')).rejects.toThrow('Choose a vendor')
    changeContext(1)
    await expect(axios.get('/api/v1/products')).rejects.toThrow('Choose a vendor')
  })
  it('rewrites requests to the active vendor and discards late responses', async () => {
    changeContext(1, '', 'benta-door')
    let release!: () => void
    let called!: () => void
    const started = new Promise<void>((resolve) => {
      called = resolve
    })
    const request = axios.get('/api/v1/products', {
      adapter: async (config) => {
        expect(config.url).toBe('/api/v1/vendors/benta-door/products')
        called()
        await new Promise<void>((resolve) => {
          release = resolve
        })
        return {
          config,
          data: { data: ['private-vendor-one'] },
          status: 200,
          statusText: 'OK',
          headers: {},
        }
      },
    })
    const rejected = (async () => {
      await expect(request).rejects.toThrow('Vendor context changed')
    })()
    await started
    changeContext(2, '', 'other')
    release()
    await rejected
  })
  it('refreshes both persisted tokens and clears them on logout failure', async () => {
    const auth = useAuthStore()
    axios.defaults.adapter = async (config) => ({
      config,
      data: { authorization: { access_token: 'new-token' } },
      status: 200,
      statusText: 'OK',
      headers: {},
    })
    expect(await auth.refreshToken()).toBe(true)
    expect(localStorage.getItem('auth_token')).toBe('new-token')
    axios.defaults.adapter = async () => {
      throw new Error('Offline')
    }
    await expect(auth.logout()).rejects.toThrow('Offline')
    expect(localStorage.getItem('auth_token')).toBeNull()
    expect(auth.user).toBeNull()
  })
})
