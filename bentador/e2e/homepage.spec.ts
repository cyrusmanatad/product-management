import { test, expect, type Page } from '@playwright/test'

const stores = [
  { id: 1, name: 'Acme store', slug: 'acme', currency: 'PHP' },
  { id: 2, name: 'Other store', slug: 'other', currency: 'PHP' },
]
const products = stores.map((store) => ({
  id: store.id,
  title: `${store.name} door`,
  slug: 'shared-door',
  price: '123.45',
  currency: 'PHP',
  store: { name: store.name, slug: store.slug },
  visible: true,
}))
function pageResult<T>(data: T[]) {
  return {
    data,
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 12,
      total: data.length,
      from: 1,
      to: data.length,
    },
  }
}
async function mockHomepage(page: Page, account: 'guest' | 'platform' | 'owner', initial = [1, 2]) {
  const selected = new Set(initial)
  const failures: string[] = []
  page.on('pageerror', (error) => failures.push(error.message))
  if (account !== 'guest') {
    await page.addInitScript(() => localStorage.setItem('auth_token', 'homepage-browser-token'))
  }
  await page.route('**/api/v1/**', async (route) => {
    const url = new URL(route.request().url())
    const path = url.pathname
    const listings = products.map((product) => ({
      ...product,
      feature_id: selected.has(product.id) ? product.id : null,
    }))
    let body: unknown = { data: [] }
    if (path === '/api/v1/users/me')
      body = {
        data: {
          id: 10,
          name: 'Platform administrator',
          email: 'preview@example.com',
          roles: [],
          permissions: [],
          created_at: '',
          is_platform_admin: account === 'platform',
        },
      }
    if (path === '/api/v1/homepage/stores') {
      const search = url.searchParams.get('search')?.toLowerCase() ?? ''
      body = pageResult(stores.filter((store) => store.name.toLowerCase().includes(search)))
    }
    if (
      path === '/api/v1/homepage/featured-products' ||
      path === '/api/v1/platform/featured-products'
    )
      body = pageResult(listings.filter((product) => product.feature_id))
    if (path.endsWith('/feature-candidates'))
      body = pageResult(listings.filter((product) => path.includes(`/${product.store.slug}/`)))
    if (path.includes('/platform/vendors/') && path.includes('/featured-products')) {
      expect(account).toBe('platform')
      if (route.request().method() === 'POST') {
        const id = route.request().postDataJSON().product_id
        expect(path).toContain(
          `/vendors/${products.find((product) => product.id === id)?.store.slug}/`,
        )
        selected.add(id)
      } else if (route.request().method() === 'DELETE')
        selected.delete(Number(path.split('/').at(-1)))
      body = { message: 'Saved' }
    }
    const store = stores.find((store) => path.includes(`/stores/${store.slug}`))
    if (store) {
      if (path === `/api/v1/stores/${store.slug}`) body = { data: store }
      if (path.endsWith('/catalog/categories')) body = [{ id: store.id, name: 'Doors' }]
      const product = {
        id: store.id,
        title: `${store.name} door`,
        slug: 'shared-door',
        base_sku: 'DOOR',
        category: 'Doors',
        category_id: store.id,
        description: '',
        price: 123.45,
        currency: 'PHP',
        stock: 4,
        status: 'published',
        variants: [
          {
            id: 100 + store.id,
            sku: 'DOOR-RED',
            price: 123.45,
            stock: 4,
            attributes: { color: 'Red' },
          },
        ],
      }
      if (path.endsWith('/catalog/products')) body = pageResult([product])
      if (path.endsWith('/catalog/products/shared-door')) body = { data: product }
    }
    await route.fulfill({ json: body, headers: { 'Access-Control-Allow-Origin': '*' } })
  })
  return failures
}

test('public homepage works at mobile tablet and desktop sizes and links to the selected store product', async ({
  page,
}) => {
  const errors = await mockHomepage(page, 'guest')
  for (const { width, dark, theme } of [
    { width: 320, dark: false, theme: 'blue' },
    { width: 768, dark: true, theme: 'blue' },
    { width: 1440, dark: false, theme: 'green' },
  ]) {
    await page.setViewportSize({ width, height: 900 })
    await page.goto('/')
    await page.evaluate(
      ({ dark, theme }) => {
        localStorage.setItem('colorTheme', theme)
        localStorage.setItem('darkMode', String(dark))
      },
      { dark, theme },
    )
    await page.reload()
    await expect(page).toHaveURL(/\/$/)
    await expect(page.getByRole('heading', { name: 'Featured products' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Other store door' })).toBeVisible()
    await expect(page.getByRole('link', { name: 'Homepage products', exact: true })).toHaveCount(0)
    await expect(page.getByRole('link', { name: 'Sign in', exact: true })).toBeVisible()
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true)
    await page.screenshot({ path: `/tmp/bentador-home-${width}.png`, fullPage: true })
  }
  await page.getByLabel('Find a store', { exact: true }).fill('Other')
  await page.getByRole('button', { name: 'Search stores' }).click()
  await expect(page.getByRole('link', { name: 'Visit store' })).toHaveCount(1)
  await page.getByRole('link', { name: 'View product' }).nth(1).click()
  await expect(page).toHaveURL(/\/stores\/other\?product=shared-door/)
  await expect(page.getByRole('heading', { name: 'Other store door' }).last()).toBeVisible()
  await page
    .getByRole('button', { name: /Add to Cart/i })
    .last()
    .click()
  await expect
    .poll(() =>
      page.evaluate(
        () =>
          JSON.parse(localStorage.getItem('bentador.cart.v2.other') ?? '{}').items?.[0]?.variant_id,
      ),
    )
    .toBe(102)
  expect(errors).toEqual([])
})

test('platform administrator features and removes products with the matching homepage result', async ({
  page,
}) => {
  const errors = await mockHomepage(page, 'platform', [])
  await page.setViewportSize({ width: 320, height: 900 })
  await page.goto('/platform/featured-products')
  await page.getByRole('button', { name: 'Acme store', exact: true }).click()
  await page.getByRole('button', { name: 'Feature product', exact: true }).click()
  await expect(page.getByText('Visible on homepage', { exact: true })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Already featured' })).toBeDisabled()
  for (const width of [320, 768, 1440]) {
    await page.setViewportSize({ width, height: 900 })
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true)
    await page.screenshot({ path: `/tmp/bentador-home-admin-${width}.png`, fullPage: true })
  }
  await page.getByRole('link', { name: 'Home', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Acme store door' })).toBeVisible()
  await page.getByRole('link', { name: 'Homepage products', exact: true }).click()
  await page.getByRole('button', { name: 'Remove', exact: true }).click()
  await expect(
    page.getByText('No featured products yet. Choose a product to get started.'),
  ).toBeVisible()
  await page.getByRole('link', { name: 'Home', exact: true }).click()
  await expect(
    page.getByRole('heading', { name: 'More featured finds are on the way' }),
  ).toBeVisible()
  expect(errors).toEqual([])
})

test('store owners cannot open platform homepage management', async ({ page }) => {
  await mockHomepage(page, 'owner')
  await page.goto('/platform/featured-products')
  await expect(page).toHaveURL(/\/vendors$/)
  await expect(page.getByRole('link', { name: 'Homepage products', exact: true })).toHaveCount(0)
})
