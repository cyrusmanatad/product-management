import type { CartItem, OrderPayload } from '@/types/order'

export function useOrderMapper() {
  const mapCartToPayload = (
    items: CartItem[],
    options?: Omit<OrderPayload, 'items'>,
  ): OrderPayload => ({
    ...options,
    items: items.map((item) => ({
      variant_id: item.variant_id,
      quantity: item.quantity,
      price_type: item.price_type,
    })),
  })
  return { mapCartToPayload }
}
