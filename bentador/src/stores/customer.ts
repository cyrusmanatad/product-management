import { defineStore } from 'pinia'
import axios from '@/utils/axios' // axios.js file
import { ref, watch } from 'vue'
import { contextRevision } from '@/utils/tenantContext'
import type { Pagination, Stat } from '@/types/data-types'
import type { Customer } from '@/types/customer-types'
import { useFormatter } from '@/composables/useFormatter'
const { formatNumber } = useFormatter()

export const useCustomerStore = defineStore('customer', () => {
  const customers = ref<Customer[] | null>(null)
  const meta = ref<Pagination | null>(null)

  const loading = ref(false)
  const search = ref('')

  const statisticsError = ref('')
  const totalCustomers = ref<number | null>(null)
  const initialStats = (): Stat[] => [
    {
      label: 'Total Customers',
      val: '—',
      color: 'bg-teal-500',
      desc: 'Store customer relationships',
    },
    { label: 'New Customers', val: '—', color: 'bg-green-500', desc: 'Joined in the last 7 days' },
    {
      label: 'Recently Ordered',
      val: '—',
      color: 'bg-blue-500',
      desc: 'Ordered in the last 30 days',
    },
    {
      label: 'Repeat Customers',
      val: '—',
      color: 'bg-purple-500',
      desc: 'Share with more than one order',
    },
  ]
  const stats = ref<Stat[]>(initialStats())
  watch(
    contextRevision,
    () => {
      stats.value = initialStats()
      totalCustomers.value = null
      statisticsError.value = ''
    },
    { flush: 'sync' },
  )

  const fetchCustomers = async (
    searchTerm = search.value,
    page = meta.value?.current_page || 1,
    queries: { status?: string } | null = null,
  ) => {
    loading.value = true

    const params: Record<string, string | number | string[]> = {
      search: searchTerm,
      page,
    }

    if (queries) {
      if (queries.status) {
        params.status = queries.status
      }
    }

    const { data } = await axios.get(`/api/v1/customers`, { params })
    customers.value = data.data
    meta.value = data.meta

    loading.value = false
  }

  const fetchStatistics = async () => {
    const revision = contextRevision.value
    stats.value = initialStats()
    totalCustomers.value = null
    statisticsError.value = ''
    try {
      const { data } = await axios.get('/api/v1/customers/total')
      const result = data.data
      totalCustomers.value = result.total
      if (stats.value[0]) stats.value[0].val = formatNumber(result.total)
      if (stats.value[1]) stats.value[1].val = formatNumber(result.new)
      if (stats.value[2]) stats.value[2].val = formatNumber(result.active)
      if (stats.value[3]) stats.value[3].val = `${formatNumber(result.retention, 2)}%`
    } catch {
      if (revision === contextRevision.value)
        statisticsError.value = 'Customer statistics are unavailable. Please retry.'
    }
  }

  const updateCustomer = async (userId: number, blocked: boolean) => {
    loading.value = true
    try {
      const { data } = await axios.patch(`/api/v1/customers/${userId}/status`, {
        is_blocked: blocked,
      })
      return data
    } finally {
      loading.value = false
    }
  }

  return {
    customers,
    meta,
    loading,
    search,
    stats,
    statisticsError,
    totalCustomers,
    fetchCustomers,
    fetchStatistics,
    updateCustomer,
  }
})
