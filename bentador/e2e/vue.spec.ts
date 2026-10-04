import { test, expect, type Page } from '@playwright/test'

const vendors = [
  { id: 1, slug: 'benta-door', name: 'Acme store', currency: 'PHP', is_active: true },
  { id: 2, slug: 'other', name: 'Other store', currency: 'PHP', is_active: true },
]
const user = {
  id: 10,
  name: 'Shared customer',
  email: 'buyer@example.com',
  roles: [],
  permissions: [],
  created_at: '',
  is_active: true,
}
function product(vendor: number) {
  return {
    id: vendor,
    title: vendor === 1 ? 'Acme door' : 'Other door',
    base_sku: 'DOOR',
    description: '',
    category: 'Doors',
    category_id: vendor,
    price: 100,
    sale_price: 90,
    price_humanize: '100.00',
    sp_humanize: '90.00',
    currency: 'PHP',
    stock: 5,
    stock_humanize: '5',
    uom: 'pcs',
    slug: 'door',
    status: 'published',
    status_label: 'Published',
    createdAt: '',
    variants: [
      {
        id: vendor === 1 ? 7 : 9,
        sku: 'DOOR-RED',
        price: 100,
        sale_price: 90,
        stock: 5,
        reserved_quantity: 0,
        attributes: { color: 'Red' },
      },
    ],
  }
}
async function mockApi(page: Page) {
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname
    expect(path).not.toMatch(/\/vendors\/\d+(?:\/|$)/)
    let body: unknown = { data: [] }
    if (path === '/api/v1/users/me') body = { data: user }
    if (path === '/api/v1/me/vendors') body = { data: vendors }
    if (path.match(/vendors\/(benta-door|other)\/me$/))
      body = {
        data: {
          ...user,
          roles: ['Owner'],
          permissions: [
            'view products',
            'view orders',
            'view customers',
            'view analytics',
            'view users',
            'view roles-permission',
          ],
        },
      }
    if (path.match(/stores\/(benta-door|other)$/))
      body = { data: vendors.find((v) => path.endsWith(v.slug)) }
    if (
      path.endsWith('/catalog/categories') ||
      path.match(/vendors\/(benta-door|other)\/categories$/)
    )
      body = [{ id: 1, name: 'Doors' }]
    if (path.endsWith('/products')) {
      const vendor = path.includes('/other/') ? 2 : 1
      body = {
        data: [product(vendor)],
        meta: { current_page: 1, last_page: 1, per_page: 10, total: 1, from: 1, to: 1 },
      }
    }
    if (path.endsWith('/inventory/total'))
      body = { data: { total_products: 1, total_available: 5, total_out_stock: 0 } }
    if (path.endsWith('/checkout')) {
      const payload = route.request().postDataJSON()
      expect(payload.items[0].variant_id).toBe(7)
      expect(payload.idempotency_key).toBeTruthy()
      body = { data: { id: 1 }, message: 'Order created successfully' }
    }
    if (path.endsWith('/orders'))
      body = {
        data: {
          data: [
            {
              id: 1,
              order_number: path.includes('/other/') ? 'OTHER-ORDER' : 'ACME-ORDER',
              total: '90.00',
              status: 'pending',
              payment_status: 'unpaid',
              items: [],
            },
          ],
        },
      }
    await route.fulfill({ json: body, headers: { 'Access-Control-Allow-Origin': '*' } })
  })
}

test.beforeEach(async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem('auth_token', 'browser-test-token'))
  await mockApi(page)
})

test('independent stores keep separate carts and checkout uses the selected variant', async ({
  page,
}) => {
  await page.goto('/stores/benta-door')
  await page.getByRole('button', { name: 'Add to cart', exact: true }).click()
  await expect
    .poll(() =>
      page.evaluate(
        () =>
          JSON.parse(localStorage.getItem('bentador.cart.v2.benta-door') ?? '{}').items?.[0]
            ?.variant_id,
      ),
    )
    .toBe(7)
  await page.goto('/stores/other')
  await expect(
    page.getByRole('heading', { name: 'Other store', exact: true }).first(),
  ).toBeVisible()
  await expect
    .poll(() => page.evaluate(() => localStorage.getItem('bentador.cart.v2.other')))
    .toBeNull()
  await page.goto('/stores/benta-door')
  await page.getByRole('button', { name: 'Open cart' }).click()
  await page.getByRole('button', { name: 'Checkout Now' }).click()
  await page.getByRole('button', { name: /Place order/ }).click()
  await expect
    .poll(() => page.evaluate(() => localStorage.getItem('bentador.cart.v2.benta-door')))
    .toBeNull()
})

test('staff switch vendors without retaining the previous catalog', async ({ page }) => {
  await page.goto('/vendors')
  await page.getByRole('link', { name: 'Manage store' }).first().click()
  await expect(page).toHaveURL(/vendors\/benta-door\/products/)
  await expect(page.getByText('Acme door', { exact: true })).toBeVisible()
  await page.getByLabel('Active store').selectOption('other')
  await expect(page).toHaveURL(/vendors\/other\/products/)
  await expect(page.getByText('Other door', { exact: true })).toBeVisible()
  await expect(page.getByText('Acme door', { exact: true })).toHaveCount(0)
  await page.goto('/vendors/other/')
  await expect(page).toHaveURL(/vendors\/other\/products/)
  await expect(page.getByText('Other door', { exact: true })).toBeVisible()
})

test('shared customers view the current store order history', async ({ page }) => {
  await page.goto('/stores/benta-door/orders')
  await expect(page.getByRole('heading', { name: 'ACME-ORDER' })).toBeVisible()
  await page.goto('/stores/other/orders')
  await expect(page.getByRole('heading', { name: 'OTHER-ORDER' })).toBeVisible()
  await expect(page.getByText('ACME-ORDER')).toHaveCount(0)
})
