<script setup lang="ts">
import StatGrid from '@/components/product/StatGrid.vue'
import ProductTable from '@/components/product/ProductTable.vue'
import ProductModal from '@/components/product/modals/ProductModal.vue'
import DeleteModal from '@/components/product/modals/DeleteModal.vue'
import AppHeader from '@/components/layouts/AppHeader.vue'
import BaseModal from '@/components/common/BaseModal.vue'
import FormField from '@/components/ui/FormField.vue'
import ActionButton from '@/components/ui/ActionButton.vue'
import InlineMessage from '@/components/ui/InlineMessage.vue'
import { useToastStore } from '@/stores/toast'
import type { ActionItem, DropdownAction } from '@/types/header-types'
import { useProductStore } from '@/stores/products'
import {
  ArrowDownTrayIcon,
  ArrowUpTrayIcon,
  ListBulletIcon,
  PlusIcon,
  TrashIcon,
  TagIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'
import axios from '@/utils/axios'
import { onMounted, ref } from 'vue'
import { useCategoryStore } from '@/stores/category'
import { useAuthStore } from '@/stores/auth'

const categoryName = ref(''),
  categoryError = ref('')
const categoryModal = ref(false),
  categorySaving = ref(false)
const toast = useToastStore()
async function addCategory() {
  categoryError.value = ''
  categorySaving.value = true
  try {
    await axios.post('/api/v1/categories', { name: categoryName.value })
    categoryStore.clearCategories()
    await categoryStore.fetchCategories()
    categoryName.value = ''
    categoryError.value = ''
    categoryModal.value = false
    toast.addToast('Category added successfully', 'success')
  } catch {
    categoryError.value = 'Category could not be added. Choose a unique name.'
  } finally {
    categorySaving.value = false
  }
}
const productStore = useProductStore()
const authStore = useAuthStore()
const categoryStore = useCategoryStore()

const actionItems: ActionItem[] = []

const dropdownAction: DropdownAction = {
  key: 'actions',
  type: 'dropdown',
  label: 'Actions',
  items: [
    {
      label: 'Categories',
      icon: ListBulletIcon,
      handler: () => {
        categoryError.value = ''
        categoryModal.value = true
      },
    },
    {
      label: 'Export CSV',
      icon: ArrowDownTrayIcon,
      handler: () => productStore.exportCsv(),
    },
    {
      label: 'Import Bulk',
      icon: ArrowUpTrayIcon,
      handler: () => productStore.importBulk(),
    },
  ],
}

if (authStore.hasPermission(['create products'])) {
  actionItems.push({
    key: 'category',
    type: 'button',
    label: 'Add category',
    icon: TagIcon,
    variant: 'secondary',
    handler: () => {
      categoryError.value = ''
      categoryModal.value = true
    },
  })
  actionItems.push({
    key: 'add',
    type: 'button',
    label: 'Add Product',
    icon: PlusIcon,
    variant: 'primary',
    handler: () => productStore.toggleModal('add', true),
  })
}

if (authStore.hasPermission(['delete all products'])) {
  dropdownAction.items.push({ label: '', divider: true })
  dropdownAction.items.push({
    label: 'Delete All',
    icon: TrashIcon,
    danger: true,
    handler: () => productStore.deleteAll(),
  })
}

actionItems.push(dropdownAction)

onMounted(async () => {
  await Promise.all([productStore.fetchStatistics(), categoryStore.fetchCategories()])
})
</script>

<template>
  <div class="p-4 md:p-8">
    <AppHeader
      title="Products List"
      :breadcrumb="[{ label: 'Products' }]"
      :action-items="actionItems"
    >
      <template #description> Manage your inventory of this store’s items </template>
    </AppHeader>

    <StatGrid :stats="productStore.stats" />
    <ProductTable />

    <!-- Modals -->
    <BaseModal :show="categoryModal" @close="categoryModal = false">
      <div
        class="p-6 bg-gray-50/50 dark:bg-slate-800/20 border-b border-gray-100 dark:border-dark-border flex items-center justify-between gap-4"
      >
        <div>
          <h2 class="text-xl font-black text-gray-900 dark:text-white">Store categories</h2>
          <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">
            Organize the products in this store.
          </p>
        </div>
        <button
          type="button"
          aria-label="Close categories"
          @click="categoryModal = false"
          class="p-3 text-gray-400 rounded-xl hover:bg-gray-100 dark:hover:bg-slate-800 transition"
        >
          <XMarkIcon class="w-5 h-5" />
        </button>
      </div>
      <div class="p-6 space-y-5 overflow-y-auto custom-scrollbar">
        <div class="flex flex-wrap gap-2">
          <span
            v-for="category in categoryStore.categories"
            :key="category.id"
            class="px-3 py-1.5 bg-gray-50 dark:bg-slate-800/50 border border-gray-200 dark:border-dark-border rounded-lg text-xs font-bold text-gray-600 dark:text-slate-300"
            >{{ category.name }}</span
          >
          <p
            v-if="!categoryStore.categories.length"
            class="text-sm text-gray-500 dark:text-slate-400"
          >
            No categories yet.
          </p>
        </div>
        <form
          v-if="authStore.hasPermission(['create products'])"
          @submit.prevent="addCategory"
          class="space-y-5"
        >
          <FormField
            id="category-name"
            v-model="categoryName"
            label="New category"
            :icon="TagIcon"
            required
            maxlength="255"
            placeholder="Category name"
            :disabled="categorySaving"
          />
          <InlineMessage v-if="categoryError" kind="error">{{ categoryError }}</InlineMessage>
          <div class="flex flex-wrap justify-end gap-3 pt-2">
            <ActionButton variant="secondary" @click="categoryModal = false">Cancel</ActionButton
            ><ActionButton type="submit" :loading="categorySaving">{{
              categorySaving ? 'Adding category...' : 'Add category'
            }}</ActionButton>
          </div>
        </form>
      </div>
    </BaseModal>
    <ProductModal mode="add" />
    <ProductModal mode="edit" />
    <DeleteModal />
  </div>
</template>
