export interface CategoryItem {
  name: string
  total_revenue: number
  total_orders: number
  percentage: number
}

export interface CategoryData {
  labels: string[]
  series: number[]
  categories: CategoryItem[]
}

export interface RevenueData {
  categories: string[]
  current: number[]
  previous: number[]
  current_total: string
  prev_total: string
  growth: number
}

export interface KpiTrend {
  percentage: number
  direction: 'up' | 'down'
  label: string // e.g. '+12.5%' or '-2.1%'
}

export interface KpiItem {
  value: string
  raw: number
  trend: KpiTrend
  currency?: string
  color: string
}

export interface KpiData {
  net_revenue: KpiItem
  avg_order_value: KpiItem
}
