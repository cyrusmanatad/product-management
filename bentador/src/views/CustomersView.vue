<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import {
  UserPlusIcon,
  ChevronDownIcon,
  MagnifyingGlassIcon,
  CalendarIcon,
  PencilSquareIcon,
  ShieldExclamationIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'
import BaseModal from '@/components/common/BaseModal.vue'
import AppHeader from '@/components/layouts/AppHeader.vue'
import type { ActionItem } from '@/types/header-types'
import { useAuthStore } from '@/stores/auth'
import { useCustomerStore } from '@/stores/customer'
import type { Customer } from '@/types/customer-types'
import Pagination from '@/components/common/Pagination.vue'
import { storeToRefs } from 'pinia'
import { useDebounceFn } from '@vueuse/core'

const customerStore = useCustomerStore()

const authStore = useAuthStore()
const canEditCustomer = () =>
  authStore.hasPermission(['view customers']) && authStore.hasPermission(['edit customers'])
const statusError = ref('')
const statusSuccess = ref('')
const editStatus = ref('Active')

const addModal = ref(false)
const editModal = ref(false)
const deleteModal = ref(false)

const selectedCustomer = ref<Customer | null>(null)

const statusOpen = ref(false)
const selectedStatus = ref('All Status')

const dateRangeOpen = ref(false)
const dateFrom = ref('')
const dateTo = ref('')

const handleStatus = (status: string) => {
  selectedStatus.value = status
  statusOpen.value = false
}

const handleClearDateRange = () => {
  dateFrom.value = ''
  dateTo.value = ''
  dateRangeOpen.value = false
}

const handleEditModal = (customer: Customer) => {
  selectedCustomer.value = customer
  editStatus.value = customer.status
  statusError.value = ''
  editModal.value = true
}

const handleDeleteModal = (customer: Customer) => {
  selectedCustomer.value = customer
  statusError.value = ''
  deleteModal.value = true
}

const saveStatus = async (blocked: boolean) => {
  if (!selectedCustomer.value || customerStore.loading) return
  statusError.value = ''
  statusSuccess.value = ''
  try {
    await customerStore.updateCustomer(selectedCustomer.value.id, blocked)
    editModal.value = false
    deleteModal.value = false
    statusSuccess.value = blocked
      ? 'Customer blocked at this store.'
      : 'Customer unblocked at this store.'
    await Promise.all([customerStore.fetchCustomers(), customerStore.fetchStatistics()])
  } catch {
    statusError.value = 'Could not update the customer. Please retry.'
  }
}
const handleUpdateStatus = () => {
  if (editStatus.value !== selectedCustomer.value?.status) {
    editModal.value = false
    deleteModal.value = true
  } else {
    editModal.value = false
  }
}

const actionItems: ActionItem[] = [
  {
    key: 'add-customer',
    type: 'button',
    label: 'Add Customer',
    icon: UserPlusIcon,
    variant: 'primary',
    handler: () => console.log('Add Customer'),
  },
]

const { search } = storeToRefs(customerStore)

const onPageChange = (page: number) => {
  customerStore.fetchCustomers(customerStore.search, page)
}

const debouncedFetch = useDebounceFn(() => {
  customerStore.fetchCustomers(search.value, 1, {
    status: '',
  })
}, 400)

watch(search, () => {
  debouncedFetch()
})

onMounted(async () => {
  await Promise.all([customerStore.fetchCustomers(), customerStore.fetchStatistics()])
})
</script>

<template>
  <div class="p-4 md:p-8">
    <!-- HEADER -->
    <AppHeader
      title="Customer Directory"
      :breadcrumb="[{ label: 'Customer' }]"
      :action-items="actionItems"
    >
      <template #description>
        Managing
        <span class="text-teal-600 dark:text-teal-400 font-bold">{{
          customerStore.totalCustomers === null
            ? '—'
            : customerStore.totalCustomers.toLocaleString('en-US')
        }}</span>
        store customers
      </template>
    </AppHeader>

    <p
      v-if="customerStore.statisticsError"
      role="alert"
      class="mb-4 text-sm text-red-600 dark:text-red-400"
    >
      {{ customerStore.statisticsError }}
      <button type="button" class="underline" @click="customerStore.fetchStatistics()">
        Retry
      </button>
    </p>
    <p v-if="statusSuccess" role="status" class="mb-4 text-sm text-teal-700 dark:text-teal-300">
      {{ statusSuccess }}
    </p>
    <!-- STATS GRID -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
      <div
        v-for="stat in customerStore.stats"
        :key="stat.label"
        class="bg-white dark:bg-dark-card p-6 rounded-2xl border border-gray-100 dark:border-dark-border shadow-sm hover:shadow-md transition-shadow group"
      >
        <p class="text-sm text-gray-500 dark:text-slate-400 font-medium mb-1">{{ stat.label }}</p>
        <div class="flex items-end justify-between">
          <h3 class="text-2xl font-black text-gray-900 dark:text-white">{{ stat.val }}</h3>
          <div
            class="h-1.5 w-10 rounded-full mb-2 transition-all group-hover:w-14"
            :class="stat.color"
          ></div>
        </div>
        <p class="text-[11px] text-gray-400 mt-2">{{ stat.desc }}</p>
      </div>
    </div>

    <!-- TABLE SECTION -->
    <div
      class="bg-white dark:bg-dark-card rounded-2xl border border-gray-200 dark:border-dark-border shadow-sm overflow-hidden"
    >
      <!-- Filter Bar -->
      <div
        class="p-4 border-b border-gray-100 dark:border-dark-border flex flex-col lg:flex-row gap-4 justify-between bg-gray-50/30 dark:bg-slate-800/20"
      >
        <div class="relative max-w-md w-full">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" />
          <input
            type="text"
            v-model="search"
            placeholder="Search by name, email..."
            class="w-full pl-10 pr-4 py-2 bg-white dark:bg-slate-900 border border-gray-200 dark:border-dark-border rounded-xl text-sm dark:text-white focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition"
          />
        </div>
        <div class="flex flex-wrap gap-2 pb-1 lg:pb-0">
          <!-- Status Dropdown -->
          <div class="relative">
            <button
              @click.stop="statusOpen = !statusOpen"
              type="button"
              class="whitespace-nowrap px-4 py-2 border border-gray-200 dark:border-dark-border rounded-xl text-xs font-bold bg-white dark:bg-slate-900 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 transition flex items-center gap-2"
            >
              <span>{{ selectedStatus }}</span>
              <ChevronDownIcon
                class="w-3 h-3 text-gray-400 transition-transform"
                :class="statusOpen && 'rotate-180'"
              />
            </button>
            <Transition
              enter-active-class="transition ease-out duration-100"
              enter-from-class="opacity-0 scale-95"
              enter-to-class="opacity-100 scale-100"
            >
              <div
                v-if="statusOpen"
                v-outside-click="() => (statusOpen = false)"
                class="absolute left-0 mt-2 w-44 bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border shadow-xl rounded-xl z-[70] py-1"
              >
                <button
                  v-for="status in ['All Status', 'Active', 'Blocked']"
                  :key="status"
                  @click="handleStatus(status)"
                  class="w-full text-left px-4 py-2 text-sm text-gray-600 dark:text-slate-400 hover:bg-teal-50 dark:hover:bg-teal-500/10 transition"
                  type="button"
                >
                  {{ status }}
                </button>
              </div>
            </Transition>
          </div>

          <!-- Joined Date Dropdown -->
          <div class="relative">
            <button
              @click.stop="dateRangeOpen = !dateRangeOpen"
              type="button"
              class="whitespace-nowrap px-4 py-2 border border-gray-200 dark:border-dark-border rounded-xl text-xs font-bold bg-white dark:bg-slate-900 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 transition flex items-center gap-2"
            >
              <CalendarIcon class="w-3 h-3 text-teal-500 dark:text-teal-400" />
              <span>{{ dateFrom && dateTo ? dateFrom + ' - ' + dateTo : 'Joined Date' }}</span>
              <ChevronDownIcon
                class="w-3 h-3 text-gray-400 transition-transform"
                :class="dateRangeOpen && 'rotate-180'"
              />
            </button>
            <Transition
              enter-active-class="transition ease-out duration-100"
              enter-from-class="opacity-0 scale-95"
              enter-to-class="opacity-100 scale-100"
            >
              <div
                v-if="dateRangeOpen"
                v-outside-click="() => (dateRangeOpen = false)"
                class="absolute right-0 mt-2 p-4 bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border shadow-2xl rounded-2xl z-[70] min-w-[280px]"
              >
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label
                      class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1"
                      >From</label
                    >
                    <input
                      type="date"
                      v-model="dateFrom"
                      class="w-full px-2 py-2 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-dark-border rounded-lg text-xs dark:text-white focus:ring-2 focus:ring-teal-500/20 outline-none transition"
                    />
                  </div>
                  <div>
                    <label
                      class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1"
                      >To</label
                    >
                    <input
                      type="date"
                      v-model="dateTo"
                      class="w-full px-2 py-2 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-dark-border rounded-lg text-xs dark:text-white focus:ring-2 focus:ring-teal-500/20 outline-none transition"
                    />
                  </div>
                </div>
                <div
                  class="mt-4 flex justify-between items-center border-t dark:border-dark-border pt-3"
                >
                  <button
                    @click="handleClearDateRange"
                    type="button"
                    class="text-[10px] font-bold text-red-500 hover:text-red-700 transition"
                  >
                    Clear
                  </button>
                  <button
                    @click="dateRangeOpen = false"
                    type="button"
                    class="px-4 py-2 bg-teal-500 text-white text-[10px] font-black rounded-lg hover:bg-teal-600 transition"
                  >
                    Apply
                  </button>
                </div>
              </div>
            </Transition>
          </div>
        </div>
      </div>

      <!-- Scrollable Table -->
      <div class="overflow-x-auto custom-scrollbar">
        <table class="w-full text-left min-w-[1000px]">
          <thead>
            <tr
              class="bg-gray-50/50 dark:bg-slate-800/20 text-[11px] uppercase tracking-widest text-gray-400 dark:text-slate-500 font-bold border-b border-gray-100 dark:border-dark-border"
            >
              <th class="px-6 py-4">Customer</th>
              <th class="px-6 py-4">Contact Info</th>
              <th class="px-6 py-4">Joined Date</th>
              <th class="px-6 py-4 text-center">Last Login</th>
              <th class="px-6 py-4 text-center">Total Orders</th>
              <th class="px-6 py-4">Lifetime Value</th>
              <th class="px-6 py-4">Status</th>
              <th class="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-dark-border">
            <tr
              v-for="customer in customerStore.customers"
              :key="customer.email"
              class="hover:bg-gray-50/80 dark:hover:bg-slate-800/30 transition-colors group"
            >
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <img
                    :src="`https://ui-avatars.com/api/?name=${customer.name}&background=random&color=fff`"
                    class="w-10 h-10 rounded-full border-2 border-white dark:border-slate-700 shadow-sm"
                    alt="Avatar"
                  />
                  <div>
                    <p class="text-sm font-bold text-gray-900 dark:text-white">
                      {{ customer.name }}
                    </p>
                    <p class="text-[11px] text-gray-400 dark:text-slate-500">
                      ID: #{{ customer.id }}
                    </p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4">
                <p class="text-xs font-medium text-gray-600 dark:text-slate-400">
                  {{ customer.email }}
                </p>
                <p class="text-[10px] text-gray-400">Phone unavailable</p>
              </td>
              <td class="px-6 py-4 text-xs text-gray-500 dark:text-slate-400">
                {{ customer.joined }}
              </td>
              <td class="px-6 py-4 text-xs text-gray-500 dark:text-slate-400">
                {{ customer.last_login_at ?? 'Unavailable' }}
              </td>
              <td class="px-6 py-4 text-center font-bold text-gray-700 dark:text-slate-300">
                {{ customer.orders }}
              </td>
              <td class="px-6 py-4 font-black text-gray-900 dark:text-white">
                {{ customer.spent }}
              </td>
              <td class="px-6 py-4">
                <span
                  class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider flex items-center gap-1.5 w-fit"
                  :class="
                    customer.color === 'green'
                      ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400'
                      : 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400'
                  "
                >
                  <span
                    class="w-1.5 h-1.5 rounded-full"
                    :class="
                      customer.color === 'green'
                        ? 'bg-green-600 dark:bg-green-400'
                        : 'bg-red-600 dark:bg-red-400'
                    "
                  ></span>
                  {{ customer.status }}
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button
                    v-if="canEditCustomer()"
                    :aria-label="`Edit status for ${customer.name}`"
                    @click="handleEditModal(customer)"
                    class="p-2 hover:bg-teal-50 dark:hover:bg-teal-500/10 text-gray-400 dark:text-slate-500 hover:text-teal-600 dark:hover:text-teal-400 rounded-lg transition"
                    type="button"
                  >
                    <PencilSquareIcon class="w-4 h-4" />
                  </button>
                  <button
                    v-if="canEditCustomer()"
                    :aria-label="`${customer.status === 'Blocked' ? 'Unblock' : 'Block'} ${customer.name}`"
                    @click="handleDeleteModal(customer)"
                    class="p-2 hover:bg-red-50 dark:hover:bg-red-500/10 text-gray-400 dark:text-slate-500 hover:text-red-600 dark:hover:text-red-400 rounded-lg transition"
                    type="button"
                  >
                    <ShieldExclamationIcon class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <Pagination
        v-if="customerStore.meta"
        :meta="customerStore.meta"
        @page-change="onPageChange"
      />
    </div>

    <!-- Modals -->
    <BaseModal :show="addModal" @close="addModal = false">
      <div
        class="p-6 border-b dark:border-dark-border flex justify-between items-center bg-gray-50/50 dark:bg-slate-800/20"
      >
        <div>
          <h2 class="text-xl font-black text-gray-900 dark:text-white">Add New Customer</h2>
          <p class="text-xs text-gray-500 dark:text-slate-400">Create a new customer profile</p>
        </div>
        <button
          @click="addModal = false"
          class="p-2 hover:bg-gray-200 dark:hover:bg-slate-800 rounded-full transition text-gray-400 dark:text-slate-500"
          type="button"
        >
          <XMarkIcon class="w-5 h-5" />
        </button>
      </div>
      <p v-if="statusError" role="alert" class="px-6 pt-4 text-sm text-red-600">
        {{ statusError }}
      </p>
      <form class="p-6 space-y-5 overflow-y-auto custom-scrollbar">
        <div>
          <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5"
            >Full Name</label
          >
          <input
            type="text"
            placeholder="Customer name"
            class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-dark-border rounded-xl dark:text-white focus:ring-2 focus:ring-teal-500/20 outline-none transition"
          />
        </div>
        <div>
          <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5"
            >Email Address</label
          >
          <input
            type="email"
            placeholder="name@email.com"
            class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-dark-border rounded-xl dark:text-white focus:ring-2 focus:ring-teal-500/20 outline-none transition"
          />
        </div>
      </form>
      <div
        class="p-6 bg-gray-50 dark:bg-slate-800/20 border-t dark:border-dark-border flex justify-end gap-3"
      >
        <button
          @click="addModal = false"
          class="px-5 py-2.5 text-sm font-bold text-gray-500 dark:text-slate-400 hover:bg-gray-200 rounded-xl transition"
          type="button"
        >
          Cancel
        </button>
        <button
          @click="addModal = false"
          class="px-6 py-2.5 text-sm font-bold text-white bg-teal-500 hover:bg-teal-600 rounded-xl shadow-lg transition active:scale-95"
          type="button"
        >
          Create Profile
        </button>
      </div>
    </BaseModal>

    <BaseModal :show="editModal" @close="editModal = false">
      <div
        class="p-6 border-b dark:border-dark-border flex justify-between items-center bg-gray-50/50 dark:bg-slate-800/20"
      >
        <div>
          <h2 class="text-xl font-black text-gray-900 dark:text-white">Edit Customer</h2>
          <p
            class="text-[11px] text-teal-600 dark:text-teal-400 font-bold uppercase tracking-wider"
          >
            {{ selectedCustomer?.name }}
          </p>
        </div>
        <button
          @click="editModal = false"
          class="p-2 hover:bg-gray-200 dark:hover:bg-slate-800 rounded-full transition text-gray-400 dark:text-slate-500"
          type="button"
        >
          <XMarkIcon class="w-5 h-5" />
        </button>
      </div>
      <p v-if="statusError" role="alert" class="px-6 pt-4 text-sm text-red-600">
        {{ statusError }}
      </p>
      <form class="p-6 space-y-5 overflow-y-auto custom-scrollbar">
        <div>
          <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5"
            >Email Address</label
          >
          <input
            type="email"
            :value="selectedCustomer?.email"
            aria-label="Customer email"
            readonly
            class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-dark-border rounded-xl dark:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
          />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label
              class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5"
              >Status</label
            >
            <select
              v-model="editStatus"
              aria-label="Customer status"
              class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-dark-border rounded-xl text-xs font-bold dark:text-white"
            >
              <option value="Active">Active</option>
              <option value="Blocked">Blocked</option>
            </select>
          </div>
        </div>
      </form>
      <div
        class="p-6 bg-gray-50 dark:bg-slate-800/20 border-t dark:border-dark-border flex justify-end gap-3"
      >
        <button
          :disabled="customerStore.loading"
          @click="handleUpdateStatus"
          class="px-6 py-2.5 text-sm font-bold text-white bg-teal-500 hover:bg-teal-600 rounded-xl shadow-lg transition"
          type="button"
        >
          Save Changes
        </button>
      </div>
    </BaseModal>

    <BaseModal :show="deleteModal" @close="deleteModal = false" maxWidth="max-w-sm">
      <div class="p-8 text-center">
        <div
          class="w-20 h-20 bg-red-100 dark:bg-red-500/10 text-red-600 dark:text-red-400 rounded-full flex items-center justify-center mx-auto mb-6"
        >
          <ShieldExclamationIcon class="w-10 h-10" />
        </div>
        <h2 class="text-xl font-black text-gray-900 dark:text-white mb-2">
          {{ selectedCustomer?.status === 'Blocked' ? 'Unblock Customer?' : 'Block Customer?' }}
        </h2>
        <p class="text-sm text-gray-500 dark:text-slate-400 mb-8 leading-relaxed">
          Are you sure you want to
          {{ selectedCustomer?.status === 'Blocked' ? 'unblock' : 'block' }}
          <span class="font-bold text-gray-900 dark:text-white">{{ selectedCustomer?.name }}</span
          >? This only changes whether they can place new orders at this store. Their account and
          existing orders remain available.
        </p>
        <p v-if="statusError" role="alert" class="mb-4 text-sm text-red-600">{{ statusError }}</p>
        <div class="flex flex-col gap-3">
          <button
            :disabled="customerStore.loading"
            @click="saveStatus(selectedCustomer?.status !== 'Blocked')"
            class="w-full py-3.5 bg-red-500 hover:bg-red-600 text-white font-black rounded-2xl shadow-xl transition active:scale-95"
            type="button"
          >
            {{
              customerStore.loading
                ? 'Saving…'
                : selectedCustomer?.status === 'Blocked'
                  ? 'Yes, Unblock Customer'
                  : 'Yes, Block Customer'
            }}
          </button>
          <button
            @click="deleteModal = false"
            class="w-full py-3 text-gray-400 dark:text-slate-500 font-bold hover:text-gray-900 dark:hover:text-white transition"
            type="button"
          >
            Cancel
          </button>
        </div>
      </div>
    </BaseModal>
  </div>
</template>
