import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import axios from '@/utils/axios'
import type { Role } from '@/types/user-types'

export const useRoleStore = defineStore('role', () => {
  const roles = ref<Role[]>([])
  const permissions = ref<string[]>([])
  const loading = ref(false)
  const saving = ref(false)

  // Form state
  const selectedRole = ref<Role | null>(null)
  const selectedPermissions = ref<string[]>([]) // active permissions for form

  const fetchRoles = async () => {
    loading.value = true
    try {
      const { data } = await axios.get('/api/v1/roles')
      roles.value = data.data
    } finally {
      loading.value = false
    }
  }

  const fetchPermissions = async () => {
    const { data } = await axios.get('/api/v1/roles/permissions')
    permissions.value = data.data
  }

  // Derive unique modules from permission names
  // e.g. 'edit products' -> 'Products'
  const modules = computed(() => [
    ...new Set(
      Object.values(permissions.value)
        .flat()
        .map((p) => {
          const parts = p.split(' ')
          const a = parts[parts.length - 1]?.charAt(0).toUpperCase() || ''
          const b = parts[parts.length - 1]?.slice(1) || '' // last word capitalized

          return a + b
        }),
    ),
  ])

  // Derive unique actions from permission names
  // e.g. 'edit products' -> 'edit'
  const actions = computed(() => [
    ...new Set(
      Object.values(permissions.value)
        .flat()
        .map((p) => {
          const parts = p.split(' ')
          const a = parts[0]?.charAt(0).toUpperCase() || ''
          const b = parts[0]?.slice(1) || '' // first word capitalized

          return a + b
        }),
    ),
  ])

  // Reset form
  const resetForm = (): void => {
    selectedRole.value = null
    selectedPermissions.value = []
  }

  // Save role permissions
  const saveRole = async (
    payload: { name: string; description: string; permissions: string[] },
    action: 'create' | 'update',
  ): Promise<void> => {
    saving.value = true
    try {
      switch (action) {
        case 'create':
          if (!payload) return
          await axios.post('/api/v1/roles', payload)
          break

        default:
          if (!selectedRole.value) return
          await axios.put(`/api/v1/roles/${selectedRole.value.id}`, payload)
          break
      }

      await fetchRoles()
    } finally {
      saving.value = false
    }
  }

  const deleteRole = async (): Promise<void> => {
    saving.value = true
    try {
      await axios.delete(`/api/v1/roles/${selectedRole.value?.id}`)

      await fetchRoles()
    } finally {
      saving.value = false
    }
  }

  return {
    roles,
    permissions,
    modules,
    actions,
    loading,
    saving,
    selectedRole,
    selectedPermissions,
    fetchRoles,
    fetchPermissions,
    resetForm,
    saveRole,
    deleteRole,
  }
})
