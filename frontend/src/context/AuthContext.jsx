import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import { apiGet, apiPost, getToken, setToken } from '../api/client.js'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let cancelled = false

    async function restoreSession() {
      if (!getToken()) {
        setLoading(false)
        return
      }

      try {
        const response = await apiGet('/auth/me')
        if (!cancelled) {
          setUser(response.data)
        }
      } catch {
        setToken(null)
        if (!cancelled) {
          setUser(null)
        }
      } finally {
        if (!cancelled) {
          setLoading(false)
        }
      }
    }

    restoreSession()

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    function handleUnauthorized() {
      setUser(null)
    }

    window.addEventListener('nongsan:unauthorized', handleUnauthorized)
    return () => window.removeEventListener('nongsan:unauthorized', handleUnauthorized)
  }, [])

  const login = useCallback(async (email, matKhau) => {
    const response = await apiPost('/auth/login', { email, mat_khau: matKhau })
    setToken(response.data.token)
    setUser(response.data.user)
    return response.data.user
  }, [])

  const register = useCallback(async (payload) => {
    const response = await apiPost('/auth/register', payload)
    setToken(response.data.token)
    setUser(response.data.user)
    return response.data.user
  }, [])

  const logout = useCallback(async () => {
    try {
      await apiPost('/auth/logout')
    } catch {
      // vẫn xóa token cục bộ
    }

    setToken(null)
    setUser(null)
  }, [])

  const refreshUser = useCallback(async () => {
    if (!getToken()) return null
    const response = await apiGet('/auth/me')
    setUser(response.data)
    return response.data
  }, [])

  const hasRole = useCallback(
    (...roles) => {
      const roleName = user?.ten_vai_tro || user?.role?.ten_vai_tro
      return roleName ? roles.includes(roleName) : false
    },
    [user],
  )

  const value = useMemo(
    () => ({ user, loading, login, register, logout, refreshUser, hasRole }),
    [user, loading, login, register, logout, refreshUser, hasRole],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (context === null) {
    throw new Error('useAuth phải được dùng bên trong <AuthProvider>.')
  }
  return context
}
