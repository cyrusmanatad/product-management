import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import axios from '@/utils/axios'
import { changeContext } from '@/utils/tenantContext'
import { useCustomerStore } from '@/stores/customer'
import { useProductStore } from '@/stores/products'

describe('store statistics', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    changeContext(1, '', 'benta-door')
    vi.restoreAllMocks()
  })
  it('uses the API percentage, resets on store switches, and shows unavailable after errors', async () => {
    const store = useCustomerStore()
    expect(store.stats.every((stat) => stat.val === '—')).toBe(true)
    vi.spyOn(axios, 'get').mockResolvedValue({
      data: { data: { total: 4, new: 1, active: 2, retained: 1, retention: 25 } },
    })
    await store.fetchStatistics()
    expect(store.stats.map((stat) => stat.val)).toEqual(['4', '1', '2', '25.00%'])
    expect(store.totalCustomers).toBe(4)
    changeContext(2, '', 'other')
    expect(store.totalCustomers).toBeNull()
    expect(store.stats.every((stat) => stat.val === '—')).toBe(true)
    vi.mocked(axios.get).mockRejectedValue(new Error('Offline'))
    await store.fetchStatistics()
    expect(store.statisticsError).toContain('unavailable')
    expect(store.stats.every((stat) => stat.val === '—')).toBe(true)
    vi.mocked(axios.get).mockResolvedValue({
      data: { data: { total: 0, new: 0, active: 0, retained: 0, retention: 0 } },
    })
    await store.fetchStatistics()
    expect(store.stats.map((stat) => stat.val)).toEqual(['0', '0', '0', '0.00%'])
    expect(store.statisticsError).toBe('')
  })
  it('renders sales from the API and clears product statistics when they are unavailable', async () => {
    const store = useProductStore()
    vi.spyOn(axios, 'get').mockResolvedValue({
      data: {
        data: { total_products: 8, total_units_sold: 23, total_available: 100, total_out_stock: 2 },
      },
    })
    await store.fetchStatistics()
    expect(store.stats.map((stat) => stat.val)).toEqual(['8', '23', '100', '2'])
    changeContext(2, '', 'other')
    expect(store.stats.every((stat) => stat.val === '—')).toBe(true)
    vi.mocked(axios.get).mockRejectedValue(new Error('Offline'))
    await store.fetchStatistics()
    expect(store.statisticsError).toContain('unavailable')
    expect(store.stats.every((stat) => stat.val === '—')).toBe(true)
  })
})
