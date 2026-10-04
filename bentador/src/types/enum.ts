import type { Status } from './data-types'

export enum ROLES {
  Support = 'Support',
  Staff = 'Inventory Staff',
  Admin = 'Admin',
  SuperAdmin = 'Owner',
  Client = 'Client',
}

export enum ProductStatus {
  ALL = '',
  PUBLISHED = 'published',
  OUT_STOCK = 'out-of-stock',
  DRAFT = 'draft',
  INACTIVE = 'inactive',
}

export enum ProductStatusLabel {
  ALL = 'All Status',
  PUBLISHED = 'Published',
  OUT_STOCK = 'Out Stock',
  DRAFT = 'Draft List',
  INACTIVE = 'Inactive',
}
export enum PaymentMethod {
  GCASH = 'gcash',
  PAYMAYA = 'paymaya',
  COD = 'cash',
  BANK = 'bank_transfer',
}

export enum OrderStatus {
  ALL = '',
  PENDING = 'pending',
  CONFIRMED = 'confirmed',
  PROCESSING = 'processing',
  SHIPPED = 'shipped',
  DELIVERED = 'delivered',
  CANCELLED = 'cancelled',
  REFUNDED = 'refunded',
}

export enum OrderStatusLabel {
  ALL = 'All Status',
  PENDING = 'Pending',
  CONFIRMED = 'Confirmed',
  PROCESSING = 'Processing',
  SHIPPED = 'Shipped',
  DELIVERED = 'Delivered',
  CANCELLED = 'Cancelled',
  REFUNDED = 'Refunded',
}

export const PRODUCT_STATUS = {
  ALL: { code: ProductStatus.ALL, label: ProductStatusLabel.ALL },
  PUBLISHED: { code: ProductStatus.PUBLISHED, label: ProductStatusLabel.PUBLISHED },
  OUT_STOCK: { code: ProductStatus.OUT_STOCK, label: ProductStatusLabel.OUT_STOCK },
  DRAFT: { code: ProductStatus.DRAFT, label: ProductStatusLabel.DRAFT },
  INACTIVE: { code: ProductStatus.INACTIVE, label: ProductStatusLabel.INACTIVE },
} as const

export const getProductStatusLabel = (status: string): string => {
  const item = Object.values(PRODUCT_STATUS).find((s) => s.code === status)
  return item?.label ?? 'Unknown'
}

export const getProductStatus = (
  status: string,
): Status<ProductStatus, ProductStatusLabel> | null => {
  const item = Object.values(PRODUCT_STATUS).find((s) => s.code === status)
  return item ?? null
}

export const ORDER_STATUS = {
  PENDING: { code: OrderStatus.PENDING, label: OrderStatusLabel.PENDING },
  CONFIRMED: { code: OrderStatus.CONFIRMED, label: OrderStatusLabel.CONFIRMED },
  PROCESSING: { code: OrderStatus.PROCESSING, label: OrderStatusLabel.PROCESSING },
  SHIPPED: { code: OrderStatus.SHIPPED, label: OrderStatusLabel.SHIPPED },
  DELIVERED: { code: OrderStatus.DELIVERED, label: OrderStatusLabel.DELIVERED },
  CANCELLED: { code: OrderStatus.CANCELLED, label: OrderStatusLabel.CANCELLED },
  REFUNDED: { code: OrderStatus.REFUNDED, label: OrderStatusLabel.REFUNDED },
}
