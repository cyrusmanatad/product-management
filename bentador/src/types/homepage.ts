import type { Pagination } from './data-types'

export interface HomepageProduct {
  id: number
  title: string
  slug: string
  primary_image_url?: string | null
  price: string | null
  currency: string
  store: { name: string; slug: string }
  feature_id: number | null
  visible: boolean
}

export interface PageResult<T> {
  data: T[]
  meta: Pagination
}
