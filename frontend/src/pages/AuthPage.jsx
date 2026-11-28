import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { readError, readFieldErrors } from '../api/client.js'
import Icon from '../components/Icon.jsx'
import { useAuth } from '../context/AuthContext.jsx'
import '../styles/AuthPage.css'

export default function AuthPage({ mode = 'login' }) {
  const isLogin = mode === 'login'
  const navigate = useNavigate()
  const location = useLocation()
  const { login, register } = useAuth()

  const [form, setForm] = useState({
    ho_ten: '',
    email: '',
    so_dien_thoai: '',
    mat_khau: '',
    mat_khau_confirmation: '',
  })

  const [errors, setErrors] = useState({})
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const redirectTo = location.state?.from ?? '/'

  function setField(name, value) {
    setForm((current) => ({ ...current, [name]: value }))
    setErrors((current) => ({ ...current, [name]: undefined }))
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setError('')
    setErrors({})

    try {
      if (isLogin) {
        await login(form.email, form.mat_khau)
      } else {
        await register({
          ho_ten: form.ho_ten,
          email: form.email,
          so_dien_thoai: form.so_dien_thoai || null,
          mat_khau: form.mat_khau,
          mat_khau_confirmation: form.mat_khau_confirmation,
        })
      }

      navigate(redirectTo, { replace: true })
    } catch (caught) {
      setErrors(readFieldErrors(caught))
      setError(readError(caught))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="auth">
      <div className="auth__aside">
        <Link to="/" className="auth__logo">
          <span className="auth__mark" aria-hidden="true">NS</span>
          Nông Sản Sạch
        </Link>

        <div className="auth__pitch">
          <h1 className="auth__pitch-title">
            Đăng nhập để đơn hàng của bạn được tích điểm và theo dõi.
          </h1>
          <ul className="auth__benefits">
            <li><Icon name="check" size={16} /> Xem lại đơn cũ và mã lô của từng món</li>
            <li><Icon name="check" size={16} /> Tích 1 điểm cho mỗi 10.000đ, dùng trừ trực tiếp</li>
            <li><Icon name="check" size={16} /> Đăng ký gói rau giao Thứ 3 và Thứ 6 hằng tuần</li>
          </ul>
          <p className="auth__guest">
            Chưa muốn tạo tài khoản? Vẫn <Link to="/thanh-toan">đặt hàng với tư cách khách vãng lai</Link>.
          </p>
        </div>
      </div>

      <div className="auth__panel">
        <div className="auth__tabs">
          <Link to="/dang-nhap" className={`auth__tab ${isLogin ? 'is-active' : ''}`} replace>Đăng nhập</Link>
          <Link to="/dang-ky" className={`auth__tab ${!isLogin ? 'is-active' : ''}`} replace>Đăng ký</Link>
        </div>

        <h2 className="auth__title">{isLogin ? 'Chào bạn quay lại' : 'Tạo tài khoản mới'}</h2>

        <form onSubmit={handleSubmit} noValidate>
          {!isLogin && (
            <>
              <div className="field">
                <label className="field__label" htmlFor="ho_ten">Họ và tên</label>
                <input
                  id="ho_ten"
                  className={`input ${errors.ho_ten ? 'input--invalid' : ''}`}
                  value={form.ho_ten}
                  onChange={(e) => setField('ho_ten', e.target.value)}
                />
                {errors.ho_ten && <p className="field__error">{errors.ho_ten}</p>}
              </div>

              <div className="field">
                <label className="field__label" htmlFor="so_dien_thoai">
                  Số điện thoại <span className="field__optional">(không bắt buộc)</span>
                </label>
                <input
                  id="so_dien_thoai"
                  className="input data"
                  value={form.so_dien_thoai}
                  onChange={(e) => setField('so_dien_thoai', e.target.value)}
                  inputMode="tel"
                />
                {errors.so_dien_thoai && <p className="field__error">{errors.so_dien_thoai}</p>}
              </div>
            </>
          )}

          <div className="field">
            <label className="field__label" htmlFor="email">Email</label>
            <input
              id="email"
              type="email"
              className={`input ${errors.email ? 'input--invalid' : ''}`}
              value={form.email}
              onChange={(e) => setField('email', e.target.value)}
            />
            {errors.email && <p className="field__error">{errors.email}</p>}
          </div>

          <div className="field">
            <label className="field__label" htmlFor="mat_khau">Mật khẩu</label>
            <input
              id="mat_khau"
              type="password"
              className={`input ${errors.mat_khau ? 'input--invalid' : ''}`}
              value={form.mat_khau}
              onChange={(e) => setField('mat_khau', e.target.value)}
            />
            {errors.mat_khau && <p className="field__error">{errors.mat_khau}</p>}
            {!isLogin && <p className="field__hint">Ít nhất 8 ký tự.</p>}
          </div>

          {!isLogin && (
            <div className="field">
              <label className="field__label" htmlFor="mat_khau_confirmation">Nhập lại mật khẩu</label>
              <input
                id="mat_khau_confirmation"
                type="password"
                className="input"
                value={form.mat_khau_confirmation}
                onChange={(e) => setField('mat_khau_confirmation', e.target.value)}
              />
            </div>
          )}

          {error && (
            <p className="auth__error" role="alert">
              <Icon name="alert" size={15} />
              {error}
            </p>
          )}

          <button type="submit" className="btn btn--primary btn--block auth__submit" disabled={submitting}>
            {submitting ? 'Đang xử lý…' : isLogin ? 'Đăng nhập' : 'Tạo tài khoản'}
          </button>
        </form>

        <p className="auth__switch">
          {isLogin ? (
            <>Chưa có tài khoản? <Link to="/dang-ky">Đăng ký ngay</Link></>
          ) : (
            <>Đã có tài khoản? <Link to="/dang-nhap">Đăng nhập</Link></>
          )}
        </p>
      </div>
    </div>
  )
}
