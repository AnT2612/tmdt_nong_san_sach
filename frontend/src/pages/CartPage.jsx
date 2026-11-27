import { Link } from 'react-router-dom'
import Icon from '../components/Icon.jsx'
import { useCart } from '../context/CartContext.jsx'
import { formatMoney, formatQuantity } from '../utils/format.js'
import './CartPage.css'

export default function CartPage() {
  const { items, updateQuantity, removeItem, subtotal, clearCart } = useCart()
  const FREE_SHIPPING_THRESHOLD = 300000

  if (items.length === 0) {
    return (
      <div className="wrap cart">
        <h1 className="cart__title">Giỏ hàng</h1>
        <div className="empty">
          <p className="empty__title">Giỏ hàng đang trống</p>
          <p>Chọn vài món rau củ tươi cho bữa tối nay.</p>
          <p style={{ marginTop: 'var(--s4)' }}>
            <Link to="/san-pham" className="btn btn--primary">
              Xem nông sản
              <Icon name="arrow" size={16} />
            </Link>
          </p>
        </div>
      </div>
    )
  }

  return (
    <div className="wrap cart">
      <div className="cart__head">
        <h1 className="cart__title">Giỏ hàng</h1>
        <button type="button" className="cart__clear" onClick={clearCart}>
          <Icon name="trash" size={15} />
          Xoá tất cả
        </button>
      </div>

      <div className="cart__grid">
        <div className="cart__items">
          {items.map((item) => (
            <article key={item.product_id} className="cart-item">
              <div className="cart-item__media">
                {item.hinh_anh_dai_dien ? (
                  <img src={item.hinh_anh_dai_dien} alt={item.ten_san_pham} />
                ) : (
                  <div className="cart-item__placeholder">
                    <Icon name="leaf" size={22} />
                  </div>
                )}
              </div>

              <div className="cart-item__info">
                <h2 className="cart-item__name">{item.ten_san_pham}</h2>
                <p className="cart-item__price data">
                  {formatMoney(item.gia)} / {item.don_vi_tinh}
                </p>
              </div>

              <div className="cart-item__controls">
                <div className="stepper stepper--sm">
                  <button
                    type="button"
                    className="stepper__btn"
                    onClick={() => updateQuantity(item.product_id, item.quantity - 1)}
                    aria-label={`Giảm số lượng ${item.ten_san_pham}`}
                  >
                    <Icon name="minus" size={14} />
                  </button>

                  <span className="stepper__value data">{formatQuantity(item.quantity)}</span>

                  <button
                    type="button"
                    className="stepper__btn"
                    onClick={() => updateQuantity(item.product_id, item.quantity + 1)}
                    aria-label={`Tăng số lượng ${item.ten_san_pham}`}
                  >
                    <Icon name="plus" size={14} />
                  </button>
                </div>

                <span className="cart-item__total data">
                  {formatMoney(item.gia * item.quantity)}
                </span>

                <button
                  type="button"
                  className="cart-item__remove"
                  onClick={() => removeItem(item.product_id)}
                  aria-label={`Xoá ${item.ten_san_pham} khỏi giỏ`}
                >
                  <Icon name="close" size={16} />
                </button>
              </div>
            </article>
          ))}
        </div>

        <aside className="cart__summary">
          <h2 className="cart__summary-title">Tạm tính</h2>

          <dl className="cart__lines">
            <div className="cart__line">
              <dt>Tiền hàng</dt>
              <dd className="data">{formatMoney(subtotal)}</dd>
            </div>
            <div className="cart__line">
              <dt>Phí giao</dt>
              <dd className="data">Tính theo khoảng cách km</dd>
            </div>
          </dl>

          {subtotal < FREE_SHIPPING_THRESHOLD && (
            <p className="cart__hint">
              Đơn từ {formatMoney(FREE_SHIPPING_THRESHOLD)} được miễn phí giao hàng thường.
            </p>
          )}

          <div className="cart__total">
            <span>Tạm tính</span>
            <strong className="data">{formatMoney(subtotal)}</strong>
          </div>

          <Link to="/thanh-toan" className="btn btn--primary btn--block">
            Tiến hành đặt hàng
            <Icon name="arrow" size={16} />
          </Link>

          <Link to="/san-pham" className="cart__continue">
            <Icon name="back" size={15} />
            Tiếp tục chọn hàng
          </Link>
        </aside>
      </div>
    </div>
  )
}
