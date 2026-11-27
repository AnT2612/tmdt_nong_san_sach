import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { apiGet, apiPost, apiUpload, readError, readFieldErrors } from '../api/client.js'
import Icon from '../components/Icon.jsx'
import { COMPLAINT_REASON_LIST, formatMoney } from '../utils/format.js'
import './ComplaintPage.css'

export default function ComplaintPage() {
  const { orderId } = useParams()
  const navigate = useNavigate()

  const [order, setOrder] = useState(null)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState('')

  const [form, setForm] = useState({
    order_detail_id: '',
    ly_do: COMPLAINT_REASON_LIST[0],
    noi_dung: '',
    minh_chung: '',
  })
  const [uploading, setUploading] = useState(false)
  const fileInputRef = useRef(null)
  const [errors, setErrors] = useState({})
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => {
    apiGet(`/orders/${orderId}`)
      .then((response) => {
        setOrder(response.data)
        const firstDetail = response.data?.order_details?.[0]
        if (firstDetail) {
          setForm((prev) => ({ ...prev, order_detail_id: String(firstDetail.id) }))
        }
      })
      .catch(() => setLoadError('Không tìm thấy đơn hàng này.'))
      .finally(() => setLoading(false))
  }, [orderId])

  async function handleFileUpload(event) {
    const file = event.target.files?.[0]
    if (!file) return

    const isImage = file.type.startsWith('image/')
    const maxSizeBytes = isImage ? 5 * 1024 * 1024 : 30 * 1024 * 1024
    if (file.size > maxSizeBytes) {
      setErrors((cur) => ({
        ...cur,
        minh_chung: isImage ? 'Ảnh không được vượt quá 5MB.' : 'Video không được vượt quá 30MB.',
      }))
      return
    }

    setUploading(true)
    setErrors((cur) => ({ ...cur, minh_chung: undefined }))

    try {
      const response = await apiUpload(file)
      if (response.data?.url) {
        setForm((cur) => ({ ...cur, minh_chung: response.data.url }))
      }
    } catch (err) {
      setErrors((cur) => ({ ...cur, minh_chung: readError(err) }))
    } finally {
      setUploading(false)
      if (event.target) event.target.value = ''
    }
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setError('')
    setErrors({})

    try {
      await apiPost('/complaints', {
        order_detail_id: Number(form.order_detail_id),
        ly_do: form.ly_do,
        noi_dung: form.noi_dung || null,
        minh_chung: form.minh_chung.trim(),
      })

      navigate(`/don-hang/${orderId}`, { replace: true })
    } catch (caught) {
      setErrors(readFieldErrors(caught))
      setError(readError(caught))
    } finally {
      setSubmitting(false)
    }
  }

  if (loading) return <div className="wrap complaint"><div className="skeleton" style={{ height: 240 }} /></div>

  if (loadError || !order) {
    return (
      <div className="wrap complaint">
        <div className="empty">
          <p className="empty__title">{loadError}</p>
          <Link to="/don-hang">← Về danh sách đơn</Link>
        </div>
      </div>
    )
  }

  if (order.trang_thai !== 'hoan_thanh') {
    return (
      <div className="wrap complaint">
        <Link to={`/don-hang/${orderId}`} className="orders__back">
          <Icon name="back" size={15} /> Về đơn hàng
        </Link>
        <div className="empty">
          <p className="empty__title">Không thể gửi khiếu nại cho đơn này</p>
          <p>Chỉ nhận khiếu nại cho đơn hàng đã hoàn thành giao.</p>
        </div>
      </div>
    )
  }

  return (
    <div className="wrap complaint">
      <Link to={`/don-hang/${orderId}`} className="orders__back">
        <Icon name="back" size={15} /> Về đơn hàng
      </Link>

      <div className="complaint__head">
        <h1>Gửi khiếu nại &amp; Đổi trả</h1>
        <p className="complaint__order data">
          Đơn {order.ma_don} · {formatMoney(order.tong_tien)}
        </p>
      </div>

      <div className="complaint__grid">
        <form className="complaint__form" onSubmit={handleSubmit}>
          <div className="field">
            <label className="field__label" htmlFor="order_detail_id">Chọn dòng sản phẩm / lô hàng bị lỗi</label>
            <select
              id="order_detail_id"
              className="select"
              value={form.order_detail_id}
              onChange={(e) => setForm((cur) => ({ ...cur, order_detail_id: e.target.value }))}
            >
              {(order.order_details || []).map((d) => (
                <option key={d.id} value={d.id}>
                  {d.product?.ten_san_pham} ({d.so_luong} {d.product?.don_vi_tinh}) {d.batch ? `— Lô ${d.batch.ma_lo}` : ''}
                </option>
              ))}
            </select>
            {errors.order_detail_id && <p className="field__error">{errors.order_detail_id}</p>}
          </div>

          <fieldset className="complaint__fieldset">
            <legend className="field__label">Lý do khiếu nại</legend>
            <div className="reason-grid">
              {COMPLAINT_REASON_LIST.map((reason) => (
                <label key={reason} className={`reason ${form.ly_do === reason ? 'is-active' : ''}`}>
                  <input
                    type="radio"
                    name="ly_do"
                    value={reason}
                    checked={form.ly_do === reason}
                    onChange={(e) => setForm((cur) => ({ ...cur, ly_do: e.target.value }))}
                  />
                  {reason}
                </label>
              ))}
            </div>
          </fieldset>

          <div className="field">
            <label className="field__label" htmlFor="noi_dung">Nội dung chi tiết</label>
            <textarea
              id="noi_dung"
              className="textarea"
              rows={3}
              value={form.noi_dung}
              onChange={(e) => setForm((cur) => ({ ...cur, noi_dung: e.target.value }))}
              placeholder="Mô tả tình trạng rau củ..."
            />
          </div>

          <div className="field">
            <label className="field__label" htmlFor="minh_chung">
              Ảnh / video minh chứng <span className="field__required">bắt buộc</span>
            </label>
            <input
              type="file"
              ref={fileInputRef}
              onChange={handleFileUpload}
              accept="image/*,video/*"
              style={{ display: 'none' }}
            />
            <div style={{ display: 'flex', gap: '0.75rem', alignItems: 'center', marginBottom: '0.5rem' }}>
              <button
                type="button"
                className="btn btn--outline"
                onClick={() => fileInputRef.current?.click()}
                disabled={uploading}
              >
                <Icon name="upload" size={16} />
                {uploading ? 'Đang tải lên…' : 'Chọn ảnh / video (≤ 5MB)'}
              </button>
            </div>
            <input
              id="minh_chung"
              className="input"
              value={form.minh_chung}
              onChange={(e) => setForm((cur) => ({ ...cur, minh_chung: e.target.value }))}
              placeholder="Hoặc dán URL ảnh minh chứng..."
            />
            {errors.minh_chung && <p className="field__error">{errors.minh_chung}</p>}
          </div>

          {error && <p className="auth__error">{error}</p>}

          <button type="submit" className="btn btn--primary" disabled={submitting}>
            {submitting ? 'Đang gửi…' : 'Gửi khiếu nại'}
          </button>
        </form>
      </div>
    </div>
  )
}
