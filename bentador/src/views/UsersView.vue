<script setup lang="ts">
import InlineMessage from '@/components/ui/InlineMessage.vue'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useUiStore } from '@/stores/ui'
import {
  Bars3Icon,
  HomeIcon,
  UserPlusIcon,
  MagnifyingGlassIcon,
  PencilSquareIcon,
  TrashIcon,
  XMarkIcon,
  UserMinusIcon,
} from '@heroicons/vue/24/outline'
import BaseModal from '@/components/common/BaseModal.vue'
import { useUserStore } from '@/stores/user'
import type { User, UserForm, UserFormErrors } from '@/types/user-types'
import Pagination from '@/components/common/Pagination.vue'
import TableSpinner from '@/components/ui/TableSpinner.vue'
import { useDebounceFn } from '@vueuse/core'
import { storeToRefs } from 'pinia'
import axios from '@/utils/axios'
import { useRoleStore } from '@/stores/roleStore'
import { useAuthStore } from '@/stores/auth'
import { ROLES } from '@/types/enum'

const uiStore = useUiStore()
const invitationLink = ref('')
const userStore = useUserStore()
const roleStore = useRoleStore()
const authStore = useAuthStore()

const addModal = ref(false)
const editModal = ref(false)
const deleteModal = ref(false)

const selectedUser = ref<User>()
const selectedRole = ref<ROLES | null>(null)
const selectedStatus = ref<'Active' | 'Inactive'>('Active')

const canEditUserStatus = computed(() => {
  const target = selectedUser.value
  const current = authStore.user
  if (!target || !current) return false
  if (current.id === target.id) return false
  if (authStore.hasRole(ROLES.SuperAdmin)) return true
  if (target.roles.includes(ROLES.Admin) || target.roles.includes(ROLES.SuperAdmin)) return false
  return authStore.hasPermission(['edit users'])
})

const formData = reactive<UserForm>({
  name: '',
  email: '',
  role: '',
  status: 'active',
})

const formErrors = reactive<UserFormErrors>({
  name: [],
  email: [],
  role: [],
  status: [],
})

const resetForm = () => {
  formData.name = ''
  formData.email = ''
  formData.role = ''
  formData.status = 'active'
  clearErrors()
}

const clearErrors = () => {
  Object.keys(formErrors).forEach((key) => {
    formErrors[key as keyof UserFormErrors] = []
  })
}

const handleClose = () => {
  addModal.value = false
  resetForm()
}

const handleEditUserModal = (user: User) => {
  selectedUser.value = user
  selectedRole.value = user.roles[0] || ROLES.Client
  selectedStatus.value = user.status === 'Inactive' ? 'Inactive' : 'Active'
  editModal.value = true
}

const handleDeleteUserModal = (user: User) => {
  selectedUser.value = user
  selectedRole.value = user.roles[0] || null
  deleteModal.value = true
}

const handleDeleteUser = async () => {
  await userStore.deleteUser(selectedUser.value?.id || 0)
  deleteModal.value = false
  await Promise.all([userStore.fetchUsers(), userStore.fetchStatistics()])
}

const handleCreateUser = async () => {
  clearErrors() // clear previous errors before new submit

  try {
    const invitation = await userStore.createUser({ ...formData })
    invitationLink.value = invitation.invitation_link
    handleClose() // close and reset only on success
  } catch (err: unknown) {
    // Map Laravel 422 validation errors to form fields
    if (axios.isAxiosError(err) && err.response?.status === 422) {
      const errors = err.response.data.errors as Record<string, string[]>
      Object.keys(errors).forEach((key) => {
        const messages = errors[key]
        if (key in formErrors && messages) {
          formErrors[key as keyof UserFormErrors] = messages
        }
      })
    }
  }
}

const handleUpdatePermissions = async () => {
  const userId = selectedUser.value?.id || 0
  if (!userId) return

  if (canEditUserStatus.value) {
    const isActive = selectedStatus.value === 'Inactive' ? 0 : 1
    await userStore.updateStatus(userId, isActive)
  }

  await userStore.updateRole(userId, selectedRole.value || ROLES.Client)
  editModal.value = false

  await Promise.all([userStore.fetchUsers(), userStore.fetchStatistics()])
}

const { search } = storeToRefs(userStore)

const onPageChange = (page: number) => {
  userStore.fetchUsers(userStore.search, page)
}

const debouncedFetch = useDebounceFn(() => {
  userStore.fetchUsers(search.value, 1, {
    status: '', //selectedStatus.value?.code,
  })
}, 400)

watch(search, () => {
  debouncedFetch()
})

onMounted(async () => {
  await Promise.all([userStore.fetchUsers(), roleStore.fetchRoles(), userStore.fetchStatistics()])
})
</script>

<template>
  <div class="p-4 md:p-8">
    <InlineMessage v-if="invitationLink" kind="success" class="mb-6">
      <p class="font-bold">Staff invitation ready</p>
      <p class="mt-1">Share this one-use link with the invited team member.</p>
      <a :href="invitationLink" class="block mt-2 underline break-all font-medium">{{
        invitationLink
      }}</a>
    </InlineMessage>
    <!-- HEADER -->
    <header
      class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4"
    >
      <div class="flex items-center gap-4">
        <button
          @click="uiStore.setSidebar(true)"
          class="lg:hidden p-2 bg-white dark:bg-dark-card border border-gray-200 dark:border-dark-border rounded-xl"
          type="button"
        >
          <Bars3Icon class="w-6 h-6" />
        </button>
        <div>
          <nav class="flex items-center gap-2 text-[12px] text-gray-400 mb-1">
            <HomeIcon class="w-3 h-3" />
            <span>/</span>
            <span class="text-teal-600 dark:text-teal-400 font-medium">System Users</span>
          </nav>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">User Management</h1>
          <p class="text-gray-500 dark:text-slate-400 text-sm">
            Configure system access and team roles
          </p>
        </div>
      </div>
      <button
        @click="addModal = true"
        class="bg-teal-500 hover:bg-teal-600 text-white px-5 py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2 shadow-lg shadow-teal-500/20 transition-all active:scale-95"
        type="button"
      >
        <UserPlusIcon class="w-4 h-4" /> Invite staff
      </button>
    </header>

    <!-- STATS GRID -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
      <div
        v-for="stat in userStore.stats"
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
      <div
        class="p-4 border-b border-gray-100 dark:border-dark-border flex flex-col lg:flex-row gap-4 justify-between bg-gray-50/30 dark:bg-slate-800/20"
      >
        <div class="relative max-w-md w-full">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" />
          <input
            type="text"
            v-model="search"
            placeholder="Search by name, email..."
            class="w-full pl-10 pr-4 py-2 bg-white dark:bg-slate-900 border border-gray-200 dark:border-dark-border rounded-xl text-sm dark:text-white focus:ring-2 focus:ring-teal-500/20 outline-none transition"
          />
        </div>
        <div class="flex flex-wrap gap-2">
          <button
            class="px-4 py-2 border dark:border-dark-border rounded-xl text-xs font-bold bg-white dark:bg-slate-900 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 transition"
            type="button"
          >
            All Roles
          </button>
          <button
            class="px-4 py-2 border dark:border-dark-border rounded-xl text-xs font-bold bg-white dark:bg-slate-900 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 transition"
            type="button"
          >
            Status
          </button>
        </div>
      </div>

      <!-- Wrapper for the overlay positioning -->
      <div class="relative">
        <TableSpinner v-if="userStore.isLoading" :text="`Fetching orders...`" />

        <div class="overflow-x-auto custom-scrollbar">
          <table class="w-full text-left min-w-[1000px]">
            <thead>
              <tr
                class="bg-gray-50/50 dark:bg-slate-800/20 text-[11px] uppercase tracking-widest text-gray-400 dark:text-slate-500 font-bold border-b border-gray-100 dark:border-dark-border"
              >
                <th class="px-6 py-4">User</th>
                <th class="px-6 py-4">Role</th>
                <th class="px-6 py-4">Email</th>
                <th class="px-6 py-4 text-center">Last Login</th>
                <th class="px-6 py-4">Status</th>
                <th class="px-6 py-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-dark-border">
              <tr
                v-for="user in userStore.users"
                :key="user.id"
                class="hover:bg-gray-50/80 dark:hover:bg-slate-800/30 transition-colors group"
              >
                <td class="px-6 py-4">
                  <div class="flex items-center gap-3">
                    <img
                      :src="`https://ui-avatars.com/api/?name=${user.name}&background=random&color=fff`"
                      class="w-10 h-10 rounded-full border-2 border-white dark:border-slate-700"
                      alt="User Avatar"
                    />
                    <span class="text-sm font-bold text-gray-900 dark:text-white">{{
                      user.name
                    }}</span>
                  </div>
                </td>
                <td v-if="user.roles.length > 0" class="px-6 py-4">
                  <span
                    v-for="role in user.roles"
                    :key="role"
                    class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-[10px] font-black uppercase text-gray-600 dark:text-slate-400"
                    >{{ role }}</span
                  >
                </td>
                <td v-else class="px-6 py-4">
                  <span
                    class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-[10px] font-black uppercase text-gray-600 dark:text-slate-400"
                    >None</span
                  >
                </td>
                <td class="px-6 py-4 text-xs font-medium text-gray-600 dark:text-slate-400">
                  {{ user.email }}
                </td>
                <td class="px-6 py-4 text-center text-xs text-gray-500 dark:text-slate-500">
                  {{ user.login }}
                </td>
                <td class="px-6 py-4">
                  <span
                    class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider flex items-center gap-1.5 w-fit"
                    :class="`bg-${user.color}-100 text-${user.color}-700 dark:bg-${user.color}-500/10 dark:text-${user.color}-400`"
                  >
                    <span
                      class="w-1.5 h-1.5 rounded-full"
                      :class="`bg-${user.color}-600 dark:bg-${user.color}-400`"
                    ></span>
                    {{ user.status }}
                  </span>
                </td>
                <td class="px-6 py-4 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <button
                      @click="handleEditUserModal(user)"
                      class="p-2 hover:bg-teal-50 dark:hover:bg-teal-500/10 text-gray-400 hover:text-teal-600 rounded-lg transition"
                      type="button"
                    >
                      <PencilSquareIcon class="w-4 h-4" />
                    </button>
                    <button
                      @click="handleDeleteUserModal(user)"
                      class="p-2 hover:bg-red-50 dark:hover:bg-red-500/10 text-gray-400 hover:text-red-600 rounded-lg transition"
                      type="button"
                    >
                      <TrashIcon class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <Pagination v-if="userStore.meta" :meta="userStore.meta" @page-change="onPageChange" />
    </div>

    <!-- Modals -->
    <BaseModal :show="addModal" @close="handleClose">
      <div
        class="p-6 border-b dark:border-dark-border flex justify-between items-center bg-gray-50/50 dark:bg-slate-800/20"
      >
        <h2 class="text-xl font-black text-gray-900 dark:text-white">Create New User</h2>
        <button
          @click="handleClose"
          class="p-2 hover:bg-gray-200 dark:hover:bg-slate-800 rounded-full transition text-gray-400"
          type="button"
        >
          <XMarkIcon class="w-5 h-5" />
        </button>
      </div>
      <form class="p-6 space-y-5" @submit.prevent="handleClose">
        <div>
          <label class="block text-[10px] font-black text-gray-400 uppercase mb-1.5"
            >Full Name</label
          >
          <input
            v-model="formData.name"
            type="text"
            class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-900 border dark:border-dark-border rounded-xl dark:text-white outline-none focus:ring-2 focus:ring-teal-500/20"
          />
          <p v-if="formErrors.name?.length" class="text-red-400 text-[11px] font-bold mt-1">
            {{ formErrors.name[0] }}
          </p>
        </div>
        <div>
          <label class="block text-[10px] font-black text-gray-400 uppercase mb-1.5">Email</label>
          <input
            v-model="formData.email"
            type="email"
            class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-900 border dark:border-dark-border rounded-xl dark:text-white outline-none focus:ring-2 focus:ring-teal-500/20"
          />
          <p v-if="formErrors.email?.length" class="text-red-400 text-[11px] font-bold mt-1">
            {{ formErrors.email[0] }}
          </p>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-[10px] font-black text-gray-400 uppercase mb-1.5">Role</label>
            <select
              v-model="formData.role"
              class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-900 border dark:border-dark-border rounded-xl text-sm dark:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
            >
              <option v-for="role in roleStore.roles" :key="role.id" :value="role.name">
                {{ role.name }}
              </option>
            </select>
            <p v-if="formErrors.role?.length" class="text-red-400 text-[11px] font-bold mt-1">
              {{ formErrors.role[0] }}
            </p>
          </div>
          <div>
            <label class="block text-[10px] font-black text-gray-400 uppercase mb-1.5"
              >Initial Status</label
            >
            <select
              class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-900 border dark:border-dark-border rounded-xl text-sm dark:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
            >
              <option>Active</option>
              <option>Inactive</option>
            </select>
          </div>
        </div>
      </form>
      <div
        class="p-6 bg-gray-50 dark:bg-slate-800/20 border-t dark:border-dark-border flex justify-end gap-3"
      >
        <button
          @click="handleCreateUser"
          :disabled="userStore.isLoading"
          class="px-6 py-2.5 text-sm font-bold text-white bg-teal-500 hover:bg-teal-600 rounded-xl shadow-lg transition"
          type="button"
        >
          {{ userStore.isLoading ? 'Inviting...' : 'Invite staff' }}
        </button>
      </div>
    </BaseModal>

    <BaseModal :show="editModal" @close="editModal = false">
      <div
        class="p-6 border-b dark:border-dark-border flex justify-between items-center bg-gray-50/50 dark:bg-slate-800/20"
      >
        <h2 class="text-xl font-black text-gray-900 dark:text-white">Edit User Role</h2>
        <button
          @click="editModal = false"
          class="p-2 hover:bg-gray-200 dark:hover:bg-slate-800 rounded-full transition text-gray-400"
          type="button"
        >
          <XMarkIcon class="w-5 h-5" />
        </button>
      </div>
      <div class="p-6 space-y-4">
        <p class="text-xs text-teal-600 font-bold">
          Modifying access for:
          <span class="text-gray-900 dark:text-white">{{ selectedUser?.name }}</span>
        </p>
        <div>
          <label class="block text-[10px] font-black text-gray-400 uppercase mb-1.5">Role</label>
          <select
            v-model="selectedRole"
            class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-900 border dark:border-dark-border rounded-xl text-sm dark:text-white font-bold"
          >
            <option v-for="role in roleStore.roles" :key="role.id" :value="role.name">
              {{ role.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-[10px] font-black text-gray-400 uppercase mb-1.5">Status</label>
          <select
            v-model="selectedStatus"
            :disabled="!canEditUserStatus"
            class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-900 border dark:border-dark-border rounded-xl text-sm dark:text-white font-bold disabled:opacity-60 disabled:cursor-not-allowed"
          >
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
          </select>
        </div>
      </div>
      <div
        class="p-6 bg-gray-50 dark:bg-slate-800/20 border-t dark:border-dark-border flex justify-end gap-3 rounded-b-3xl"
      >
        <button
          @click="handleUpdatePermissions"
          class="px-6 py-2.5 text-sm font-bold text-white bg-teal-500 hover:bg-teal-600 rounded-xl shadow-lg transition"
          type="button"
        >
          Update Permissions
        </button>
      </div>
    </BaseModal>

    <BaseModal :show="deleteModal" @close="deleteModal = false" maxWidth="max-w-sm">
      <div class="p-8 text-center">
        <div
          class="w-20 h-20 bg-red-100 dark:bg-red-500/10 text-red-600 dark:text-red-400 rounded-full flex items-center justify-center mx-auto mb-6"
        >
          <UserMinusIcon class="w-10 h-10" />
        </div>
        <h2 class="text-xl font-black text-gray-900 dark:text-white mb-2">Remove Access?</h2>
        <p class="text-sm text-gray-500 dark:text-slate-400 mb-8 leading-relaxed">
          Remove
          <span class="font-bold text-gray-900 dark:text-white">{{ selectedUser?.name }}</span
          >? They will be unable to log in until re-added.
        </p>
        <div class="flex flex-col gap-3">
          <button
            @click="handleDeleteUser"
            :disabled="userStore.isLoading"
            class="w-full py-3.5 bg-red-500 hover:bg-red-600 text-white font-black rounded-2xl shadow-xl transition active:scale-95"
            type="button"
          >
            {{ userStore.isLoading ? 'Deleting...' : 'Yes, Revoke Access' }}
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
