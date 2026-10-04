import type { Product } from './data-types'

export interface Order {
  currency?: string
  payments?: { id: number; reference: string; reversed_at: string | null }[]
  id: number
  order_number: string
  customer: string
  created_at: string
  total: string
  status: string
  payment_method: string
  payment_status: string
  items: {
    id: number
    order_id: number
    sku: string
    product_name: string
    quantity: number
    subtotal: string
  }[]
  color: string
}

export interface OrderItem {
  variant_id: number
  quantity: number
  price_type: 'sale' | 'original'
}

export interface OrderPayload {
  idempotency_key?: string
  currency?: string
  discount?: number
  tax?: number
  shipping_fee?: number
  payment_method?: string
  shipping_method?: string
  notes?: string
  items: OrderItem[]
}

// Cart item — this is what form holds
export interface CartItem extends Product {
  variant_id: number
  quantity: number
  price_type: 'sale' | 'original'
}
