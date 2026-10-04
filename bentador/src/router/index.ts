import { createRouter, createWebHistory } from 'vue-router'
import AppLayout from '@/components/layouts/AppLayout.vue'
import AnalyticsView from '@/views/AnalyticsView.vue'
import OrdersView from '@/views/OrdersView.vue'
import CustomersView from '@/views/CustomersView.vue'
import UsersView from '@/views/UsersView.vue'
import RolesPermissionView from '@/views/RolesPermissionView.vue'
import LoginView from '@/views/LoginView.vue'
import ProductsView from '@/views/ProductsView.vue'
import OrderEntryView from '@/views/OrderEntryView.vue'
import ProfileSettingsView from '@/views/ProfileSettingsView.vue'
import AccountSettingsView from '@/views/AccountSettingsView.vue'
import { useTenantStore } from '@/stores/tenant'
import PasswordResetView from '@/views/PasswordResetView.vue'
import VendorsView from '@/views/VendorsView.vue'
import InvitationView from '@/views/InvitationView.vue'
import PlatformVendorsView from '@/views/PlatformVendorsView.vue'
import CustomerOrdersView from '@/views/CustomerOrdersView.vue'
import HomeView from '@/views/HomeView.vue'
import PlatformFeaturedProductsView from '@/views/PlatformFeaturedProductsView.vue'
import { useAuthStore } from '@/stores/auth'
import { storefrontSlug } from '@/utils/tenantContext'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', name: 'home', component: HomeView, meta: { title: 'Home' } },
    {
      path: '/platform/featured-products',
      component: PlatformFeaturedProductsView,
      meta: { requiresAuth: true, platform: true, title: 'Homepage products' },
    },
    { path: '/reset-password', component: PasswordResetView, meta: { title: 'Reset password' } },
    {
      path: '/vendors',
      component: VendorsView,
      meta: { requiresAuth: true, title: 'Your stores' },
    },
    {
      path: '/platform/vendors',
      component: PlatformVendorsView,
      meta: { requiresAuth: true, platform: true, title: 'Platform vendors' },
    },
    { path: '/invitations/:token', component: InvitationView, meta: { title: 'Store invitation' } },
    {
      path: '/profile',
      name: 'profile-settings',
      component: ProfileSettingsView,
      meta: { requiresAuth: true },
    },
    {
      path: '/account',
      name: 'account-settings',
      component: AccountSettingsView,
      meta: { requiresAuth: true },
    },
    {
      path: '/stores/:slug/orders',
      name: 'customer-orders',
      component: CustomerOrdersView,
      meta: { requiresAuth: true, title: 'Your orders' },
    },
    { path: '/order-entry', redirect: '/' },
    ...['analytics', 'products', 'orders', 'customers', 'users', 'roles-permission'].map(
      (page) => ({ path: `/${page}`, redirect: '/vendors' }),
    ),
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { guestOnly: true },
    },
    {
      path: '/stores/:slug',
      name: 'order-entry',
      component: OrderEntryView,
      meta: { title: 'Order Entry' },
    },
    {
      path: '/vendors/:vendor',
      component: AppLayout,
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          redirect: (to) => `/vendors/${encodeURIComponent(String(to.params.vendor))}/products`,
        },
        {
          path: 'analytics',
          name: 'analytics',
          component: AnalyticsView,
          meta: {
            permissions: ['view analytics'],
          },
        },
        {
          path: 'products',
          name: 'products',
          component: ProductsView,
          meta: {
            permissions: ['view products'],
          },
        },
        {
          path: 'orders',
          name: 'orders',
          component: OrdersView,
          meta: {
            permissions: ['view orders'],
          },
        },
        {
          path: 'customers',
          name: 'customers',
          component: CustomersView,
          meta: {
            permissions: ['view customers'],
          },
        },
        {
          path: 'users',
          name: 'users',
          component: UsersView,
          meta: {
            permissions: ['view users'],
          },
        },
        {
          path: 'roles-permission',
          name: 'roles-permission',
          component: RolesPermissionView,
          meta: {
            permissions: ['view roles-permission'],
          },
        },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  const token = localStorage.getItem('auth_token')

  // A guest has no token, so the shop must not call /users/me.
  if (!auth.user && token) {
    await auth.fetchUser()
  }

  if (to.meta.requiresAuth && !auth.user) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (auth.user?.must_reset_password && to.path !== '/account') return '/account'
  if (to.meta.platform && !auth.user?.is_platform_admin) return '/vendors'
  const tenant = useTenantStore()
  if (to.name === 'home') tenant.leaveStore()
  if (to.params.vendor && String(to.params.vendor) !== tenant.activeVendorSlug) {
    try {
      await tenant.selectVendor(String(to.params.vendor))
    } catch {
      return '/vendors'
    }
  }
  if (to.params.slug && storefrontSlug.value !== to.params.slug) {
    await tenant.openStore(String(to.params.slug))
  }

  // Role check. Meta fields are widened because vue-router types them as {}.
  const requiredRoles = to.meta.roles as unknown as string[] | undefined
  if (requiredRoles?.length) {
    const hasRole = requiredRoles.some((role) => auth.user?.roles.includes(role))

    if (!hasRole) {
      return '/vendors'
    }
  }

  // Permission check
  const requiredPermissions = to.meta.permissions as unknown as string[] | undefined
  if (requiredPermissions?.length) {
    const hasPermission = requiredPermissions.every((permission) =>
      auth.user?.permissions.includes(permission),
    )

    if (!hasPermission) {
      return '/vendors'
    }
  }

  return true
})

router.afterEach(async (to) => {
  document.title = `Benta Door: ${to.meta.title ?? 'Dashboard'}`
})

export default router
