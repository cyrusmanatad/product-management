import axios from 'axios'
import { contextRevision, staffPath, storePath } from './tenantContext'

const client = axios.create({
  baseURL:
    import.meta.env.VITE_API_BASE_URL ?? (import.meta.env.PROD ? '' : 'http://localhost:8000'),
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
})

client.interceptors.request.use((config) => {
  const url = config.url ?? ''
  const token = localStorage.getItem('auth_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  else delete config.headers.Authorization
  const revisionConfig = config as typeof config & { tenantRevision: number }
  revisionConfig.tenantRevision = contextRevision.value
  if (/^\/api\/v1\/(catalog\/|checkout(?:$|\?))/.test(url)) {
    config.url = storePath(url.replace('/api/v1/', ''))
  } else if (
    /^\/api\/v1\/(products|categories|inventory|orders|customers|roles|analytics|users(?!\/me))(?:$|[/?])/.test(
      url,
    )
  ) {
    config.url = staffPath(url.replace('/api/v1/', ''))
  }
  return config
})

client.interceptors.response.use(
  (response) => {
    const config = response.config as typeof response.config & { tenantRevision?: number }
    if (config.tenantRevision !== contextRevision.value) {
      throw new axios.CanceledError('Vendor context changed.')
    }
    return response
  },
  (error) => {
    if (
      axios.isAxiosError(error) &&
      error.config &&
      (error.config as typeof error.config & { tenantRevision?: number }).tenantRevision !==
        contextRevision.value
    ) {
      return Promise.reject(new axios.CanceledError('Vendor context changed.'))
    }
    if (axios.isAxiosError(error) && error.response?.status === 401) {
      localStorage.removeItem('auth_token')
      window.dispatchEvent(new Event('session:expired'))
    }
    return Promise.reject(error)
  },
)

export default Object.assign(client, { isAxiosError: axios.isAxiosError })
