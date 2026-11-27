import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { apiGet, apiPost, readError, readFieldErrors } from '../api/client.js'
import Icon from '../components/Icon.jsx'
import { useAuth } from '../context/AuthContext.jsx'
import { useCart } from '../context/CartContext.jsx'
import { formatMoney, formatQuantity } from '../utils/format.js'
import './CheckoutPage.css'

const POINT_VALUE = 1000

export default function CheckoutPage() {
  const navigate = useNavigate()
  const { user } = useAuth()
  const { items, subtotal, clearCart } = useCart()

  const [form, setForm] = useState({
    ten_nguoi_nhan: user?.ho_ten ?? '',
    so_dien_thoai: user?.so_dien_thoai ?? '',
    email_nguoi_nhan: user?.email ?? '',
    dia_chi_giao: '',
    loai_giao: 'thuong',
    cong_thanh_toan: 'cod',
  })

  const [shippingEstimate, setShippingEstimate] = useState({ khoang_cach_km: null, phi_van_chuyen: 0 })
  const [pointsUsed, setPointsUsed] = useState(0)
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState('')

  const maxPointsByAmount = Math.floor(subtotal / POINT_VALUE)
  const maxPoints = Math.min(user?.diem_tich_luy ?? 0, maxPointsByAmount)
  const discountAmount = pointsUsed * POINT_VALUE
  const total = Math.max(0, subtotal + Number(shippingEstimate.phi_van_chuyen || 0) - discountAmount)

  useEffect(() => {
    if (!form.dia_chi_giao.trim() || items.length === 0) return

    const timer = setTimeout(() => {
      const params = new URLSearchParams({
        dia_chi_giao: form.dia_chi_giao,
        tam_tinh: String(subtotal),
        loai_giao: form.loai_giao,
      })
      apiGet(`/shipping-fee?${params.toString()}`)
        .then((res) => setShippingEstimate(res.data))
        .catch(() => {})
    }, 350)

    return () => clearTimeout(timer)
  }, [form.dia_chi_giao, form.loai_giao, subtotal, items.length])

  function setField(name, value) {
    setForm((current) => ({ ...current, [name]: value }))
    setErrors((current) => ({ ...current, [name]: undefined }))
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setSubmitError('')
    setErrors({})

    const payload = {
      ten_nguoi_nhan: form.ten_nguoi_nhan,
      so_dien_thoai: form.so_dien_thoai,
      email_nguoi_nhan: form.email_nguoi_nhan || null,
      dia_chi_giao: form.dia_chi_giao,
      loai_giao: form.loai_giao,
      cong_thanh_toan: form.cong_thanh_toan,
      diem_su_dung: pointsUsed,
      items: items.map((item) => ({
        product_id: item.product_id,
        quantity: item.quantity,
      })),
    }

    try {
      const response = await apiPost('/orders', payload)
      const { order, payment_url: paymentUrl } = response.data
      clearCart()

      if (form.cong_thanh_toan === 'vnpay' && paymentUrl) {
        window.location.href = paymentUrl
        return
      }

      navigate(`/don-hang/${order.id}`, { replace: true })
    } catch (caught) {
      setErrors(readFieldErrors(caught))
      setSubmitError(readError(caught))
    } finally {
      setSubmitting(false)
    }
  }

  if (items.length === 0) {
    return (
      <div className="wrap checkout">
        <div className="empty">
          <p className="empty__title">Chưa có gì để thanh toán</p>
          <p>Giỏ hàng của bạn đang trống.</p>
          <p style={{ marginTop: 'var(--s4)' }}>
            <Link to="/san-pham" className="btn btn--primary">Chọn nông sản</Link>
          </p>
        </div>
      </div>
    )
  }

  return (
    <div className="wrap checkout">
      <div className="checkout__head">
        <h1>Đặt hàng</h1>
        {!user && (
          <p className="checkout__guest-note">
            Bạn đang đặt hàng với tư cách khách vãng lai. <Link to="/dang-nhap">Đăng nhập</Link> để tích điểm cho đơn này.
          </p>
        )}
      </div>

      <form className="checkout__grid" onSubmit={handleSubmit} noValidate>
        <div className="checkout__form">
          <section className="checkout__section">
            <h2 className="checkout__section-title">Người nhận</h2>

            <div className="field">
              <label className="field__label" htmlFor="ten_nguoi_nhan">Họ tên người nhận</label>
              <input
                id="ten_nguoi_nhan"
                className={`input ${errors.ten_nguoi_nhan ? 'input--invalid' : ''}`}
                value={form.ten_nguoi_nhan}
                onChange={(e) => setField('ten_nguoi_nhan', e.target.value)}
              />
              {errors.ten_nguoi_nhan && <p className="field__error">{errors.ten_nguoi_nhan}</p>}
            </div>

            <div className="field">
              <label className="field__label" htmlFor="so_dien_thoai">Số điện thoại</label>
              <input
                id="so_dien_thoai"
                className={`input data ${errors.so_dien_thoai ? 'input--invalid' : ''}`}
                value={form.so_dien_thoai}
                onChange={(e) => setField('so_dien_thoai', e.target.value)}
                inputMode="tel"
                placeholder="09xx xxx xxx"
              />
              {errors.so_dien_thoai && <p className="field__error">{errors.so_dien_thoai}</p>}
            </div>

            <div className="field">
              <label className="field__label" htmlFor="email_nguoi_nhan">
                Email nhận xác nhận <span className="field__optional">(không bắt buộc)</span>
              </label>
              <input
                id="email_nguoi_nhan"
                type="email"
                className="input"
                value={form.email_nguoi_nhan}
                onChange={(e) => setField('email_nguoi_nhan', e.target.value)}
              />
            </div>

            <div className="field">
              <label className="field__label" htmlFor="dia_chi_giao">Địa chỉ giao hàng</label>
              <textarea
                id="dia_chi_giao"
                className={`textarea ${errors.dia_chi_giao ? 'textarea--invalid' : ''}`}
                value={form.dia_chi_giao}
                onChange={(e) => setField('dia_chi_giao', e.target.value)}
                placeholder="Số nhà, tên đường, phường/xã, quận/huyện, tỉnh/thành"
                rows={3}
              />
              {errors.dia_chi_giao && <p className="field__error">{errors.dia_chi_giao}</p>}
              {shippingEstimate.khoang_cach_km !== null && (
                <p className="field__hint data">
                  Khoảng cách ước tính: {shippingEstimate.khoang_cach_km} km · Phí giao: {formatMoney(shippingEstimate.phi_van_chuyen)}
                </p>
              )}
            </div>

            <div className="field">
              <label className="field__label" htmlFor="loai_giao">Hình thức giao hàng</label>
              <select
                id="loai_giao"
                className="select"
                value={form.loai_giao}
                onChange={(e) => setField('loai_giao', e.target.value)}
              >
                <option value="thuong">Giao thường trong ngày (5.000đ/km, miễn phí đơn ≥ 300k)</option>
                <option value="hoa_toc">Giao hỏa tốc 2 giờ (+15.000đ phụ phí)</option>
              </select>
            </div>
          </section>

          <section className="checkout__section">
            <h2 className="checkout__section-title">Cổng thanh toán</h2>
            <div className="pay-options">
              {[
                { value: 'cod', name: 'Trả tiền khi nhận hàng (COD)', desc: 'Thanh toán tiền mặt khi nhận rau.' },
                { value: 'vietqr', name: 'Chuyển khoản VietQR (Napas 24/7)', desc: 'Quét mã QR ngân hàng / ví điện tử.' },
                { value: 'vnpay', name: 'Cổng thanh toán VNPay Sandbox', desc: 'Thanh toán qua thẻ ATM nội địa VNPay.' },
                { value: 'momo', name: 'Ví điện tử MoMo', desc: 'Thanh toán qua ví MoMo.' },
              ].map((opt) => (
                <label key={opt.value} className={`pay-option ${form.cong_thanh_toan === opt.value ? 'is-active' : ''}`}>
                  <input
                    type="radio"
                    name="cong_thanh_toan"
                    value={opt.value}
                    checked={form.cong_thanh_toan === opt.value}
                    onChange={(e) => setField('cong_thanh_toan', e.target.value)}
                  />
                  <span className="pay-option__body">
                    <span className="pay-option__name">{opt.name}</span>
                    <span className="pay-option__desc">{opt.desc}</span>
                  </span>
                </label>
              ))}
            </div>
          </section>

          {user && maxPoints > 0 && (
            <section className="checkout__section">
              <h2 className="checkout__section-title">Điểm tích luỹ</h2>
              <p className="checkout__points-note">
                Bạn có <strong className="data">{user.diem_tich_luy}</strong> điểm. Mỗi điểm giảm {formatMoney(POINT_VALUE)}.
              </p>
              <div className="points-row">
                <input
                  type="range"
                  min={0}
                  max={maxPoints}
                  step={1}
                  value={pointsUsed}
                  onChange={(e) => setPointsUsed(Number(e.target.value))}
                  className="points-row__slider"
                />
                <input
                  type="number"
                  min={0}
                  max={maxPoints}
                  value={pointsUsed}
                  onChange={(e) => setPointsUsed(Math.max(0, Math.min(maxPoints, Number(e.target.value) || 0)))}
                  className="input data points-row__input"
                />
              </div>
            </section>
          )}
        </div>

        <aside className="checkout__summary">
          <h2 className="checkout__summary-title">Đơn hàng</h2>
          <ul className="checkout__items">
            {items.map((item) => (
              <li key={item.product_id} className="checkout__item">
                <span>
                  {item.ten_san_pham} <span className="data">× {formatQuantity(item.quantity)} {item.don_vi_tinh}</span>
                </span>
                <span className="data">{formatMoney(item.gia * item.quantity)}</span>
              </li>
            ))}
          </ul>

          <dl className="checkout__lines">
            <div><dt>Tiền hàng</dt><dd className="data">{formatMoney(subtotal)}</dd></div>
            <div><dt>Phí vận chuyển</dt><dd className="data">{formatMoney(shippingEstimate.phi_van_chuyen)}</dd></div>
            {discountAmount > 0 && (
              <div><dt>Giảm điểm ({pointsUsed}đ)</dt><dd className="data">−{formatMoney(discountAmount)}</dd></div>
            )}
          </dl>

          <div className="checkout__total">
            <span>Tổng thanh toán</span>
            <strong className="data">{formatMoney(total)}</strong>
          </div>

          {submitError && (
            <p className="checkout__error" role="alert">
              <Icon name="alert" size={15} />
              {submitError}
            </p>
          )}

          <button type="submit" className="btn btn--primary btn--block" disabled={submitting}>
            {submitting ? 'Đang gửi đơn…' : 'Xác nhận đặt hàng'}
            <Icon name="arrow" size={16} />
          </button>
        </aside>
      </form>
    </div>
  )
}
