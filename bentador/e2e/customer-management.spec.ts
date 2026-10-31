import { test, expect } from '@playwright/test'

for (const [label, width] of [
  ['mobile', 390],
  ['tablet', 768],
  ['desktop', 1440],
] as const) {
  test.describe(label, () => {
    test.use({ viewport: { width, height: 900 } })

    test('customer status persists with retry and accurate statistics', async ({ page }) => {
      const user = {
        id: 1,
        name: 'Owner',
        email: 'owner@example.com',
        roles: ['Owner'],
        permissions: ['view customers', 'edit customers', 'view products'],
        is_active: true,
      }
      const vendors = [
        { id: 1, slug: 'benta-door', name: 'Acme', currency: 'PHP', is_active: true },
        { id: 2, slug: 'other', name: 'Other', currency: 'PHP', is_active: true },
      ]
      let blocked = false
      let failUpdate = true
      let failStats = false
      await page.addInitScript(() => localStorage.setItem('auth_token', 'test-token'))
      await page.route('**/api/v1/**', async (route) => {
        const path = new URL(route.request().url()).pathname
        let body: unknown = { data: [] }
        if (path.endsWith('/users/me') || path.match(/vendors\/[^/]+\/me$/)) body = { data: user }
        if (path.endsWith('/me/vendors')) body = { data: vendors }
        if (path.endsWith('/customers/total')) {
          if (failStats) return route.fulfill({ status: 500, json: { message: 'Unavailable' } })
          body = { data: { total: 4, new: 1, active: 2, retained: 1, retention: 25 } }
        }
        if (path.endsWith('/customers'))
          body = {
            data: [
              {
                id: 42,
                name: 'Shared Buyer',
                email: 'buyer@example.com',
                joined: '1 day ago',
                orders: 2,
                spent: '20.00',
                status: blocked && path.includes('/benta-door/') ? 'Blocked' : 'Active',
                color: blocked && path.includes('/benta-door/') ? 'red' : 'green',
              },
            ],
            meta: { current_page: 1, last_page: 1, per_page: 10, total: 4, from: 1, to: 1 },
          }
        if (path.endsWith('/customers/42/status')) {
          if (route.request().method() !== 'PATCH') throw new Error('Status changes must use PATCH')
          if (failUpdate) {
            failUpdate = false
            return route.fulfill({ status: 500, json: { message: 'Try again' } })
          }
          blocked = route.request().postDataJSON().is_blocked
          body = { data: { id: 42, status: blocked ? 'Blocked' : 'Active' } }
        }
        if (path.endsWith('/inventory/total'))
          body = {
            data: {
              total_products: 8,
              total_units_sold: 23,
              total_available: 100,
              total_out_stock: 2,
            },
          }
        if (path.endsWith('/products'))
          body = { data: [], meta: { current_page: 1, last_page: 1, total: 0 } }
        if (path.endsWith('/categories')) body = []
        await route.fulfill({ json: body })
      })
      await page.goto('/vendors/benta-door/customers')
      await expect(page.getByText('25.00%', { exact: true })).toBeVisible()
      await expect(page.getByText('Phone unavailable')).toBeVisible()
      await expect(page.getByText('8,420')).toHaveCount(0)
      await page.getByRole('button', { name: 'Block Shared Buyer', exact: true }).click()
      await expect(
        page.getByText('Their account and existing orders remain available.', { exact: false }),
      ).toBeVisible()
      await page.getByRole('button', { name: 'Yes, Block Customer' }).click()
      await expect(page.getByRole('alert')).toContainText('Could not update')
      await page.getByRole('button', { name: 'Yes, Block Customer' }).click()
      await expect(page.getByRole('status')).toHaveText('Customer blocked at this store.')
      await page.reload()
      await page.getByRole('button', { name: 'Unblock Shared Buyer', exact: true }).click()
      await page.getByRole('button', { name: 'Yes, Unblock Customer' }).click()
      await expect(page.getByRole('status')).toHaveText('Customer unblocked at this store.')
      await expect(
        page.getByRole('heading', { name: 'Unblock Customer?', exact: true }),
      ).toHaveCount(0)
      await page.screenshot({
        path: `../planning/evidence/sprint1/customers-${label}.png`,
        fullPage: true,
      })
      expect(
        await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth),
      ).toBe(true)
      failStats = true
      await page.reload()
      await expect(page.getByRole('alert')).toContainText('Customer statistics are unavailable')
      await expect(page.getByText('25.00%', { exact: true })).toHaveCount(0)
      failStats = false
      await page.getByRole('button', { name: 'Retry', exact: true }).click()
      await expect(page.getByText('25.00%', { exact: true })).toBeVisible()
      await page.goto('/vendors/other/customers')
      await expect(
        page.getByRole('button', { name: 'Block Shared Buyer', exact: true }),
      ).toBeVisible()
      await page.goto('/vendors/other/products')
      await expect(page.getByText('Units Sold', { exact: true })).toBeVisible()
      await expect(page.getByText('23', { exact: true })).toBeVisible()
      user.permissions = ['view customers']
      await page.goto('/vendors/other/customers')
      await expect(page.getByText('Shared Buyer', { exact: true })).toBeVisible()
      await expect(
        page.getByRole('button', { name: 'Block Shared Buyer', exact: true }),
      ).toHaveCount(0)
      await expect(
        page.getByRole('button', { name: 'Edit status for Shared Buyer', exact: true }),
      ).toHaveCount(0)
    })
  })
}
