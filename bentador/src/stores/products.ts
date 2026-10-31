import type {
  Pagination,
  Product,
  ProductForm,
  Stat,
  Status,
  VariantInput,
} from '@/types/data-types'
import { defineStore } from 'pinia'
import { ref, watch } from 'vue'
import { contextRevision } from '@/utils/tenantContext'
import axios from '@/utils/axios' // axios.js file
import { ProductStatus, ProductStatusLabel } from '@/types/enum'
import { useFormatter } from '@/composables/useFormatter'
const { formatStock } = useFormatter()

export const useProductStore = defineStore('products', () => {
  const products = ref<Product[]>([])
  const categories = ref<{ id: number; name: string }[]>([])
  const productOptions = ref<VariantInput[]>([])
  const meta = ref<Pagination | null>(null)

  const isLoading = ref(false)
  const search = ref('')

  const statuses = ref<Status<ProductStatus, ProductStatusLabel>[]>([
    { code: ProductStatus.ALL, label: ProductStatusLabel.ALL },
    { code: ProductStatus.PUBLISHED, label: ProductStatusLabel.PUBLISHED },
    { code: ProductStatus.OUT_STOCK, label: ProductStatusLabel.OUT_STOCK },
    { code: ProductStatus.DRAFT, label: ProductStatusLabel.DRAFT },
    { code: ProductStatus.INACTIVE, label: ProductStatusLabel.INACTIVE },
  ])

  const stats = ref<Stat[]>([
    { label: 'Total Products', val: '—', color: 'bg-teal-500', desc: 'Current catalog' },
    {
      label: 'Units Sold',
      val: '—',
      color: 'bg-orange-400',
      desc: 'Paid, non-cancelled orders · all time',
    },
    { label: 'Available Units', val: '—', color: 'bg-green-500', desc: 'On hand minus reserved' },
    {
      label: 'Marked Out of Stock',
      val: '—',
      color: 'bg-red-400',
      isAlert: true,
      desc: 'Products with out-of-stock status',
    },
  ])

  const statisticsError = ref('')
  watch(
    contextRevision,
    () => {
      stats.value.forEach((stat) => {
        stat.val = '—'
      })
      statisticsError.value = ''
    },
    { flush: 'sync' },
  )

  const selectedProduct = ref<Product | null>(null)

  const modals = ref({
    add: false,
    edit: false,
    delete: false,
    logout: false,
  })

  const toggleModal = (
    modalName: keyof typeof modals.value,
    value: boolean,
    product: Product | null = null,
  ) => {
    modals.value[modalName] = value
    if (product) {
      selectedProduct.value = { ...product }
    } else if (!value) {
      selectedProduct.value = null
    }
  }

  const fetchStatistics = async () => {
    const revision = contextRevision.value
    stats.value.forEach((stat) => {
      stat.val = '—'
    })
    statisticsError.value = ''
    try {
      isLoading.value = true

      const total = await axios.get(`/api/v1/inventory/total`)

      const { data } = total.data

      if (stats.value[0]) stats.value[0].val = formatStock(data.total_products)
      if (stats.value[1] && data.total_units_sold !== undefined)
        stats.value[1].val = formatStock(data.total_units_sold)
      if (stats.value[2]) stats.value[2].val = formatStock(data.total_available)
      if (stats.value[3]) stats.value[3].val = formatStock(data.total_out_stock)
    } catch {
      if (revision === contextRevision.value)
        statisticsError.value = 'Product statistics are unavailable. Please retry.'
    } finally {
      isLoading.value = false
    }
  }

  const fetchCatalogCategories = async () => {
    try {
      isLoading.value = true
      const { data } = await axios.get(`/api/v1/catalog/categories`)
      categories.value = data
    } catch (error) {
      console.error('Failed to fetch catalog categories', error)
    } finally {
      isLoading.value = false
    }
  }

  const fetchCatalogProducts = async () => {
    try {
      isLoading.value = true
      const { data } = await axios.get(`/api/v1/catalog/products`)
      products.value = data.data
      meta.value = data.meta
    } catch (error) {
      console.error('Failed to fetch catalog products', error)
    } finally {
      isLoading.value = false
    }
  }

  const fetchCategories = async () => {
    try {
      isLoading.value = true

      if (categories.value.length > 0) return true

      const { data } = await axios.get(`/api/v1/categories`)

      categories.value = data
    } catch (error) {
      console.error('Failed to fetch products', error)
    } finally {
      isLoading.value = false
    }
  }

  const fetchProducts = async (
    searchTerm = search.value,
    page = meta.value?.current_page || 1,
    queries: { category?: string[]; status?: string } | null = null,
  ) => {
    try {
      isLoading.value = true

      const params: Record<string, string | number | string[]> = {
        search: searchTerm,
        page,
      }

      if (queries) {
        if (queries.status) {
          params.status = queries.status
        }
        if (queries.category) {
          params.category = queries.category
        }
      }

      const { data } = await axios.get(`/api/v1/products`, { params })
      products.value = data.data
      meta.value = data.meta
    } catch (error) {
      console.error('Failed to fetch products', error)
    } finally {
      isLoading.value = false
    }
  }

  const addProduct = async (product: ProductForm) => {
    isLoading.value = true
    await axios.post(`/api/v1/products`, product)
    await fetchProducts(search.value, 1) // Reset to page 1 for new items
    await fetchStatistics()
    toggleModal('add', false)
    isLoading.value = false
  }

  const updateProduct = async (productId: number, updates: ProductForm) => {
    isLoading.value = true
    try {
      await axios.put(`/api/v1/products/${productId}`, updates)

      await fetchProducts(search.value) // Will use current search and page
      toggleModal('edit', false)
    } catch {
      // Leave the modal open so the user can retry.
    } finally {
      isLoading.value = false
    }
  }

  const deleteProduct = async (productId: number) => {
    isLoading.value = true
    await axios.delete(`/api/v1/products/${productId}`)
    await fetchProducts(search.value) // Will use current search and page
    toggleModal('delete', false)
  }

  const exportCsv = () => console.log('Export')
  const importBulk = () => console.log('Import')
  const deleteAll = () => console.log('Delete All')

  return {
    categories,
    isLoading,
    modals,
    meta,
    products,
    productOptions,
    search,
    stats,
    statisticsError,
    statuses,
    selectedProduct,
    addProduct,
    deleteAll,
    deleteProduct,
    exportCsv,
    fetchCatalogCategories,
    fetchCatalogProducts,
    fetchCategories,
    fetchProducts,
    fetchStatistics,
    importBulk,
    toggleModal,
    updateProduct,
  }
})
