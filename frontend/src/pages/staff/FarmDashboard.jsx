import { useCallback, useEffect, useRef, useState } from 'react'
import { apiGet, apiPost, apiUpload, readError } from '../../api/client.js'
import Icon from '../../components/Icon.jsx'
import { BATCH_STATUS_LABEL, formatDate, formatQuantity } from '../../utils/format.js'
import './StaffPages.css'

export default function FarmDashboard() {
  const [batches, setBatches] = useState([])
  const [products, setProducts] = useState([])
  const [loading, setLoading] = useState(true)
  const [message, setMessage] = useState(null)
  const [activeBatch, setActiveBatch] = useState(null)
  const [creating, setCreating] = useState(false)

  const loadData = useCallback(async () => {
    setLoading(true)
    try {
      const [batchRes, productRes] = await Promise.all([
        apiGet('/batches?per_page=50'),
        apiGet('/products?per_page=50'),
      ])
      setBatches(batchRes.data || [])
      setProducts(productRes.data || [])
    } catch {
      setMessage({ type: 'error', text: 'Không tải được dữ liệu nông trại.' })
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { loadData() }, [loadData])

  return (
    <div className="staff">
      <header className="staff__head">
        <div>
          <h1>Nông trại</h1>
          <p className="staff__sub">Tạo lô · Nhật ký canh tác · Mã QR truy xuất nguồn gốc</p>
        </div>

        <button type="button" className="btn btn--primary" onClick={() => setCreating(true)}>
          <Icon name="plus" size={16} /> Tạo lô mới
        </button>
      </header>

      {message && (
        <p className={`staff__message staff__message--${message.type}`} role="status">{message.text}</p>
      )}

      {loading ? (
        <div className="skeleton" style={{ height: 180 }} />
      ) : (
        <div className="table-wrap">
          <table className="ledger staff-table">
            <thead>
              <tr>
                <th>Mã lô</th>
                <th>Sản phẩm</th>
                <th className="num">Còn / Nhập</th>
                <th>Thu hoạch</th>
                <th>Hạn dùng</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
              </tr>
            </thead>
            <tbody>
              {batches.map((batch) => (
                <tr key={batch.id}>
                  <td className="data staff-table__code">{batch.ma_lo}</td>
                  <td>{batch.product?.ten_san_pham}</td>
                  <td className="num data">
                    {formatQuantity(batch.so_luong_ton)} / {formatQuantity(batch.so_luong_nhap)} {batch.product?.don_vi_tinh}
                  </td>
                  <td className="data">{formatDate(batch.ngay_thu_hoach)}</td>
                  <td className="data">{formatDate(batch.ngay_het_han)}</td>
                  <td>
                    <span className={`tag tag--${batch.trang_thai === 'con_hang' ? 'good' : batch.trang_thai === 'can_han' ? 'warn' : 'neutral'}`}>
                      {BATCH_STATUS_LABEL[batch.trang_thai] ?? batch.trang_thai}
                    </span>
                  </td>
                  <td>
                    <div className="staff-table__actions">
                      <button type="button" className="btn btn--solid btn--sm" onClick={() => setActiveBatch(batch)}>
                        Nhật ký
                      </button>
                      <a
                        className="btn btn--outline btn--sm"
                        href={`/api/v1/traceability/${batch.ma_lo}/qr.svg`}
                        target="_blank"
                        rel="noreferrer"
                      >
                        Mã QR
                      </a>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {creating && (
        <CreateBatchDialog
          products={products}
          onClose={() => setCreating(false)}
          onDone={async (text) => {
            setMessage({ type: 'ok', text })
            setCreating(false)
            await loadData()
          }}
        />
      )}

      {activeBatch && (
        <FarmingLogDrawer
          batch={activeBatch}
          onClose={() => setActiveBatch(null)}
          onLogged={async (text) => {
            setMessage({ type: 'ok', text })
            await loadData()
          }}
        />
      )}
    </div>
  )
}

function CreateBatchDialog({ products, onClose, onDone }) {
  const today = new Date().toISOString().slice(0, 10)
  const nextWeek = new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10)

  const [form, setForm] = useState({
    product_id: products[0]?.id ?? '',
    ngay_thu_hoach: today,
    ngay_het_han: nextWeek,
    so_luong_nhap: '10',
  })
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setError('')

    try {
      const response = await apiPost('/batches', {
        product_id: Number(form.product_id),
        ngay_thu_hoach: form.ngay_thu_hoach,
        ngay_het_han: form.ngay_het_han,
        so_luong_nhap: Number(form.so_luong_nhap),
      })

      onDone(`Đã tạo lô ${response.data.batch.ma_lo}.`)
    } catch (caught) {
      setError(readError(caught))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="modal-backdrop" role="dialog" aria-modal="true">
      <div className="modal">
        <div className="modal__head">
          <h2>Tạo lô hàng mới</h2>
          <button type="button" className="modal__close" onClick={onClose}>✕</button>
        </div>

        <form className="modal__body" onSubmit={handleSubmit}>
          <div className="field">
            <label className="field__label">Sản phẩm</label>
            <select
              className="select"
              value={form.product_id}
              onChange={(e) => setForm((c) => ({ ...c, product_id: e.target.value }))}
            >
              {products.map((p) => (
                <option key={p.id} value={p.id}>{p.ten_san_pham} ({p.don_vi_tinh})</option>
              ))}
            </select>
          </div>

          <div className="field">
            <label className="field__label">Ngày thu hoạch</label>
            <input
              type="date"
              className="input data"
              value={form.ngay_thu_hoach}
              onChange={(e) => setForm((c) => ({ ...c, ngay_thu_hoach: e.target.value }))}
            />
          </div>

          <div className="field">
            <label className="field__label">Ngày hết hạn</label>
            <input
              type="date"
              className="input data"
              value={form.ngay_het_han}
              onChange={(e) => setForm((c) => ({ ...c, ngay_het_han: e.target.value }))}
            />
          </div>

          <div className="field">
            <label className="field__label">Số lượng nhập kho</label>
            <input
              type="number"
              className="input data"
              min="0.01"
              step="0.1"
              value={form.so_luong_nhap}
              onChange={(e) => setForm((c) => ({ ...c, so_luong_nhap: e.target.value }))}
            />
          </div>

          {error && <p className="auth__error">{error}</p>}

          <div className="modal__actions">
            <button type="button" className="btn btn--outline" onClick={onClose}>Huỷ</button>
            <button type="submit" className="btn btn--primary" disabled={submitting}>
              {submitting ? 'Đang tạo…' : 'Tạo lô & Sinh QR'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

function FarmingLogDrawer({ batch, onClose, onLogged }) {
  const [logs, setLogs] = useState([])
  const [loading, setLoading] = useState(true)
  const [form, setForm] = useState({
    hanh_dong: 'Tưới nước nhỏ giọt',
    thoi_gian: new Date().toISOString().slice(0, 16),
    mo_ta: '',
    hinh_anh_minh_chung: '',
  })
  const [uploading, setUploading] = useState(false)
  const fileInputRef = useRef(null)
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const loadLogs = useCallback(async () => {
    setLoading(true)
    try {
      const response = await apiGet(`/batches/${batch.id}/farming-logs`)
      setLogs(response.data.logs || [])
    } finally {
      setLoading(false)
    }
  }, [batch.id])

  useEffect(() => { loadLogs() }, [loadLogs])

  async function handleFile(event) {
    const file = event.target.files?.[0]
    if (!file) return
    setUploading(true)
    try {
      const res = await apiUpload(file)
      if (res.data?.url) setForm((c) => ({ ...c, hinh_anh_minh_chung: res.data.url }))
    } catch (err) {
      setError(readError(err))
    } finally {
      setUploading(false)
    }
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setError('')

    try {
      await apiPost(`/batches/${batch.id}/farming-logs`, {
        hanh_dong: form.hanh_dong,
        thoi_gian: form.thoi_gian,
        mo_ta: form.mo_ta || null,
        hinh_anh_minh_chung: form.hinh_anh_minh_chung || null,
      })

      setForm((c) => ({ ...c, mo_ta: '', hinh_anh_minh_chung: '' }))
      await loadLogs()
      await onLogged(`Đã thêm nhật ký cho lô ${batch.ma_lo}.`)
    } catch (caught) {
      setError(readError(caught))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <>
      <div className="drawer-backdrop" onClick={onClose} aria-hidden="true" />
      <aside className="drawer" role="dialog" aria-modal="true">
        <header className="drawer__head">
          <div>
            <span className="data drawer__code">{batch.ma_lo}</span>
            <h2 className="drawer__title">Nhật ký · {batch.product?.ten_san_pham}</h2>
          </div>
          <button type="button" className="drawer__close" onClick={onClose}>✕</button>
        </header>

        <div className="drawer__body">
          <form className="log-form" onSubmit={handleSubmit}>
            <h3 className="log-form__title">Ghi mốc nhật ký</h3>

            <label className="field">
              <span className="field__label">Hành động</span>
              <input
                className="input"
                value={form.hanh_dong}
                onChange={(e) => setForm((c) => ({ ...c, hanh_dong: e.target.value }))}
                required
              />
            </label>

            <label className="field">
              <span className="field__label">Thời gian</span>
              <input
                type="datetime-local"
                className="input data"
                value={form.thoi_gian}
                onChange={(e) => setForm((c) => ({ ...c, thoi_gian: e.target.value }))}
              />
            </label>

            <label className="field">
              <span className="field__label">Mô tả</span>
              <textarea
                className="textarea"
                rows={2}
                value={form.mo_ta}
                onChange={(e) => setForm((c) => ({ ...c, mo_ta: e.target.value }))}
              />
            </label>

            <div className="field">
              <input type="file" ref={fileInputRef} onChange={handleFile} accept="image/*" style={{ display: 'none' }} />
              <button type="button" className="btn btn--outline btn--sm" onClick={() => fileInputRef.current?.click()} disabled={uploading}>
                {uploading ? 'Đang tải…' : 'Đính kèm ảnh minh chứng'}
              </button>
              {form.hinh_anh_minh_chung && <p className="field__hint">{form.hinh_anh_minh_chung}</p>}
            </div>

            {error && <p className="auth__error">{error}</p>}

            <button type="submit" className="btn btn--primary btn--block" disabled={submitting}>
              {submitting ? 'Đang lưu…' : 'Lưu mốc nhật ký'}
            </button>
          </form>

          <div className="log-list">
            <h3 className="log-form__title">Đã ghi ({logs.length})</h3>
            {loading ? <div className="skeleton" style={{ height: 80 }} /> : logs.map((log) => (
              <div key={log.id} className="log-entry">
                <div className="log-entry__head">
                  <span className="data log-entry__type">{log.hanh_dong}</span>
                  <span className="data log-entry__date">{formatDate(log.thoi_gian)}</span>
                </div>
                {log.mo_ta && <p className="log-entry__desc">{log.mo_ta}</p>}
              </div>
            ))}
          </div>
        </div>
      </aside>
    </>
  )
}
