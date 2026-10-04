<script setup lang="ts">
import { onUnmounted, ref, watch } from 'vue'
import axios from '@/utils/axios'
import type { ProductImage } from '@/types/data-types'
import { PhotoIcon, TrashIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'

const props = defineProps<{ productId: number | null; disabled?: boolean }>()
const emit = defineEmits<{ busy: [value: boolean] }>()
const images = ref<ProductImage[]>([])
const previews = ref<Record<number, string>>({})
const pending = ref<{ file: File; url: string }[]>([])
const primaryIndex = ref<number | null>(null)
const busy = ref(false)
const error = ref('')
let generation = 0
const message = (cause: unknown) => {
  if (axios.isAxiosError(cause)) {
    const data = cause.response?.data
    return (
      Object.values(data?.errors ?? {})
        .flat()
        .join(' ') ||
      data?.message ||
      'Image request failed. Try again.'
    )
  }
  return 'Image request failed. Try again.'
}
const setBusy = (value: boolean) => {
  busy.value = value
  emit('busy', value)
}
const release = () => {
  Object.values(previews.value).forEach((url) => URL.revokeObjectURL(url))
  previews.value = {}
}
const load = async (productId: number, version: number) => {
  const { data } = await axios.get<{ data: ProductImage[] }>(`/api/v1/products/${productId}/images`)
  if (version !== generation) return
  images.value = data.data
  release()
  await Promise.all(
    data.data.map(async (image) => {
      if (!image.url.startsWith('/api/v1/stores/')) {
        previews.value[image.id] = image.url
        return
      }
      const response = await axios.get<Blob>(
        `/api/v1/products/${productId}/images/${image.id}/file`,
        { responseType: 'blob' },
      )
      if (version === generation) previews.value[image.id] = URL.createObjectURL(response.data)
    }),
  )
}
watch(
  () => props.productId,
  async (id) => {
    const version = ++generation
    release()
    images.value = []
    if (id === null) return
    setBusy(true)
    try {
      await load(id, version)
    } catch (cause) {
      if (version === generation) error.value = message(cause)
    } finally {
      if (version === generation) setBusy(false)
    }
  },
  { immediate: true },
)
const choose = (event: Event) => {
  const input = event.target as HTMLInputElement
  const files = Array.from(input.files ?? [])
  input.value = ''
  error.value = ''
  if (pending.value.length + images.value.length + files.length > 10) {
    error.value = 'A product can have up to 10 images.'
    return
  }
  if (
    files.some(
      (file) =>
        !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ||
        file.size > 5 * 1024 * 1024,
    )
  ) {
    error.value = 'Choose JPEG, PNG or WebP images up to 5 MB each.'
    return
  }
  pending.value.push(...files.map((file) => ({ file, url: URL.createObjectURL(file) })))
  if (images.value.length === 0 && primaryIndex.value === null && pending.value.length > 0)
    primaryIndex.value = 0
}
const removePending = (index: number) => {
  const removed = pending.value.splice(index, 1)[0]
  if (removed) URL.revokeObjectURL(removed.url)
  if (primaryIndex.value === index)
    primaryIndex.value = images.value.length === 0 && pending.value.length > 0 ? 0 : null
  else if (primaryIndex.value !== null && primaryIndex.value > index) primaryIndex.value--
}
const change = async (image: ProductImage, action: 'primary' | 'delete') => {
  if (!props.productId || busy.value || props.disabled) return
  setBusy(true)
  error.value = ''
  try {
    const path = `/api/v1/products/${props.productId}/images/${image.id}`
    if (action === 'delete') await axios.delete(path)
    else {
      await axios.patch(`${path}/primary`)
      primaryIndex.value = null
    }
    await load(props.productId, generation)
  } catch (cause) {
    error.value = message(cause)
  } finally {
    setBusy(false)
  }
}
const uploadPending = async (productId: number) => {
  if (!pending.value.length) return
  setBusy(true)
  error.value = ''
  try {
    const form = new FormData()
    pending.value.forEach(({ file }) => form.append('images[]', file))
    if (primaryIndex.value !== null) form.append('primary_index', String(primaryIndex.value))
    await axios.post(`/api/v1/products/${productId}/images`, form)
    pending.value.forEach(({ url }) => URL.revokeObjectURL(url))
    pending.value = []
    primaryIndex.value = null
    // Keep the successful upload committed even if preview retrieval fails.
  } catch (cause) {
    error.value = message(cause)
    throw cause
  } finally {
    setBusy(false)
  }
}
defineExpose({ uploadPending })
onUnmounted(() => {
  generation++
  release()
  pending.value.forEach(({ url }) => URL.revokeObjectURL(url))
})
</script>

<template>
  <section class="space-y-4" aria-label="Product images">
    <div>
      <h3 class="font-bold text-gray-900 dark:text-white">Product images</h3>
      <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
        Choose up to 10 JPEG, PNG or WebP images. Maximum 5 MB and 4096 × 4096 pixels per image. The
        storefront image appears on product cards.
      </p>
      <p v-if="productId" class="mt-2 text-xs text-gray-500 dark:text-slate-400">
        Cover changes and removals save immediately. New uploads save with the product.
      </p>
    </div>
    <label
      class="block rounded-2xl border border-dashed border-gray-300 dark:border-dark-border p-4 text-sm font-bold text-gray-700 dark:text-slate-300"
    >
      <span class="flex items-center gap-2 mb-3"
        ><PhotoIcon class="w-5 h-5 text-teal-500" /> Add images</span
      >
      <input
        type="file"
        multiple
        accept="image/jpeg,image/png,image/webp"
        aria-label="Choose product images"
        :disabled="busy || disabled"
        class="block w-full text-xs file:mr-3 file:rounded-xl file:border-0 file:bg-teal-50 file:px-3 file:py-2 file:font-bold file:text-teal-700 dark:file:bg-teal-500/10 dark:file:text-teal-400"
        @change="choose"
      />
    </label>
    <p v-if="error" role="alert" class="text-xs text-red-500">{{ error }}</p>
    <p v-if="busy" role="status" class="text-xs text-gray-500 dark:text-slate-400">
      Saving or loading images…
    </p>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
      <article
        v-for="image in images"
        :key="image.id"
        class="min-w-0 rounded-2xl border border-gray-100 dark:border-dark-border overflow-hidden"
      >
        <div class="aspect-square bg-gray-50 dark:bg-slate-900/50 flex items-center justify-center">
          <img
            v-if="previews[image.id]"
            :src="previews[image.id]"
            alt="Saved product image"
            class="w-full h-full object-contain"
          />
          <PhotoIcon v-else class="w-10 h-10 text-gray-300" />
        </div>
        <div class="p-2 space-y-2">
          <button
            type="button"
            :disabled="busy || disabled"
            :aria-pressed="image.is_primary && primaryIndex === null"
            class="w-full min-h-10 rounded-xl px-2 text-[11px] font-bold text-teal-600 dark:text-teal-400 hover:bg-teal-50 dark:hover:bg-teal-500/10 disabled:opacity-50"
            @click="change(image, 'primary')"
          >
            <CheckCircleIcon
              v-if="image.is_primary && primaryIndex === null"
              class="inline w-4 h-4"
            />
            {{
              image.is_primary && primaryIndex === null ? 'Storefront image' : 'Use in storefront'
            }}
          </button>
          <button
            type="button"
            :disabled="busy || disabled"
            class="w-full min-h-10 rounded-xl text-xs text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 disabled:opacity-50"
            @click="change(image, 'delete')"
          >
            <TrashIcon class="inline w-4 h-4" /> Remove
          </button>
        </div>
      </article>
      <article
        v-for="(item, index) in pending"
        :key="item.url"
        class="min-w-0 rounded-2xl border border-gray-100 dark:border-dark-border overflow-hidden"
      >
        <img
          :src="item.url"
          :alt="item.file.name"
          class="aspect-square w-full object-contain bg-gray-50 dark:bg-slate-900/50"
        />
        <div class="p-2 space-y-2">
          <p class="text-[10px] text-gray-400 truncate">{{ item.file.name }} · Pending</p>
          <button
            type="button"
            :disabled="busy || disabled"
            :aria-pressed="primaryIndex === index"
            class="w-full min-h-10 rounded-xl px-2 text-[11px] font-bold text-teal-600 dark:text-teal-400 hover:bg-teal-50 dark:hover:bg-teal-500/10 disabled:opacity-50"
            @click="primaryIndex = index"
          >
            <CheckCircleIcon v-if="primaryIndex === index" class="inline w-4 h-4" />
            {{ primaryIndex === index ? 'Storefront image' : 'Use in storefront' }}
          </button>
          <button
            type="button"
            :disabled="busy || disabled"
            class="w-full min-h-10 rounded-xl text-xs text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 disabled:opacity-50"
            @click="removePending(index)"
          >
            <TrashIcon class="inline w-4 h-4" /> Remove
          </button>
        </div>
      </article>
    </div>
    <p
      v-if="!images.length && !pending.length && !busy"
      class="text-xs text-gray-400 dark:text-slate-500"
    >
      No images yet. The first upload becomes the storefront image unless you choose another.
    </p>
  </section>
</template>
