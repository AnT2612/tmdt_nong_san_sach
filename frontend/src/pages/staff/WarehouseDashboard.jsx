import { useCallback, useEffect, useState } from 'react'
import { apiGet, apiPost, apiPut, readError } from '../../api/client.js'
import Icon from '../../components/Icon.jsx'
import {
  BATCH_STATUS_LABEL,
  DISPOSE_REASON_LIST,
  formatDate,
  formatExpiry,
  formatMoney,
  formatQuantity,
} from '../../utils/format.js'
import './StaffPages.css'

export default function WarehouseDashboard() {
  const [batches, setBatches] = useState([])
  const [loading, setLoading] = useState(true)
  const [message, setMessage] = useState(null)
  const [dialog, setDialog] = useState(null)

  const loadBatches = useCallback(async () => {
    setLoading(true)
    try {
      const response = await apiGet('/batches?per_page=50')
      setBatches(response.data || [])
    } catch {
      setMessage({ type: 'error', text: 'Không tải được danh sách lô hàng.' })
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { loadBatches() }, [loadBatches])

  async function handleDone(text) {
    setMessage({ type: 'ok', text })
    setDialog(null)
    await loadBatches()
  }

  const expiringCount = batches.filter(
    (b) => (b.trang_thai === 'con_hang' || b.trang_thai === 'can_han') && (b.days_until_expiry ?? 99) <= 2,
  ).length

  return (
    <div className="staff">
      <header className="staff__head">
        <div>
          <h1>Kho lô hàng &amp; Hao hụt</h1>
          <p className="staff__sub">Quản lý lô theo hạn dùng · Thuật toán FEFO · Ghi nhận loss_records</p>
        </div>

        <div className="staff__stats">
          <div className="staff__stat">
            <span className="data-label">Tổng số lô</span>
            <span className="data staff__stat-value">{batches.length}</span>
          </div>
          <div className={`staff__stat ${expiringCount > 0 ? 'staff__stat--alert' : ''}`}>
            <span className="data-label">Cận hạn ≤ 2 ngày</span>
            <span className="data staff__stat-value">{expiringCount}</span>
          </div>
        </div>
      </header>

      {message && (
        <p className={`staff__message staff__message--${message.type}`} role="status">{message.text}</p>
      )}

      {loading ? (
        <div className="skeleton" style={{ height: 220 }} />
      ) : (
        <div className="table-wrap">
          <table className="ledger staff-table">
            <thead>
              <tr>
                <th>Mã lô</th>
                <th>Sản phẩm</th>
                <th className="num">Tồn / Nhập (Hao hụt)</th>
                <th>Hạn sử dụng</th>
                <th className="num">Giá xả hàng</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
              </tr>
            </thead>
            <tbody>
              {batches.map((batch) => {
                const isExpiring = batch.so_luong_ton > 0 && (batch.days_until_expiry ?? 99) <= 2
                return (
                  <tr key={batch.id} className={isExpiring ? 'staff-table__row--alert' : ''}>
                    <td className="data staff-table__code">{batch.ma_lo}</td>
                    <td>{batch.product?.ten_san_pham}</td>
                    <td className="num data">
                      {formatQuantity(batch.so_luong_ton)} / {formatQuantity(batch.so_luong_nhap)}{' '}
                      <span className="staff-table__muted">(hụt {formatQuantity(batch.so_luong_hao_hut)})</span>
                    </td>
                    <td>
                      <span className={`tag tag--${isExpiring ? 'danger' : 'good'}`}>
                        {formatDate(batch.ngay_het_han)} · {formatExpiry(batch.days_until_expiry)}
                      </span>
                    </td>
                    <td className="num data">
                      {batch.gia_xa_hang ? formatMoney(batch.gia_xa_hang) : '—'}
                    </td>
                    <td>
                      <span className="tag tag--neutral">{BATCH_STATUS_LABEL[batch.trang_thai] ?? batch.trang_thai}</span>
                    </td>
                    <td>
                      <div className="staff-table__actions">
                        <button
                          type="button"
                          className="btn btn--outline btn--sm"
                          onClick={() => setDialog({ type: 'edit', batch })}
                        >
                          Sửa lô
                        </button>
                        {Number(batch.so_luong_ton) > 0 && (
                          <button
                            type="button"
                            className="btn btn--primary btn--sm"
                            onClick={() => setDialog({ type: 'dispose', batch })}
                          >
                            Xuất hủy
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      )}

      {dialog?.type === 'dispose' && (
        <DisposeDialog batch={dialog.batch} onClose={() => setDialog(null)} onDone={handleDone} />
      )}

      {dialog?.type === 'edit' && (
        <EditBatchDialog batch={dialog.batch} onClose={() => setDialog(null)} onDone={handleDone} />
      )}
    </div>
  )
}

function DisposeDialog({ batch, onClose, onDone }) {
  const [soLuong, setSoLuong] = useState('')
  const [lyDo, setLyDo] = useState(DISPOSE_REASON_LIST[0])
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setError('')

    try {
      await apiPost(`/batches/${batch.id}/dispose`, {
        so_luong: Number(soLuong),
        ly_do: lyDo,
      })

      onDone(`Đã lập phiếu hao hụt ${soLuong} ${batch.product?.don_vi_tinh} cho lô ${batch.ma_lo}.`)
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
          <h2>Ghi nhận xuất hủy lô {batch.ma_lo}</h2>
          <button type="button" className="modal__close" onClick={onClose}>✕</button>
        </div>

        <form className="modal__body" onSubmit={handleSubmit}>
          <p className="staff__dialog-note">
            {batch.product?.ten_san_pham} · Còn {formatQuantity(batch.so_luong_ton)} {batch.product?.don_vi_tinh}.
          </p>

          <div className="field">
            <label className="field__label">Số lượng xuất hủy</label>
            <input
              type="number"
              className="input data"
              min="0.01"
              max={batch.so_luong_ton}
              step="0.1"
              value={soLuong}
              onChange={(e) => setSoLuong(e.target.value)}
            />
          </div>

          <div className="field">
            <label className="field__label">Lý do</label>
            <select className="select" value={lyDo} onChange={(e) => setLyDo(e.target.value)}>
              {DISPOSE_REASON_LIST.map((r) => <option key={r} value={r}>{r}</option>)}
            </select>
          </div>

          {error && <p className="auth__error">{error}</p>}

          <div className="modal__actions">
            <button type="button" className="btn btn--outline" onClick={onClose}>Huỷ</button>
            <button type="submit" className="btn btn--primary" disabled={submitting}>
              {submitting ? 'Đang lưu…' : 'Xác nhận xuất hủy'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

function EditBatchDialog({ batch, onClose, onDone }) {
  const [form, setForm] = useState({
    ngay_thu_hoach: String(batch.ngay_thu_hoach || '').slice(0, 10),
    ngay_het_han: String(batch.ngay_het_han || '').slice(0, 10),
    so_luong_nhap: String(batch.so_luong_nhap || ''),
    gia_xa_hang: batch.gia_xa_hang ? String(batch.gia_xa_hang) : '',
  })
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setError('')

    try {
      await apiPut(`/batches/${batch.id}`, {
        ngay_thu_hoach: form.ngay_thu_hoach,
        ngay_het_han: form.ngay_het_han,
        so_luong_nhap: Number(form.so_luong_nhap),
        gia_xa_hang: form.gia_xa_hang ? Number(form.gia_xa_hang) : null,
      })

      onDone(`Đã cập nhật thông tin lô ${batch.ma_lo}.`)
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
          <h2>Sửa lô {batch.ma_lo}</h2>
          <button type="button" className="modal__close" onClick={onClose}>✕</button>
        </div>

        <form className="modal__body" onSubmit={handleSubmit}>
          <div className="field">
            <label className="field__label">Ngày thu hoạch</label>
            <input type="date" className="input data" value={form.ngay_thu_hoach} onChange={(e) => setForm((f) => ({ ...f, ngay_thu_hoach: e.target.value }))} />
          </div>
          <div className="field">
            <label className="field__label">Ngày hết hạn</label>
            <input type="date" className="input data" value={form.ngay_het_han} onChange={(e) => setForm((f) => ({ ...f, ngay_het_han: e.target.value }))} />
          </div>
          <div className="field">
            <label className="field__label">Số lượng nhập</label>
            <input type="number" step="0.1" className="input data" value={form.so_luong_nhap} onChange={(e) => setForm((f) => ({ ...f, so_luong_nhap: e.target.value }))} />
          </div>
          <div className="field">
            <label className="field__label">Giá xả hàng (trống = bán giá gốc)</label>
            <input type="number" className="input data" value={form.gia_xa_hang} onChange={(e) => setForm((f) => ({ ...f, gia_xa_hang: e.target.value }))} />
          </div>

          {error && <p className="auth__error">{error}</p>}

          <div className="modal__actions">
            <button type="button" className="btn btn--outline" onClick={onClose}>Huỷ</button>
            <button type="submit" className="btn btn--primary" disabled={submitting}>
              {submitting ? 'Đang lưu…' : 'Cập nhật lô'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
