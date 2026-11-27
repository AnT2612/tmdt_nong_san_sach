import { useState } from 'react'
import { Link, NavLink, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext.jsx'
import { useCart } from '../context/CartContext.jsx'
import Icon from './Icon.jsx'
import './Header.css'

const STAFF_LINKS = {
  farm_staff: [
    { to: '/staff/farm', label: 'Nông trại & Nhật ký' },
    { to: '/staff/warehouse', label: 'Kho lô & Hao hụt' },
  ],
  sales: [{ to: '/staff/sales', label: 'Đơn hàng & CSKH' }],
  admin: [
    { to: '/staff/admin', label: 'Quản trị' },
    { to: '/staff/sales', label: 'Đơn hàng' },
    { to: '/staff/warehouse', label: 'Kho lô' },
  ],
}

export default function Header() {
  const { user, logout, hasRole } = useAuth()
  const { totalQuantity } = useCart()
  const navigate = useNavigate()
  const [menuOpen, setMenuOpen] = useState(false)

  const roleName = user?.ten_vai_tro || user?.role?.ten_vai_tro
  const staffLinks = STAFF_LINKS[roleName] || []

  async function handleLogout() {
    await logout()
    setMenuOpen(false)
    navigate('/')
  }

  function closeMenu() {
    setMenuOpen(false)
  }

  return (
    <header className="site-header">
      <div className="wrap site-header__bar">
        <Link to="/" className="site-header__logo" onClick={closeMenu}>
          <span className="site-header__mark" aria-hidden="true">NS</span>
          <span className="site-header__wordmark">
            Nông Sản Sạch
            <span className="site-header__sub">Vườn Củ Chi · giao trong ngày</span>
          </span>
        </Link>

        <nav className={`site-header__nav ${menuOpen ? 'is-open' : ''}`} aria-label="Điều hướng chính">
          <NavLink to="/san-pham" className="site-header__link" onClick={closeMenu}>
            Sản phẩm
          </NavLink>
          <NavLink to="/goi-dinh-ky" className="site-header__link" onClick={closeMenu}>
            Gói định kỳ
          </NavLink>
          <NavLink to="/truy-xuat" className="site-header__link" onClick={closeMenu}>
            Truy xuất
          </NavLink>

          {staffLinks.map((item) => (
            <NavLink key={item.to} to={item.to} className="site-header__link" onClick={closeMenu}>
              {item.label}
            </NavLink>
          ))}

          {hasRole('customer') && (
            <NavLink to="/don-hang" className="site-header__link" onClick={closeMenu}>
              Đơn của tôi
            </NavLink>
          )}
        </nav>

        <div className="site-header__actions">
          <Link to="/gio-hang" className="site-header__cart" aria-label={`Giỏ hàng, ${totalQuantity} món`}>
            <Icon name="cart" size={19} />
            <span className="site-header__cart-label">Giỏ</span>
            {totalQuantity > 0 && (
              <span className="site-header__count data">{totalQuantity}</span>
            )}
          </Link>

          {user ? (
            <div className="site-header__account">
              <span className="site-header__name">{user.ho_ten}</span>
              <button type="button" className="site-header__logout" onClick={handleLogout}>
                Đăng xuất
              </button>
            </div>
          ) : (
            <Link to="/dang-nhap" className="site-header__login" onClick={closeMenu}>
              Đăng nhập
            </Link>
          )}

          <button
            type="button"
            className="site-header__toggle"
            onClick={() => setMenuOpen((open) => !open)}
            aria-expanded={menuOpen}
            aria-label={menuOpen ? 'Đóng menu' : 'Mở menu'}
          >
            <Icon name={menuOpen ? 'close' : 'menu'} size={22} />
          </button>
        </div>
      </div>
    </header>
  )
}
