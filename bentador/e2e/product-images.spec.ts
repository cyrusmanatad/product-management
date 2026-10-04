import { test, expect, type Page } from '@playwright/test'

const png = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII=',
  'base64',
)
const image = (id: number, primary: boolean) => ({
  id,
  url: `/api/v1/stores/benta-door/images/${id}`,
  is_primary: primary,
  sort_order: id,
})
const store = { id: 1, name: 'Benta Door', slug: 'benta-door', currency: 'PHP', is_active: true }
const user = {
  id: 10,
  name: 'Store owner',
  email: 'owner@example.com',
  roles: ['Owner'],
  permissions: ['view products', 'create products', 'edit products'],
  created_at: '',
  is_active: true,
}
const product = {
  id: 1,
  title: 'Gallery door',
  base_sku: 'DOOR',
  description: 'A door with multiple images.',
  category: 'Doors',
  category_id: 1,
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
      id: 7,
      sku: 'DOOR',
      price: 100,
      sale_price: 90,
      stock: 5,
      reserved_quantity: 0,
      attributes: {},
    },
  ],
}
const pageResult = (data: unknown[]) => ({
  data,
  meta: {
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: data.length,
    from: 1,
    to: data.length,
  },
})
async function mockImages(page: Page, failFirstUpload = false) {
  const state = {
    images: [image(1, false), image(2, true)],
    uploads: [] as string[],
    creates: 0,
    updates: 0,
    failures: [] as string[],
  }
  page.on('pageerror', (error) => state.failures.push(error.message))
  await page.addInitScript(() => localStorage.setItem('auth_token', 'image-browser-token'))
  await page.route('**/api/v1/**', async (route) => {
    const request = route.request()
    const path = new URL(request.url()).pathname
    const method = request.method()
    const headers = { 'Access-Control-Allow-Origin': '*', 'Access-Control-Allow-Headers': '*' }
    if (path.match(/\/images\/\d+(?:\/file)?$/) && method === 'GET') {
      await route.fulfill({ body: png, contentType: 'image/png', headers })
      return
    }
    let body: unknown = { data: [] }
    if (path === '/api/v1/users/me' || path.endsWith('/vendors/benta-door/me'))
      body = { data: user }
    if (path === '/api/v1/me/vendors') body = { data: [store] }
    if (path === '/api/v1/stores/benta-door') body = { data: store }
    if (path.endsWith('/categories')) body = [{ id: 1, name: 'Doors' }]
    if (path.endsWith('/inventory/total'))
      body = { data: { total_products: 1, total_available: 5, total_out_stock: 0 } }
    const sorted = () =>
      [...state.images].sort((a, b) => Number(b.is_primary) - Number(a.is_primary) || a.id - b.id)
    if (path.endsWith('/products') && method === 'GET')
      body = pageResult([{ ...product, images: sorted(), primary_image_url: sorted()[0]?.url }])
    if (path.endsWith('/catalog/products/door')) body = { data: { ...product, images: sorted() } }
    if (path === '/api/v1/vendors/benta-door/products' && method === 'POST') {
      state.creates++
      body = { data: { id: 1 } }
    }
    if (path === '/api/v1/vendors/benta-door/products/1' && method === 'PUT') {
      state.updates++
      expect(request.postDataJSON().variants[0].price).toBe(100)
      body = { data: true }
    }
    if (path.endsWith('/products/1/images')) {
      if (method === 'POST') {
        const multipart = request.postData() ?? ''
        state.uploads.push(multipart)
        if (failFirstUpload && state.uploads.length === 1) {
          await route.fulfill({
            status: 422,
            json: { message: 'Upload failed. Try again.' },
            headers,
          })
          return
        }
        expect(multipart).toContain('filename="first.png"')
        expect(multipart).toContain('filename="second.png"')
        expect(multipart).toMatch(/name="primary_index"\r\n\r\n1/)
        state.images = [image(1, false), image(2, false), image(3, false), image(4, true)]
      }
      body = { data: sorted() }
    }
    if (path.endsWith('/images/1/primary') && method === 'PATCH') {
      state.images.forEach((item) => (item.is_primary = item.id === 1))
      body = { data: sorted() }
    }
    if (path.endsWith('/images/2') && method === 'DELETE') {
      state.images = state.images.filter((item) => item.id !== 2)
      body = { data: sorted() }
    }
    await route.fulfill({ json: body, headers })
  })
  return state
}
const files = [
  { name: 'first.png', mimeType: 'image/png', buffer: png },
  { name: 'second.png', mimeType: 'image/png', buffer: png },
]

test('storefront uses cover and carousel supports buttons keyboard thumbnails and mobile layout', async ({
  page,
}) => {
  const state = await mockImages(page)
  for (const width of [320, 768, 1440]) {
    await page.setViewportSize({ width, height: 900 })
    await page.goto('/stores/benta-door?product=door')
    const carousel = page.getByRole('region', { name: 'Gallery door images' })
    await expect(carousel).toBeVisible()
    await page.evaluate((width) => {
      document.documentElement.classList.toggle('dark', width === 768)
      document.documentElement.classList.toggle('theme-blue', width !== 1440)
    }, width)
    await expect(carousel.locator('img').first()).toHaveAttribute('src', /\/images\/2$/)
    await expect
      .poll(() =>
        carousel
          .locator('img')
          .first()
          .evaluate((node) => (node as HTMLImageElement).naturalWidth),
      )
      .toBeGreaterThan(0)
    await page.getByRole('button', { name: 'Next product image' }).click()
    await expect(carousel.locator('img').first()).toHaveAttribute('src', /\/images\/1$/)
    await carousel.focus()
    await page.keyboard.press('ArrowLeft')
    await expect(carousel.locator('img').first()).toHaveAttribute('src', /\/images\/2$/)
    await page.getByRole('button', { name: 'Show product image 2' }).click()
    await expect(carousel.locator('img').first()).toHaveAttribute('src', /\/images\/1$/)
    await expect
      .poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth))
      .toBe(true)
    await page.screenshot({ animations: 'disabled', path: `/tmp/product-carousel-${width}.png` })
    await page.getByRole('button', { name: 'Close product details' }).click()
  }
  expect(state.failures).toEqual([])
})

test('owner previews files changes cover removes image and saves multiple selected uploads', async ({
  page,
}) => {
  const state = await mockImages(page)
  for (const width of [320, 768, 1440]) {
    await page.setViewportSize({ width, height: 900 })
    await page.goto('/vendors/benta-door/products')
    await page.getByRole('button', { name: 'Edit Gallery door' }).click()
    const editor = page.getByRole('region', { name: 'Product images' })
    await expect(editor.getByText('Saving or loading images…')).toHaveCount(0)
    await expect(editor.getByRole('img')).toHaveCount(2)
    await expect
      .poll(() =>
        editor
          .getByRole('img')
          .first()
          .evaluate((node) => (node as HTMLImageElement).naturalWidth),
      )
      .toBeGreaterThan(0)
    await page.evaluate((width) => {
      document.documentElement.classList.toggle('dark', width === 768)
      document.documentElement.classList.toggle('theme-blue', width !== 1440)
    }, width)
    await editor.scrollIntoViewIfNeeded()
    await page.screenshot({
      animations: 'disabled',
      path: `/tmp/product-images-owner-${width}.png`,
    })
    await page.getByRole('button', { name: 'Cancel', exact: true }).click()
  }
  await page.getByRole('button', { name: 'Edit Gallery door' }).click()
  const editor = page.getByRole('region', { name: 'Product images' })
  await expect(editor.getByRole('img')).toHaveCount(2)
  await expect(editor.getByText('Saving or loading images…')).toHaveCount(0)
  await editor.getByRole('button', { name: 'Use in storefront' }).click()
  await expect(editor.getByRole('button', { name: 'Storefront image', exact: true })).toHaveCount(1)
  await expect(editor.getByText('Saving or loading images…')).toHaveCount(0)
  await editor.getByRole('button', { name: 'Remove', exact: true }).last().click()
  await expect(editor.getByRole('img')).toHaveCount(1)
  await page.getByLabel('Choose product images').setInputFiles(files)
  await editor.getByRole('button', { name: 'Use in storefront' }).last().click()
  await page.locator('form').getByRole('button', { name: 'Next', exact: true }).click()
  await page.locator('form').getByRole('button', { name: 'Next', exact: true }).click()
  await page.getByRole('button', { name: 'Update Product', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Edit Product', exact: true })).toHaveCount(0)
  expect(state.uploads).toHaveLength(1)
  expect(state.images.filter((item) => item.is_primary).map((item) => item.id)).toEqual([4])
  expect(state.failures).toEqual([])
})

test('new product retains selected files across steps and failed upload retries without duplicate creation', async ({
  page,
}) => {
  const state = await mockImages(page, true)
  await page.goto('/vendors/benta-door/products')
  await page.getByRole('button', { name: 'Add Product', exact: true }).click()
  await page.getByPlaceholder('e.g. Sony WH-1000XM5').fill('New gallery door')
  await page.getByPlaceholder('e.g. SONY-WH1000').fill('NEW-DOOR')
  await page.getByPlaceholder('sony-wh-1000xm5').fill('new-gallery-door')
  await page.getByLabel('Choose product images').setInputFiles(files)
  const editor = page.getByRole('region', { name: 'Product images' })
  await editor.getByRole('button', { name: 'Use in storefront' }).last().click()
  await page.locator('form').getByRole('button', { name: 'Next', exact: true }).click()
  await page.locator('form').getByRole('button', { name: 'Next', exact: true }).click()
  // Keep the existing pricing contract while testing the retry flow.
  await page.locator('input[type="number"]').first().fill('100')
  await page.getByRole('button', { name: 'Create Product', exact: true }).click()
  await expect(page.getByRole('alert')).toContainText('Product details saved.')
  await page.getByRole('button', { name: 'Create Product', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Add New Product' })).toHaveCount(0)
  expect(state.creates).toBe(1)
  expect(state.updates).toBe(1)
  expect(state.uploads).toHaveLength(2)
  expect(state.failures).toEqual([])
})
