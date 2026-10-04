import { ref } from 'vue'
import axios from '@/utils/axios'

export function useDownload() {
  const downloading = ref(false)
  async function downloadPdf(endpoint: string) {
    downloading.value = true
    try {
      const { data } = await axios.get(`/${endpoint.replace(/^\//, '')}`, { responseType: 'blob' })
      const url = URL.createObjectURL(data)
      const anchor = document.createElement('a')
      anchor.href = url
      anchor.download = 'orders-report.pdf'
      anchor.click()
      setTimeout(() => URL.revokeObjectURL(url), 1000)
    } finally {
      downloading.value = false
    }
  }
  return { downloading, downloadPdf }
}
