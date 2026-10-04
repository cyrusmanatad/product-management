<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useProductStore } from '@/stores/products'
import BaseModal from '@/components/common/BaseModal.vue'
import {
  XMarkIcon,
  PlusIcon,
  TrashIcon,
  InformationCircleIcon,
  SwatchIcon,
  CircleStackIcon,
  ChevronRightIcon,
  ChevronLeftIcon,
} from '@heroicons/vue/24/outline'
import type { ProductForm, ProductOption } from '@/types/data-types'
import { useCategoryStore } from '@/stores/category'
import { ProductStatus } from '@/types/enum'
import { useAuthStore } from '@/stores/auth'
import axios from '@/utils/axios'
import ProductImagesEditor from '@/components/product/ProductImagesEditor.vue'

const props = defineProps<{
  mode: 'add' | 'edit'
}>()

const productStore = useProductStore()
const categoryStore = useCategoryStore()
const authStore = useAuthStore()

const canEditOrCreate = computed(() =>
  authStore.hasPermission([props.mode === 'add' ? 'create products' : 'edit products']),
)
const canManageImages = computed(() => authStore.hasPermission(['edit products']))
const imageEditor = ref<InstanceType<typeof ProductImagesEditor> | null>(null)
const savedProductId = ref<number | null>(null)
const saving = ref(false)
const imageBusy = ref(false)
const saveError = ref('')
const close = () => {
  if (!saving.value && !imageBusy.value) productStore.toggleModal(props.mode, false)
}
watch(
  () => productStore.modals[props.mode],
  () => {
    savedProductId.value = null
    saveError.value = ''
    activeTab.value = 'basic'
  },
)

const activeTab = ref<'basic' | 'attributes' | 'variants'>('basic')

const formData = ref<ProductForm>({
  base_sku: '',
  title: '',
  description: '',
  category_id: 0,
  uom: '',
  slug: '',
  stock: 0,
  price: 0.0,
  sale_price: 0.0,
  status: ProductStatus.INACTIVE,
  options: [],
  variants: [
    { id: 0, sku: '', price: 0, sale_price: 0, stock: 0, reserved_quantity: 0, attributes: {} },
  ],
})

const variantProductOption = ref<ProductOption[]>([])

const addOption = () => {
  variantProductOption.value.push({ name: '', values: [] })
}

const removeOption = (index: number) => {
  variantProductOption.value.splice(index, 1)
  generateVariants()
}

const addOptionValue = (index: number, value: string) => {
  if (!value.trim()) return
  if (!variantProductOption.value[index]?.values.includes(value.trim())) {
    variantProductOption.value[index]?.values.push(value.trim())
    generateVariants()
  }
}

const removeOptionValue = (optIndex: number, valIndex: number) => {
  variantProductOption.value[optIndex]?.values.splice(valIndex, 1)
  generateVariants()
}

const generateVariants = () => {
  const activeOptions = variantProductOption.value.filter((o) => o.name && o.values.length > 0)

  if (activeOptions.length === 0) {
    formData.value.variants = [
      {
        id: 1,
        sku: formData.value.base_sku.toUpperCase(),
        price: formData.value.price,
        sale_price: formData.value.sale_price,
        stock: formData.value.stock,
        reserved_quantity: 0,
        attributes: {},
      },
    ]
    return
  }

  const combinations = activeOptions.reduce<Record<string, string>[]>(
    (acc, opt) => {
      const next: Record<string, string>[] = []
      acc.forEach((a) => {
        opt.values.forEach((v) => {
          next.push({ ...a, [opt.name]: v })
        })
      })
      return next
    },
    [{}],
  )

  formData.value.variants = combinations.map((combo) => {
    const existing = formData.value.variants.find((v) =>
      Object.entries(combo).every(([key, value]) => v.attributes[key] === value),
    )

    const variantSku = `${formData.value.base_sku}-${Object.values(combo).join('-')}`
      .toUpperCase()
      .replace(/\s+/g, '-')

    return (
      existing || {
        id: 0,
        sku: variantSku,
        price: formData.value.price,
        sale_price: formData.value.sale_price,
        stock: 0,
        reserved_quantity: 0,
        attributes: combo as Record<string, string>,
      }
    )
  })
}

watch(
  [
    () => formData.value.base_sku,
    () => formData.value.price,
    () => formData.value.sale_price,
    () => formData.value.stock,
  ],
  () => {
    if (variantProductOption.value.filter((o) => o.name && o.values.length > 0).length === 0) {
      if (formData.value.variants[0]) {
        formData.value.variants[0].sku = formData.value.base_sku.toUpperCase()
        formData.value.variants[0].price = formData.value.price
        formData.value.variants[0].sale_price = formData.value.sale_price
        formData.value.variants[0].stock = formData.value.stock
      }
    }
  },
)

watch(
  [() => productStore.selectedProduct, () => productStore.modals[props.mode]],
  ([val]) => {
    activeTab.value = 'basic'
    if (val && props.mode === 'edit') {
      formData.value = {
        base_sku: val.base_sku,
        title: val.title,
        description: val.description,
        category_id: val.category_id,
        stock: val.stock,
        uom: val.uom,
        slug: val.slug,
        price: val.price,
        sale_price: val.sale_price,
        status: val.status,
        options: val.options || [],
        variants: (val.variants || []).map((variant) => ({
          ...variant,
          attributes: { ...variant.attributes },
        })),
      }
      // variantProductOption.value = JSON.parse(JSON.stringify(val.options || []))

      // productOptions.value = product.variants
      const attributeMap: Record<string, Set<string>> = {}

      formData.value.variants.forEach((item) => {
        Object.entries(item.attributes).forEach(([key, value]) => {
          if (!attributeMap[key]) {
            attributeMap[key] = new Set()
          }
          attributeMap[key].add(value)
        })
      })

      variantProductOption.value = Object.entries(attributeMap).map(([name, values]) => ({
        name,
        values: Array.from(values),
      }))

      console.log('formData:', formData)
    } else if (props.mode === 'add') {
      formData.value = {
        base_sku: '',
        title: '',
        description: '',
        category_id: 0,
        stock: 0,
        uom: '',
        slug: '',
        price: 0.0,
        sale_price: 0.0,
        status: ProductStatus.PUBLISHED,
        options: [],
        variants: [
          {
            id: 0,
            sku: '',
            price: 0,
            sale_price: 0,
            stock: 0,
            reserved_quantity: 0,
            attributes: {},
          },
        ],
      }
      variantProductOption.value = []
    }
  },
  { immediate: true },
)

watch(
  () => variantProductOption.value,
  (val) => {
    console.log('variantProductOption:', val)
  },
  { deep: true },
)

const submit = async () => {
  if (saving.value || imageBusy.value) return
  saving.value = true
  saveError.value = ''
  formData.value.options = variantProductOption.value.filter((o) => o.name && o.values.length > 0)
  try {
    let id = props.mode === 'edit' ? productStore.selectedProduct?.id : savedProductId.value
    if (id) await axios.put(`/api/v1/products/${id}`, { ...formData.value })
    else {
      const { data } = await axios.post('/api/v1/products', { ...formData.value })
      id = data.data.id as number
      savedProductId.value = id
    }
    await imageEditor.value?.uploadPending(id!)
    await productStore.fetchProducts(undefined, props.mode === 'add' ? 1 : undefined)
    await productStore.fetchStatistics()
    productStore.toggleModal(props.mode, false)
  } catch (cause) {
    const detail = axios.isAxiosError(cause) ? cause.response?.data : null
    saveError.value =
      Object.values(detail?.errors ?? {})
        .flat()
        .join(' ') ||
      detail?.message ||
      'Could not save the product. Try again.'
    if (savedProductId.value)
      saveError.value = `Product details saved. ${saveError.value} Retry to finish saving its images.`
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <BaseModal
    :show="mode === 'add' ? productStore.modals.add : productStore.modals.edit"
    @close="close"
  >
    <!-- Modal Header -->
    <div
      class="p-6 border-b dark:border-dark-border flex justify-between items-center bg-gray-50/50 dark:bg-slate-800/20"
    >
      <div>
        <h2 class="text-xl font-black text-gray-900 dark:text-white">
          {{ mode === 'add' ? 'Add New Product' : 'Edit Product' }}
        </h2>
        <p class="text-xs text-gray-500 dark:text-slate-400">
          {{
            mode === 'add'
              ? 'List a new item in your inventory'
              : 'Modifying: ' + productStore.selectedProduct?.title
          }}
        </p>
      </div>
      <button
        @click="close"
        class="p-2 hover:bg-gray-200 dark:hover:bg-slate-800 rounded-full transition text-gray-400 dark:text-slate-500"
        type="button"
      >
        <XMarkIcon class="w-5 h-5" />
      </button>
    </div>

    <!-- Tab Navigation -->
    <div
      class="px-2 sm:px-6 overflow-x-auto shrink-0 border-b dark:border-dark-border flex bg-white dark:bg-dark-card sticky top-0 z-10"
    >
      <button
        v-for="tab in ['basic', 'attributes', 'variants'] as const"
        :key="tab"
        @click="activeTab = tab"
        type="button"
        class="px-3 sm:px-4 py-4 text-xs font-black uppercase tracking-widest transition-all relative flex items-center gap-2"
        :class="[
          activeTab === tab
            ? 'text-teal-600 dark:text-teal-400'
            : 'text-gray-400 hover:text-gray-600 dark:text-slate-500 dark:hover:text-slate-300',
        ]"
      >
        <component
          :is="
            tab === 'basic'
              ? InformationCircleIcon
              : tab === 'attributes'
                ? SwatchIcon
                : CircleStackIcon
          "
          class="w-4 h-4"
        />
        {{ tab }}
        <div
          v-if="activeTab === tab"
          class="absolute bottom-0 left-0 w-full h-0.5 bg-teal-500 dark:bg-teal-400"
        ></div>
      </button>
    </div>

    <form
      class="p-6 space-y-6 overflow-y-auto custom-scrollbar max-h-[65vh]"
      @submit.prevent="submit"
    >
      <!-- Tab 1: General Information -->
      <div
        v-if="activeTab === 'basic'"
        class="space-y-6 transition-all duration-300 animate-in fade-in slide-in-from-bottom-2"
      >
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="md:col-span-2">
            <label
              class="block text-[10px] font-black text-gray-400 dark:text-slate-500 uppercase tracking-widest mb-1.5"
              >Product Name</label
            >
            <input
              v-model="formData.title"
              type="text"
              placeholder="e.g. Sony WH-1000XM5"
              class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-dark-border rounded-xl dark:text-white focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none text-sm font-medium transition"
            />
          </div>
          <div>
            <label
              class="block text-[10px] font-black text-gray-400 dark:text-slate-500 uppercase tracking-widest mb-1.5"
              >Base SKU</label
            >
            <input
              v-model="formData.base_sku"
              type="text"
              placeholder="e.g. SONY-WH1000"
              class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-dark-border rounded-xl dark:text-white focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none text-sm font-medium transition"
            />
          </div>
        </div>

        <div>
          <label
            class="block text-[10px] font-black text-gray-400 dark:text-slate-500 uppercase tracking-widest mb-1.5"
            >Description</label
          >
          <textarea
            v-model="formData.description"
            rows="4"
            placeholder="Tell something about the product..."
            class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-dark-border rounded-xl dark:text-white focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none text-sm font-medium transition resize-none"
          ></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label
              class="block text-[10px] font-black text-gray-400 dark:text-slate-500 uppercase tracking-widest mb-1.5"
              >Category</label
            >
            <select
              v-model="formData.category_id"
              class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-dark-border rounded-xl dark:text-white focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none text-sm font-medium transition appearance-none"
            >
              <option :value="0">Select category</option>
              <option
                v-for="category in categoryStore.categories"
                :key="category.id"
                :value="category.id"
              >
                {{ category.name }}
              </option>
            </select>
          </div>
          <div>
            <label
              class="block text-[10px] font-black text-gray-400 dark:text-slate-500 uppercase tracking-widest mb-1.5"
              >Slug</label
            >
            <input
              v-model="formData.slug"
              type="text"
              placeholder="sony-wh-1000xm5"
              class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-dark-border rounded-xl dark:text-white focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none text-sm font-medium transition"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label
              class="block text-[10px] font-black text-gray-400 dark:text-slate-500 uppercase tracking-widest mb-1.5"
              >UOM</label
            >
            <input
              v-model="formData.uom"
              type="text"
              placeholder="e.g. Piece"
              class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-dark-border rounded-xl dark:text-white focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none text-sm font-medium transition"
            />
          </div>
          <div>
            <label
              class="block text-[10px] font-black text-gray-400 dark:text-slate-500 uppercase tracking-widest mb-1.5"
              >Listing Status</label
            >
            <select
              v-model="formData.status"
              class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-dark-border rounded-xl dark:text-white focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none text-sm font-medium transition appearance-none"
            >
              <option
                v-for="status in productStore.statuses"
                :value="status.code"
                :key="status.code"
              >
                {{ status.label }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <ProductImagesEditor
        v-if="productStore.modals[mode] && canManageImages"
        v-show="activeTab === 'basic'"
        ref="imageEditor"
        :product-id="mode === 'edit' ? (productStore.selectedProduct?.id ?? null) : null"
        :disabled="saving"
        @busy="imageBusy = $event"
      />
      <p v-if="saveError" role="alert" class="text-xs text-red-500">{{ saveError }}</p>

      <!-- Tab 2: Attributes -->
      <div
        v-if="activeTab === 'attributes'"
        class="space-y-6 transition-all duration-300 animate-in fade-in slide-in-from-bottom-2"
      >
        <div
          class="bg-teal-50/50 dark:bg-teal-900/10 p-4 rounded-2xl border border-teal-500 dark:border-dark-border flex gap-4"
        >
          <SwatchIcon class="w-6 h-6 text-teal-600 dark:text-teal-400 shrink-0" />
          <div>
            <h4
              class="text-xs font-black text-teal-900 dark:text-teal-100 uppercase tracking-widest"
            >
              Define Options
            </h4>
            <p class="text-[11px] text-teal-700 dark:text-teal-300 mt-0.5">
              Define attributes like Color or Size. If left empty, a single base variant will be
              maintained.
            </p>
          </div>
        </div>

        <div class="space-y-4">
          <div
            v-for="(option, optIndex) in variantProductOption"
            :key="optIndex"
            class="p-4 bg-gray-50 dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-dark-border space-y-4"
          >
            <div class="flex gap-4 items-end">
              <div class="flex-1">
                <label class="block text-[9px] font-black text-gray-400 uppercase mb-1.5"
                  >Option Name</label
                >
                <input
                  v-model="option.name"
                  @change="generateVariants"
                  type="text"
                  placeholder="e.g. Color"
                  class="w-full px-4 py-2.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-dark-border rounded-xl dark:text-white text-xs outline-none focus:ring-2 focus:ring-teal-500/20 transition"
                />
              </div>
              <button
                @click="removeOption(optIndex)"
                type="button"
                class="p-2.5 text-red-400 hover:bg-red-50 dark:hover:bg-red-900/10 rounded-xl transition"
              >
                <TrashIcon class="w-5 h-5" />
              </button>
            </div>

            <div class="space-y-2">
              <label class="block text-[9px] font-black text-gray-400 uppercase">Values</label>
              <div class="flex flex-wrap gap-2 items-center">
                <div
                  v-for="(val, valIndex) in option.values"
                  :key="valIndex"
                  class="flex items-center gap-1.5 px-3 py-1.5 bg-white dark:bg-slate-800 text-gray-700 dark:text-slate-200 rounded-lg text-xs font-bold border border-gray-200 dark:border-dark-border shadow-sm"
                >
                  {{ val }}
                  <button
                    @click="removeOptionValue(optIndex, valIndex)"
                    type="button"
                    class="text-gray-400 hover:text-red-500 transition"
                  >
                    <XMarkIcon class="w-3.5 h-3.5" />
                  </button>
                </div>
                <input
                  @keydown.enter.prevent="
                    (e) => {
                      addOptionValue(optIndex, (e.target as HTMLInputElement).value)
                      ;(e.target as HTMLInputElement).value = ''
                    }
                  "
                  type="text"
                  placeholder="Press Enter to add..."
                  class="bg-transparent border-none outline-none text-xs text-teal-600 dark:text-teal-400 font-bold placeholder:text-gray-400 w-40"
                />
              </div>
            </div>
          </div>

          <button
            @click="addOption"
            type="button"
            class="w-full py-4 border-2 border-dashed border-gray-200 dark:border-dark-border rounded-2xl flex items-center justify-center gap-2 text-gray-400 hover:text-teal-600 hover:border-teal-500 hover:bg-teal-50/50 dark:hover:bg-teal-900/10 transition group"
          >
            <PlusIcon class="w-5 h-5 transition group-hover:scale-110" />
            <span class="text-xs font-black uppercase tracking-widest">Add New Attribute</span>
          </button>
        </div>
      </div>

      <!-- Tab 3: Variants & Inventory -->
      <div
        v-if="activeTab === 'variants'"
        class="space-y-6 transition-all duration-300 animate-in fade-in slide-in-from-bottom-2"
      >
        <div
          v-if="variantProductOption.filter((o) => o.name && o.values.length > 0).length === 0"
          class="p-4 bg-orange-50/50 dark:bg-orange-900/10 border border-orange-100 dark:border-orange-900/30 rounded-2xl space-y-3"
        >
          <h4
            class="text-xs font-black text-orange-900 dark:text-orange-100 uppercase tracking-widest"
          >
            Single Variant Setup
          </h4>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-[9px] font-black text-gray-400 uppercase mb-1.5"
                >Selling Price ($)</label
              >
              <input
                v-model="formData.price"
                type="number"
                step="any"
                class="w-full px-4 py-2.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-dark-border rounded-xl dark:text-white text-xs outline-none focus:ring-2 focus:ring-teal-500/20 transition"
              />
            </div>
            <div>
              <label class="block text-[9px] font-black text-gray-400 uppercase mb-1.5"
                >Cost Price ($)</label
              >
              <input
                v-model="formData.sale_price"
                type="number"
                step="any"
                class="w-full px-4 py-2.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-dark-border rounded-xl dark:text-white text-xs outline-none focus:ring-2 focus:ring-teal-500/20 transition"
              />
            </div>
            <div>
              <label class="block text-[9px] font-black text-gray-400 uppercase mb-1.5"
                >Stock</label
              >
              <input
                v-model="formData.stock"
                type="number"
                class="w-full px-4 py-2.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-dark-border rounded-xl dark:text-white text-xs outline-none focus:ring-2 focus:ring-teal-500/20 transition"
              />
            </div>
          </div>
        </div>

        <div
          v-if="formData.variants.length > 0"
          class="overflow-hidden rounded-2xl border border-gray-100 dark:border-dark-border shadow-sm"
        >
          <table class="w-full text-left text-[11px]">
            <thead
              class="bg-gray-50 dark:bg-slate-800/50 text-gray-400 dark:text-slate-500 font-black uppercase tracking-widest"
            >
              <tr>
                <th
                  v-if="
                    formData.variants[0] && Object.keys(formData.variants[0].attributes).length > 0
                  "
                  class="px-4 py-3.5"
                >
                  Variant
                </th>
                <th class="px-4 py-3.5">SKU</th>
                <th class="px-4 py-3.5 w-24 text-right">Price ($)</th>
                <th class="px-4 py-3.5 w-24 text-right">Sale ($)</th>
                <th class="px-4 py-3.5 w-24 text-right">Stock</th>
              </tr>
            </thead>
            <tbody
              class="divide-y divide-gray-100 dark:divide-dark-border bg-white dark:bg-slate-900"
            >
              <tr
                v-for="(v, idx) in formData.variants"
                :key="idx"
                class="hover:bg-gray-50/50 dark:hover:bg-slate-800/30 transition"
              >
                <td
                  v-if="Object.keys(v.attributes).length > 0"
                  class="px-4 py-3.5 font-bold text-gray-700 dark:text-slate-300"
                >
                  <div class="flex flex-wrap gap-1">
                    <span
                      v-for="(val, key) in v.attributes"
                      :key="key"
                      class="bg-gray-100 dark:bg-slate-800 px-1.5 py-0.5 rounded uppercase text-[9px]"
                      >{{ val }}</span
                    >
                  </div>
                </td>
                <td class="px-4 py-3.5">
                  <input
                    v-model="v.sku"
                    type="text"
                    class="w-full bg-transparent border-none outline-none text-gray-600 dark:text-slate-400 focus:text-teal-600 font-mono"
                  />
                </td>
                <td class="px-4 py-3.5">
                  <input
                    v-model="v.price"
                    type="number"
                    step="any"
                    class="w-full bg-transparent border-none outline-none text-gray-600 dark:text-slate-400 font-black text-right focus:text-teal-600"
                  />
                </td>
                <td class="px-4 py-3.5">
                  <input
                    v-model="v.sale_price"
                    type="number"
                    step="any"
                    class="w-full bg-transparent border-none outline-none text-gray-600 dark:text-slate-400 font-black text-right focus:text-teal-600"
                  />
                </td>
                <td class="px-4 py-3.5">
                  <input
                    v-model="v.stock"
                    type="number"
                    class="w-full bg-transparent border-none outline-none text-gray-600 dark:text-slate-400 font-black text-right focus:text-teal-600"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Footer Actions -->
      <div class="pt-6 flex justify-between gap-3 border-t dark:border-dark-border mt-auto">
        <div class="flex gap-2">
          <button
            v-if="activeTab !== 'basic'"
            @click="activeTab = activeTab === 'variants' ? 'attributes' : 'basic'"
            type="button"
            class="px-4 py-2.5 text-xs font-black text-gray-500 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 rounded-xl transition flex items-center gap-2"
          >
            <ChevronLeftIcon class="w-4 h-4" /> Back
          </button>
        </div>

        <div class="flex gap-3">
          <button
            @click="close"
            class="px-5 py-2.5 text-xs font-black text-gray-400 dark:text-slate-500 hover:bg-gray-100 dark:hover:bg-slate-800 rounded-xl transition"
            type="button"
          >
            Cancel
          </button>

          <button
            v-if="activeTab !== 'variants'"
            @click="activeTab = activeTab === 'basic' ? 'attributes' : 'variants'"
            type="button"
            class="px-5 py-2.5 text-xs font-black text-white bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 rounded-xl transition flex items-center gap-2 shadow-lg shadow-slate-500/10"
          >
            Next <ChevronRightIcon class="w-4 h-4" />
          </button>

          <button
            v-else-if="canEditOrCreate"
            type="submit"
            class="px-6 py-2.5 text-xs font-black text-white bg-teal-500 hover:bg-teal-600 rounded-xl shadow-lg shadow-teal-500/20 transition active:scale-95 disabled:opacity-50"
            :disabled="saving || imageBusy"
          >
            {{ saving ? 'Saving…' : mode === 'add' ? 'Create Product' : 'Update Product' }}
          </button>
        </div>
      </div>
    </form>
  </BaseModal>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #e2e8f0;
  border-radius: 10px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
  background: #334155;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-in {
  animation: fadeIn 0.3s ease-out forwards;
}
</style>
