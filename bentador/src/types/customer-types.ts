export interface Customer {
  id: number
  name: string
  email: string
  joined: string
  orders: number
  completed_orders: number
  spent: string
  lv_raw: number
  last_login_at: string | null
  last_login_raw: string | null
  status: string
  color: string
  is_active: boolean
  is_blocked: boolean
  type: string
}

// interface Customer {
//   name: string
//   email: string
//   joined: string
//   orders: string
//   spent: string
//   status?: string
//   color?: string
// }
