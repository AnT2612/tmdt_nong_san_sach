import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { apiGet, readError } from '../api/client.js'
import Icon from '../components/Icon.jsx'
import { useAuth } from '../context/AuthContext.jsx'
import { formatExpiry } from '../utils/format.js'
import './TraceabilityPage.css'

export default function TraceabilityPage() {
  const { token } = useParams()
  const navigate = useNavigate()
  const { user } = useAuth()

  const [tokenInput, setTokenInput] = useState('')
  const [record, setRecord] = useState(null)
  const [loading, setLoading] = useState(Boolean(token))
  const [error, setError] = useState('')
  const [availableBatches, setAvailableBatches] = useState([])
  const [userBatches, setUserBatches] = useState([])

  useEffect(() => {
    if (!token) {
      setLoading(false)
      return
    }

    let cancelled = false
    setLoading(true)
    setError('')

    apiGet(`/traceability/${encodeURIComponent(token)}`)
      .then((response) => {
        if (!cancelled) setRecord(response.data)
      })
      .catch((caught) => {
        if (!cancelled) setError(readError(caught))
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })

    return () => { cancelled = true }
  }, [token])

  useEffect(() => {
    if (token) return

    apiGet('/available-batches?limit=6')
      .then((res) => setAvailableBatches(res?.data || []))
      .catch(() => {})

    if (user) {
      apiGet('/orders?per_page=10')
        .then((res) => {
          const orders = res?.data || []
          const batches = []
          for (const order of orders) {
            for (const detail of order.order_details || []) {
              if (detail.batch) {
                batches.push({
                  ma_lo: detail.batch.ma_lo,
                  ten_san_pham: detail.product?.ten_san_pham ?? 'Nông sản',
                  ngay_het_han: detail.batch.ngay_het_han,
                  ma_don: order.ma_don,
                })
              }
            }
          }
          setUserBatches(batches)
        })
        .catch(() => {})
    }
  }, [token, user])

  if (!token) {
    return (
      <div className="wrap trace">
        <div className="trace__intro">
          <h1>Tra cứu nguồn gốc</h1>
          <p className="trace__intro-body">
            Nhập mã lô in trên tem bao bì để xem ngày thu hoạch, hạn dùng, giấy kiểm định và nhật ký canh tác.
          </p>
        </div>

        <form
          className="trace__form"
          onSubmit={(event) => {
            event.preventDefault()
            const value = tokenInput.trim()
            if (value) navigate(`/truy-xuat/${encodeURIComponent(value)}`)
          }}
        >
          <label className="field__label" htmlFor="token">Mã lô trên tem hoặc mã QR</label>
          <div className="trace__form-row">
            <input
              id="token"
              className="input data"
              value={tokenInput}
              onChange={(event) => setTokenInput(event.target.value)}
              placeholder="Ví dụ: LO-20261127-002…"
              autoComplete="off"
            />
            <button type="submit" className="btn btn--primary" disabled={!tokenInput.trim()}>
              Tra cứu
            </button>
          </div>
        </form>

        {userBatches.length > 0 && (
          <div className="trace__suggestions">
            <h2 className="trace__suggestions-title">Nông sản trong đơn hàng của bạn</h2>
            <div className="trace__batch-cards">
              {userBatches.map((item, idx) => (
                <div key={`${item.ma_lo}-${idx}`} className="trace__batch-card">
                  <div>
                    <strong className="trace__batch-name">{item.ten_san_pham}</strong>
                    <div className="trace__batch-meta">
                      <span className="data trace__batch-code">Lô: {item.ma_lo}</span>
                      <span className="data">Đơn: #{item.ma_don}</span>
                    </div>
                  </div>
                  <Link to={`/truy-xuat/${item.ma_lo}`} className="btn btn--sm btn--primary">
                    Xem nhật ký →
                  </Link>
                </div>
              ))}
            </div>
          </div>
        )}

        {availableBatches.length > 0 && (
          <div className="trace__suggestions">
            <h2 className="trace__suggestions-title">Lô nông sản đang lưu hành</h2>
            <div className="trace__batch-cards">
              {availableBatches.map((b) => (
                <div key={b.ma_lo} className="trace__batch-card">
                  <div>
                    <strong className="trace__batch-name">{b.ten_san_pham}</strong>
                    <div className="trace__batch-meta">
                      <span className="data trace__batch-code">Mã lô: {b.ma_lo}</span>
                      <span className="data">Thu hoạch: {b.ngay_thu_hoach}</span>
                      <span className="data">HSD: {b.ngay_het_han}</span>
                    </div>
                  </div>
                  <Link to={`/truy-xuat/${b.ma_lo}`} className="btn btn--sm btn--outline">
                    Tra cứu ngay →
                  </Link>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    )
  }

  if (loading) {
    return (
      <div className="wrap trace">
        <div className="skeleton" style={{ height: 120, marginBottom: 24 }} />
        <div className="skeleton" style={{ height: 300 }} />
      </div>
    )
  }

  if (error || !record) {
    return (
      <div className="wrap trace">
        <div className="empty">
          <p className="empty__title">Không tìm thấy lô hàng này</p>
          <p>{error}</p>
          <Link to="/truy-xuat" className="btn btn--outline">Nhập lại mã lô</Link>
        </div>
      </div>
    )
  }

  const { batch, product, farmer, timeline, certificates } = record

  return (
    <div className="trace">
      <div className="trace__header">
        <div className="wrap">
          <div className="trace__top-nav">
            <Link to="/truy-xuat" className="trace__back-link">
              <Icon name="back" size={14} /> Tra cứu lô khác
            </Link>
            <Link to="/don-hang" className="trace__back-link">Đơn hàng của tôi →</Link>
          </div>

          <p className="trace__eyebrow data">Hồ sơ lô hàng</p>
          <h1 className="trace__code data">{batch.ma_lo}</h1>

          <div className="trace__header-grid">
            <div className="trace__header-item">
              <span className="data-label">Sản phẩm</span>
              <span className="trace__header-value">{product.ten_san_pham}</span>
            </div>
            <div className="trace__header-item">
              <span className="data-label">Danh mục</span>
              <span className="trace__header-value">{product.ten_danh_muc ?? '—'}</span>
            </div>
            <div className="trace__header-item">
              <span className="data-label">Nhân viên phụ trách</span>
              <span className="trace__header-value">{farmer.ho_ten ?? '—'}</span>
            </div>
            <div className="trace__header-item">
              <span className="data-label">Chứng nhận</span>
              <span className="trace__header-value">{product.chung_nhan ?? '—'}</span>
            </div>
          </div>
        </div>
      </div>

      <div className="wrap trace__body">
        <section className="trace__dates">
          <div className="trace__date trace__date--key">
            <span className="data-label">Ngày thu hoạch</span>
            <span className="data trace__date-value">{batch.ngay_thu_hoach ?? '—'}</span>
          </div>
          <div className="trace__date">
            <span className="data-label">Hạn sử dụng</span>
            <span className="data trace__date-value">{batch.ngay_het_han ?? '—'}</span>
            <span className={`tag tag--${batch.days_until_expiry <= 2 ? 'danger' : batch.days_until_expiry <= 5 ? 'warn' : 'good'}`}>
              {formatExpiry(batch.days_until_expiry)}
            </span>
          </div>
        </section>

        {certificates?.length > 0 && (
          <section className="trace__timeline-section" style={{ marginBottom: '2rem' }}>
            <div className="trace__section-head">
              <h2>Giấy tờ kiểm định &amp; Chứng nhận</h2>
            </div>
            <ul style={{ listStyle: 'none', padding: 0, display: 'grid', gap: '0.5rem' }}>
              {certificates.map((cert) => (
                <li key={cert.id} style={{ padding: '0.75rem 1rem', border: '1px solid var(--border)', borderRadius: 4 }}>
                  <strong>{cert.ten_chung_nhan}</strong> · Cấp ngày <span className="data">{cert.ngay_cap}</span>
                  {cert.file_minh_chung && (
                    <> · <a href={cert.file_minh_chung} target="_blank" rel="noreferrer">Xem minh chứng</a></>
                  )}
                </li>
              ))}
            </ul>
          </section>
        )}

        <section className="trace__timeline-section">
          <div className="trace__section-head">
            <h2>Nhật ký canh tác</h2>
            <p className="trace__section-note">
              {timeline.length > 0 ? `${timeline.length} mốc đã ghi nhận.` : 'Chưa có mốc nhật ký.'}
            </p>
          </div>

          {timeline.length > 0 && (
            <ol className="timeline">
              {timeline.map((log) => (
                <li key={log.id} className="timeline__item">
                  <div className="timeline__marker"><span className="timeline__dot" aria-hidden="true" /></div>
                  <div className="timeline__content">
                    <div className="timeline__head">
                      <span className="timeline__type data">{log.hanh_dong}</span>
                      <time className="timeline__date data">{log.thoi_gian}</time>
                    </div>
                    {log.mo_ta && <p className="timeline__desc">{log.mo_ta}</p>}
                    {log.hinh_anh_minh_chung && (
                      <div className="timeline__media">
                        <img src={log.hinh_anh_minh_chung} alt={log.hanh_dong} loading="lazy" />
                      </div>
                    )}
                    {log.farm_staff && <p className="timeline__author">Ghi bởi {log.farm_staff}</p>}
                  </div>
                </li>
              ))}
            </ol>
          )}
        </section>
      </div>
    </div>
  )
}
