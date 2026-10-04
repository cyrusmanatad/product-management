export interface User {
  is_platform_admin?: boolean
  must_reset_password?: boolean
  id: number
  name: string
  email: string
  created_at: string
  permissions: string[]
  roles: string[]
  is_active?: boolean
  last_login_at?: string | null
  last_login_ip?: string | null
  login?: string
  status?: string
  color?: string
}

export interface ProfileForm {
  name: string
  email: string
}

export interface PasswordForm {
  current_password: string
  password: string
  password_confirmation: string
}

export interface ProfileFormErrors {
  name: string[]
  email: string[]
}

export interface PasswordFormErrors {
  current_password: string[]
  password: string[]
  password_confirmation: string[]
}

export interface Credentials {
  email: string
  password: string
}

export interface RegisterPayload {
  name: string
  email: string
  password: string
}
