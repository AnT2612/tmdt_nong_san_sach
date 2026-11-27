import axios from 'axios'

export const API_URL = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api/v1'

const TOKEN_KEY = 'nongsan_token'

export function getToken() {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token) {
  if (token) {
    localStorage.setItem(TOKEN_KEY, token)
  } else {
    localStorage.removeItem(TOKEN_KEY)
  }
}

export const api = axios.create({
  baseURL: API_URL,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  timeout: 15000,
})

api.interceptors.request.use((config) => {
  const token = getToken()

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && getToken()) {
      setToken(null)
      window.dispatchEvent(new Event('nongsan:unauthorized'))
    }

    return Promise.reject(error)
  },
)

export async function apiGet(path, config) {
  const response = await api.get(path, config)
  return response.data
}

export async function apiPost(path, body, config) {
  const response = await api.post(path, body, config)
  return response.data
}

export async function apiPut(path, body, config) {
  const response = await api.put(path, body, config)
  return response.data
}

export async function apiPatch(path, body, config) {
  const response = await api.patch(path, body, config)
  return response.data
}

export async function apiDelete(path, config) {
  const response = await api.delete(path, config)
  return response.data
}

export async function apiUpload(file) {
  const formData = new FormData()
  formData.append('file', file)

  const response = await api.post('/upload', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  })

  return response.data
}

export function readError(error) {
  if (!error.response) {
    return 'Không kết nối được máy chủ. Kiểm tra Backend đã chạy chưa.'
  }

  const body = error.response.data

  if (body?.errors && typeof body.errors === 'object') {
    const firstField = Object.values(body.errors)[0]
    if (Array.isArray(firstField) && firstField.length > 0) {
      return firstField[0]
    }
  }

  return body?.message || 'Đã có lỗi xảy ra. Vui lòng thử lại.'
}

export function readFieldErrors(error) {
  const errors = error.response?.data?.errors
  if (!errors || typeof errors !== 'object') {
    return {}
  }

  return Object.fromEntries(
    Object.entries(errors).map(([field, messages]) => [
      field,
      Array.isArray(messages) ? messages[0] : String(messages),
    ]),
  )
}
