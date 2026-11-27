import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { apiGet, apiPost, readError, readFieldErrors } from '../api/client.js'
import Icon from '../components/Icon.jsx'
import { useAuth } from '../context/AuthContext.jsx'
import { formatMoney, formatQuantity } from '../utils/format.js'
import './SubscriptionPage.css'

const WEEKDAYS = [
  { value: 2, short: 'T2', full: 'Thứ 2' },
  { value: 3, short: 'T3', full: 'Thứ 3' },
  { value: 4, short: 'T4', full: 'Thứ 4' },
  { value: 5, short: 'T5', full: 'Thứ 5' },
  { value: 6, short: 'T6', full: 'Thứ 6' },
  { value: 7, short: 'T7', full: 'Thứ 7' },
  { value: 8, short: 'CN', full: 'Chủ nhật' },
]

export default function SubscriptionPage() {
  const { user } = useAuth()
  const navigate = useNavigate()

  const [plans, setPlans] = useState([])
  const [mySubscriptions, setMySubscriptions] = useState([])
  const [loading, setLoading] = useState(true)
  const [selectedPlan, setSelectedPlan] = useState(null)

  useEffect(() => {
    async function load() {
      try {
        const response = await apiGet('/subscription-plans')
        setPlans(response.data || [])

        if (user) {
          const mine = await apiGet('/my-subscriptions').catch(() => null)
          if (mine?.data) setMySubscriptions(mine.data)
        }
      } finally {
        setLoading(false)
      }
    }

    load()
  }, [user])

  return (
    <div className="wrap subscription">
      <div className="subscription__intro">
        <h1>Gói rau định kỳ</h1>
        <p>
          Thanh toán trước một tháng, nông trại tự động sinh lịch giao (<code>delivery_schedules</code>)
          và giao đúng thứ cố định hằng tuần.
        </p>
      </div>

      {mySubscriptions.length > 0 && (
        <section className="my-subs">
          <h2 className="my-subs__title">Gói của bạn</h2>

          {mySubscriptions.map((sub) => {
            const nextSchedule = sub.delivery_schedules?.find((s) => s.trang_thai === 'cho_sinh_don')
            return (
              <div key={sub.id} className="my-sub">
                <div className="my-sub__main">
                  <span className="data my-sub__code">SUB-#{sub.id}</span>
                  <h3>{sub.ten_goi}</h3>
                  <p className="data my-sub__dates">
                    {new Date(sub.ngay_bat_dau).toLocaleDateString('vi-VN')} —{' '}
                    {new Date(sub.ngay_ket_thuc).toLocaleDateString('vi-VN')}
                  </p>
                </div>

                <div className="my-sub__next">
                  <span className="data-label">Kỳ giao tới</span>
                  <span className="data">
                    {nextSchedule ? new Date(nextSchedule.ngay_giao).toLocaleDateString('vi-VN') : '—'}
                  </span>
                </div>

                <span className={`tag tag--${sub.trang_thai === 'active' ? 'good' : 'neutral'}`}>
                  {sub.trang_thai === 'active' ? 'Đang hoạt động' : sub.trang_thai}
                </span>
              </div>
            )
          })}
        </section>
      )}

      {loading ? (
        <div className="plan-grid">
          {[0, 1].map((index) => <div key={index} className="skeleton" style={{ height: 380 }} />)}
        </div>
      ) : (
        <div className="plan-grid">
          {plans.map((plan, index) => {
            const weekdays = String(plan.ngay_giao_trong_tuan || '')
              .split(',')
              .map((v) => Number(v.trim()))
              .filter(Boolean)

            return (
              <article key={plan.code} className={`plan-card ${index === 0 ? 'plan-card--featured' : ''}`}>
                {index === 0 && <span className="plan-card__badge">Phổ biến nhất</span>}

                <header className="plan-card__head">
                  <h2 className="plan-card__name">{plan.ten_goi}</h2>
                  <p className="plan-card__desc">{plan.mo_ta}</p>
                </header>

                <div className="plan-card__price">
                  <span className="data">{formatMoney(plan.gia_goi)}</span>
                  <span className="plan-card__period">/ {plan.thoi_han_ngay} ngày</span>
                </div>

                <div className="plan-card__schedule">
                  <span className="data-label">Lịch giao hằng tuần</span>
                  <div className="weekday-row">
                    {WEEKDAYS.map((day) => (
                      <span
                        key={day.value}
                        className={`weekday ${weekdays.includes(day.value) ? 'is-active' : ''}`}
                        title={day.full}
                      >
                        {day.short}
                      </span>
                    ))}
                  </div>
                </div>

                <div className="plan-card__items">
                  <span className="data-label">Mỗi lần giao gồm</span>
                  <ul>
                    {(plan.items ?? []).map((item, idx) => (
                      <li key={idx}>
                        <span className="data">{formatQuantity(item.so_luong)}</span>{' '}
                        {item.don_vi_tinh} {item.ten_san_pham}
                      </li>
                    ))}
                  </ul>
                </div>

                <button
                  type="button"
                  className="btn btn--primary btn--block"
                  onClick={() => {
                    if (!user) {
                      navigate('/dang-nhap', { state: { from: '/goi-dinh-ky' } })
                      return
                    }
                    setSelectedPlan(plan)
                  }}
                >
                  Đăng ký gói này
                  <Icon name="arrow" size={16} />
                </button>
              </article>
            )
          })}
        </div>
      )}

      {selectedPlan && (
        <SubscribeForm
          plan={selectedPlan}
          onClose={() => setSelectedPlan(null)}
          onDone={() => {
            setSelectedPlan(null)
            window.location.reload()
          }}
        />
      )}
    </div>
  )
}

function SubscribeForm({ plan, onClose, onDone }) {
  const initialWeekdays = String(plan.ngay_giao_trong_tuan || '3,6')
    .split(',')
    .map((v) => Number(v.trim()))
    .filter(Boolean)

  const [form, setForm] = useState({
    dia_chi_giao: '',
    ngay_bat_dau: new Date().toISOString().slice(0, 10),
    khung_gio: '08:00-10:00',
    cong_thanh_toan: 'vnpay',
  })

  const [weekdays, setWeekdays] = useState(initialWeekdays)
  const [errors, setErrors] = useState({})
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  function toggleWeekday(value) {
    setWeekdays((current) =>
      current.includes(value) ? current.filter((d) => d !== value) : [...current, value].sort(),
    )
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setError('')
    setErrors({})

    try {
      await apiPost('/subscriptions', {
        plan_code: plan.code,
        ngay_bat_dau: form.ngay_bat_dau,
        ngay_giao_trong_tuan: weekdays.join(','),
        dia_chi_giao: form.dia_chi_giao,
        khung_gio: form.khung_gio,
        cong_thanh_toan: form.cong_thanh_toan,
      })

      onDone()
    } catch (caught) {
      setErrors(readFieldErrors(caught))
      setError(readError(caught))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="sub-title">
      <div className="modal">
        <div className="modal__head">
          <h2 id="sub-title">Đăng ký {plan.ten_goi}</h2>
          <button type="button" className="modal__close" onClick={onClose} aria-label="Đóng">
            <Icon name="close" size={20} />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="modal__body" noValidate>
          <div className="field">
            <label className="field__label" htmlFor="sub-address">Địa chỉ giao hàng</label>
            <textarea
              id="sub-address"
              className="textarea"
              rows={2}
              value={form.dia_chi_giao}
              onChange={(e) => setForm((f) => ({ ...f, dia_chi_giao: e.target.value }))}
              placeholder="Số nhà, đường, quận/huyện, TP.HCM"
            />
            {errors.dia_chi_giao && <p className="field__error">{errors.dia_chi_giao}</p>}
          </div>

          <div className="field">
            <span className="field__label">Thứ giao trong tuần</span>
            <div className="weekday-picker">
              {WEEKDAYS.map((day) => (
                <button
                  key={day.value}
                  type="button"
                  className={`weekday-pick ${weekdays.includes(day.value) ? 'is-active' : ''}`}
                  onClick={() => toggleWeekday(day.value)}
                >
                  {day.short}
                </button>
              ))}
            </div>
          </div>

          <div className="field">
            <label className="field__label" htmlFor="sub-slot">Khung giờ giao</label>
            <select
              id="sub-slot"
              className="select"
              value={form.khung_gio}
              onChange={(e) => setForm((f) => ({ ...f, khung_gio: e.target.value }))}
            >
              <option value="08:00-10:00">08:00 – 10:00</option>
              <option value="14:00-16:00">14:00 – 16:00</option>
              <option value="17:00-19:00">17:00 – 19:00</option>
            </select>
          </div>

          {error && <p className="auth__error">{error}</p>}

          <div className="modal__actions">
            <button type="button" className="btn btn--outline" onClick={onClose}>Huỷ</button>
            <button type="submit" className="btn btn--primary" disabled={submitting}>
              {submitting ? 'Đang đăng ký…' : 'Xác nhận đăng ký'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
