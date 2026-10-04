import { describe, it, expect, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useCartStore } from '@/stores/cart'
import { changeContext } from '@/utils/tenantContext'
import type { Product } from '@/types/data-types'
import { ProductStatus, ProductStatusLabel } from '@/types/enum'

const product = {
  id: 1,
  base_sku: 'DOOR',
  title: 'Door',
  category_id: 1,
  category: 'Doors',
  currency: 'PHP',
  price: 100,
  price_humanize: '100.00',
  sale_price: 90,
  sp_humanize: '90.00',
  slug: 'door',
  uom: 'pcs',
  stock: 10,
  stock_humanize: '10',
  status: ProductStatus.PUBLISHED,
  status_label: ProductStatusLabel.PUBLISHED,
  createdAt: 'today',
  variants: [
    {
      id: 7,
      sku: 'DOOR-RED',
      price: 100,
      sale_price: 90,
      stock: 5,
      reserved_quantity: 0,
      attributes: { color: 'Red' },
    },
    {
      id: 9,
      sku: 'DOOR-BLUE',
      price: 120,
      sale_price: 110,
      stock: 5,
      reserved_quantity: 0,
      attributes: { color: 'Blue' },
    },
  ],
} satisfies Product

describe('vendor cart', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
    changeContext(null, 'acme')
  })
  it('requires variant selection and preserves the actual selected ID', () => {
    const cart = useCartStore()
    expect(() => cart.addToCart(product)).toThrow('Choose a product variant')
    cart.addToCart(product, 9)
    cart.addToCart(product, 7)
    expect(cart.items.map((item) => item.variant_id)).toEqual([9, 7])
    expect(cart.items[0]?.price).toBe(110)
    expect(cart.cartTotal).toBe(200)
  })
  it('persists variants and quantities independently per storefront', () => {
    const cart = useCartStore()
    cart.addToCart(product, 7)
    cart.updateQuantity(7, 1)
    changeContext(null, 'other')
    expect(cart.items).toEqual([])
    cart.addToCart(product, 9)
    changeContext(null, 'acme')
    expect(cart.items[0]?.variant_id).toBe(7)
    expect(cart.items[0]?.quantity).toBe(2)
    setActivePinia(createPinia())
    expect(useCartStore().items[0]?.variant_id).toBe(7)
  })
  it('reuses a checkout key for retries and changes it when cart content changes', () => {
    const cart = useCartStore()
    cart.addToCart(product, 7)
    const first = cart.idempotencyKey()
    expect(cart.idempotencyKey()).toBe(first)
    cart.updateQuantity(7, 1)
    expect(cart.idempotencyKey()).not.toBe(first)
    cart.clearCart()
    expect(localStorage.getItem('bentador.cart.v2.acme')).toBeNull()
  })
  it('ignores legacy carts whose variants were not trustworthy', () => {
    localStorage.setItem('bentadoor.cart.v1', JSON.stringify({ version: 1, items: [product] }))
    expect(useCartStore().items).toEqual([])
  })
})
