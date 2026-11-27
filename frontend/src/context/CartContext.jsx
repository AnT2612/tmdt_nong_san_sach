import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'

const CartContext = createContext(null)
const STORAGE_KEY = 'nongsan_cart_v2'

function loadCart() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? JSON.parse(raw) : []
  } catch {
    return []
  }
}

export function CartProvider({ children }) {
  const [items, setItems] = useState(loadCart)

  useEffect(() => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items))
  }, [items])

  const addItem = useCallback((product, quantity = 1) => {
    setItems((current) => {
      const existing = current.find((item) => item.product_id === product.id)

      if (existing) {
        return current.map((item) =>
          item.product_id === product.id
            ? { ...item, quantity: round(item.quantity + quantity) }
            : item,
        )
      }

      return [
        ...current,
        {
          product_id: product.id,
          ten_san_pham: product.ten_san_pham,
          don_vi_tinh: product.don_vi_tinh,
          gia: product.gia_hien_tai ?? product.gia_ban_co_ban,
          hinh_anh_dai_dien: product.hinh_anh_dai_dien,
          quantity: round(quantity),
        },
      ]
    })
  }, [])

  const updateQuantity = useCallback((productId, quantity) => {
    setItems((current) => {
      if (quantity <= 0) {
        return current.filter((item) => item.product_id !== productId)
      }

      return current.map((item) =>
        item.product_id === productId ? { ...item, quantity: round(quantity) } : item,
      )
    })
  }, [])

  const removeItem = useCallback((productId) => {
    setItems((current) => current.filter((item) => item.product_id !== productId))
  }, [])

  const clearCart = useCallback(() => setItems([]), [])

  const totals = useMemo(() => {
    const totalQuantity = items.reduce((sum, item) => sum + item.quantity, 0)
    const subtotal = items.reduce((sum, item) => sum + item.quantity * item.gia, 0)
    return { totalQuantity, subtotal }
  }, [items])

  const value = useMemo(
    () => ({ items, addItem, updateQuantity, removeItem, clearCart, ...totals }),
    [items, addItem, updateQuantity, removeItem, clearCart, totals],
  )

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>
}

function round(value) {
  return Math.round(Number(value) * 100) / 100
}

export function useCart() {
  const context = useContext(CartContext)
  if (context === null) {
    throw new Error('useCart phải được dùng bên trong <CartProvider>.')
  }
  return context
}
