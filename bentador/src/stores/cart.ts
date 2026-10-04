import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import type { Product } from '@/types/data-types'
import type { CartItem } from '@/types/order'
import { storefrontSlug } from '@/utils/tenantContext'

const storageKey = () => `bentador.cart.v2.${storefrontSlug.value}`

function readCart(): CartItem[] {
  if (!storefrontSlug.value) return []
  try {
    const doc = JSON.parse(localStorage.getItem(storageKey()) ?? 'null')
    if (doc?.version !== 2 || doc.store !== storefrontSlug.value || !Array.isArray(doc.items))
      return []
    return doc.items.filter(
      (item: CartItem) =>
        Number.isInteger(item.variant_id) &&
        item.variant_id > 0 &&
        Number.isInteger(item.quantity) &&
        item.quantity > 0 &&
        typeof item.title === 'string' &&
        Number.isFinite(item.price) &&
        item.price >= 0 &&
        ['sale', 'original'].includes(item.price_type),
    )
  } catch {
    return []
  }
}

export const useCartStore = defineStore('cart', () => {
  const items = ref<CartItem[]>(readCart())
  const isCartOpen = ref(false)
  const checkoutKey = ref('')
  watch(
    items,
    (value) => {
      checkoutKey.value = ''
      if (!storefrontSlug.value) return
      if (!value.length) localStorage.removeItem(storageKey())
      else
        localStorage.setItem(
          storageKey(),
          JSON.stringify({ version: 2, store: storefrontSlug.value, items: value }),
        )
    },
    { deep: true, flush: 'sync' },
  )
  watch(
    storefrontSlug,
    () => {
      items.value = readCart()
      isCartOpen.value = false
    },
    { flush: 'sync' },
  )
  const cartTotal = computed(
    () =>
      items.value.reduce((sum, item) => sum + Math.round(item.price * 100) * item.quantity, 0) /
      100,
  )
  const cartCount = computed(() => items.value.reduce((sum, item) => sum + item.quantity, 0))
  function addToCart(product: Product, variantId?: number) {
    if (!storefrontSlug.value) throw new Error('Open a store before adding items.')
    const variant = variantId
      ? product.variants.find((v) => v.id === variantId)
      : (product.variants.find((v) => v.sku === product.base_sku) ??
        (product.variants.length === 1 ? product.variants[0] : undefined))
    if (!variant) throw new Error('Choose a product variant.')
    const existing = items.value.find((item) => item.variant_id === variant.id)
    if (existing) {
      existing.quantity++
      return
    }
    const original = Number(variant.price),
      sale = Number(variant.sale_price)
    const onSale = variant.sale_price != null && Number.isFinite(sale) && sale < original
    items.value.push({
      ...product,
      base_sku: variant.sku,
      price: onSale ? sale : original,
      sale_price: sale,
      variant_id: variant.id,
      quantity: 1,
      price_type: onSale ? 'sale' : 'original',
    })
  }
  const removeFromCart = (id: number) => {
    items.value = items.value.filter((item) => item.variant_id !== id)
  }
  function updateQuantity(id: number, delta: number) {
    const item = items.value.find((item) => item.variant_id === id)
    if (item) {
      item.quantity += delta
      if (item.quantity <= 0) removeFromCart(id)
    }
  }
  const clearCart = () => {
    items.value = []
    checkoutKey.value = ''
  }
  const toggleCart = (value?: boolean) => {
    isCartOpen.value = value ?? !isCartOpen.value
  }
  function idempotencyKey() {
    if (!checkoutKey.value) checkoutKey.value = crypto.randomUUID()
    return checkoutKey.value
  }
  return {
    items,
    isCartOpen,
    cartTotal,
    cartCount,
    checkoutKey,
    addToCart,
    removeFromCart,
    updateQuantity,
    clearCart,
    toggleCart,
    idempotencyKey,
  }
})
