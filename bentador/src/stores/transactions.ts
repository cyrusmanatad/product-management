import { useCartStore } from './cart'
import { ref } from 'vue'
import { defineStore } from 'pinia'
import axios from '@/utils/axios'
import { useOrderMapper } from '@/composables/useOrderMapper'
import type { CartItem, Order, OrderPayload } from '@/types/order'
import type { Pagination, Stat, Status } from '@/types/data-types'
import { OrderStatus, OrderStatusLabel } from '@/types/enum'
import { useFormatter } from '@/composables/useFormatter'
const { formatNumber } = useFormatter()

export const useOrderStore = defineStore('order', () => {
  const loading = ref(false)
  const search = ref('')
  const errors = ref<Record<string, string[]>>({})
  const orders = ref<Order[]>([])
  const meta = ref<Pagination | null>(null)
  const ordersToday = ref(0)

  const statuses = ref<Status<OrderStatus, OrderStatusLabel>[]>([
    { code: OrderStatus.ALL, label: OrderStatusLabel.ALL },
    { code: OrderStatus.PENDING, label: OrderStatusLabel.PENDING },
    { code: OrderStatus.CONFIRMED, label: OrderStatusLabel.CONFIRMED },
    { code: OrderStatus.PROCESSING, label: OrderStatusLabel.PROCESSING },
    { code: OrderStatus.SHIPPED, label: OrderStatusLabel.SHIPPED },
    { code: OrderStatus.DELIVERED, label: OrderStatusLabel.DELIVERED },
    { code: OrderStatus.CANCELLED, label: OrderStatusLabel.CANCELLED },
    { code: OrderStatus.REFUNDED, label: OrderStatusLabel.REFUNDED },
  ])

  const selectedOrder = ref<Order | null>(null)

  const modals = ref({
    details: false,
    delete: false,
  })

  const toggleModal = (
    modalName: keyof typeof modals.value,
    value: boolean,
    order: Order | null = null,
  ) => {
    modals.value[modalName] = value
    if (order) {
      selectedOrder.value = { ...order }
    } else if (!value) {
      selectedOrder.value = null
    }
  }

  const { mapCartToPayload } = useOrderMapper()

  const stats = ref<Stat[]>([
    { label: 'Total Orders', val: '42,120', color: 'bg-teal-500', desc: 'Total volume' },
    { label: 'Pending Orders', val: '124', color: 'bg-orange-400', desc: 'Awaiting shipping' },
    { label: 'Completed', val: '41,850', color: 'bg-green-500', desc: 'Successfully delivered' },
    { label: 'Total Revenue', val: '$582,400', color: 'bg-blue-500', desc: 'Net earnings' },
  ])

  const updateOrderStatus = async (status: string) => {
    await axios.put(`/api/v1/orders/${selectedOrder.value?.id}`, {
      status,
    })
    modals.value.details = false
    fetchOrders()
  }

  const deleteOrder = async () => {
    await axios.delete(`/api/v1/orders/${selectedOrder.value?.id}`)
    modals.value.delete = false
    fetchOrders()
  }

  const submitOrder = async (cartItems: CartItem[], options?: Omit<OrderPayload, 'items'>) => {
    loading.value = true
    errors.value = {}

    try {
      const payload = {
        ...mapCartToPayload(cartItems, options),
        idempotency_key: useCartStore().idempotencyKey(),
      }

      const { data } = await axios.post('/api/v1/checkout', payload)
      return data
    } catch (err: unknown) {
      // Laravel validation errors
      if (axios.isAxiosError(err) && err.response?.status === 422) {
        errors.value = err.response.data.errors
      }
      throw err
    } finally {
      loading.value = false
    }
  }

  const fetchOrders = async (
    searchTerm = search.value,
    page = meta.value?.current_page || 1,
    queries: { status?: string } | null = null,
  ) => {
    try {
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

      const { data } = await axios.get(`/api/v1/orders`, { params })
      orders.value = data.data
      meta.value = data.meta
    } catch (error) {
      console.error('Failed to fetch orders', error)
    } finally {
      loading.value = false
    }
  }

  const fetchStatistics = async () => {
    try {
      loading.value = true

      const { data } = await axios.get(`/api/v1/orders/total`)

      const _stats = data.data

      ordersToday.value = _stats.today

      if (stats.value[0]) stats.value[0].val = formatNumber(_stats.total)
      if (stats.value[1]) stats.value[1].val = formatNumber(_stats.pending)
      if (stats.value[2]) stats.value[2].val = formatNumber(_stats.completed)
      if (stats.value[3]) stats.value[3].val = formatNumber(_stats.revenue, 2)
    } catch (error) {
      console.error('Failed to fetch order statistics', error)
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    meta,
    modals,
    orders,
    ordersToday,
    errors,
    search,
    selectedOrder,
    stats,
    statuses,
    deleteOrder,
    fetchOrders,
    fetchStatistics,
    submitOrder,
    toggleModal,
    updateOrderStatus,
  }
})
