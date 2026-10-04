import type { ProductStatus, ProductStatusLabel } from './enum'

// export interface ProductVariant {
//   id: string
//   product_id: string
//   desc: string
//   sku: string
//   uom: string
//   price: string
//   sale_price: string
//   currency: string
//   inventory?: {
//     id: string
//     variant_id: string
//     stock_quantity: number
//     reserved_quantity: number
//   }
// }

export enum STATUS {}

export interface ProductOption {
  name: string
  values: string[]
}

export interface VariantInput {
  id: number
  sku: string
  price: number
  sale_price: number
  stock: number
  reserved_quantity: number
  attributes: Record<string, string>
}

export interface ProductImage {
  id: number
  url: string
  is_primary: boolean
  sort_order: number
}

export interface Product {
  id: number
  base_sku: string
  title: string
  description?: string
  category_id: number
  category: string
  currency: string
  price: number
  price_humanize: string
  sale_price: number
  sp_humanize: string
  slug: string
  uom: string
  stock: number
  stock_humanize: string
  status: ProductStatus
  status_label: ProductStatusLabel
  createdAt: string
  images?: ProductImage[]
  primary_image_url?: string | null
  icon?: string
  options?: ProductOption[]
  variants: VariantInput[]
}

export type ProductForm = Omit<
  Product,
  | 'images'
  | 'primary_image_url'
  | 'id'
  | 'price_humanize'
  | 'sp_humanize'
  | 'stock_humanize'
  | 'currency'
  | 'createdAt'
  | 'category'
  | 'icon'
  | 'status_label'
>

export interface Pagination {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number
  to: number
}

export interface Category {
  id: string
  name: string
}

export interface Stat {
  label: string
  val: string
  desc: string
  color: string
  isAlert?: boolean
}

export interface Status<TCode, TLabel> {
  code: TCode
  label: TLabel
}
