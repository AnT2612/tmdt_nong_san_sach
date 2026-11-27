import { useCallback, useEffect, useState } from 'react'
import { apiGet, apiPatch, apiPost, readError } from '../../api/client.js'
import Icon from '../../components/Icon.jsx'
import {
  COMPLAINT_OUTCOME_LABEL,
  COMPLAINT_STATUS_LABEL,
  ORDER_STATUS_LABEL,
  formatDateTime,
  formatMoney,
} from '../../utils/format.js'
import './StaffPages.css'

const ORDER_TABS = [
  { key: 'cho_duyet', label: 'Chờ duyệt' },
  { key: 'da_duyet', label: 'Đã duyệt' },
  { key: 'dang_giao', label: 'Đang giao' },
  { key: 'hoan_thanh', label: 'Hoàn thành' },
  { key: '', label: 'Tất cả' },
]

const COMPLAINT_TABS = [
  { key: '', label: 'Tất cả' },
  { key: 'moi', label: 'Mới gửi' },
  { key: 'dang_xu_ly', label: 'Đang xử lý' },
  { key: 'da_xu_ly', label: 'Đã xử lý' },
]

export default function SalesDashboard() {
  const [mainTab, setMainTab] = useState('orders')
  const [orderTab, setOrderTab] = useState('cho_duyet')
  const [orders, setOrders] = useState([])
  const [expiring, setExpiring] = useState([])
  const [loadingOrders, setLoadingOrders] = useState(true)
  const [orderBusyId, setOrderBusyId] = useState(null)

  const [complaintTab, setComplaintTab] = useState('moi')
  const [complaints, setComplaints] = useState([])
  const [loadingComplaints, setLoadingComplaints] = useState(false)
  const [activeComplaint, setActiveComplaint] = useState(null)
  const [handlingSubmitting, setHandlingSubmitting] = useState(false)
  const [handleForm, setHandleForm] = useState({
    huong_xu_ly: 'hoan_tien',
    so_tien_hoan: 0,
  })
  const [message, setMessage] = useState(null)

  const loadOrders = useCallback(async (activeTab = orderTab) => {
    setLoadingOrders(true)
    try {
      const query = activeTab ? `?trang_thai=${activeTab}` : ''
      const response = await apiGet(`/sales/orders${query}`)
      setOrders(response.data || [])
    } catch {
      setMessage({ type: 'error', text: 'Không tải được danh sách đơn hàng.' })
    } finally {
      setLoadingOrders(false)
    }
  }, [orderTab])

  useEffect(() => {
    if (mainTab === 'orders') loadOrders(orderTab)
  }, [mainTab, orderTab, loadOrders])

  useEffect(() => {
    apiGet('/batch-alerts')
      .then((res) => setExpiring(res.data || []))
      .catch(() => setExpiring([]))
  }, [])

  const loadComplaints = useCallback(async (activeTab = complaintTab) => {
    setLoadingComplaints(true)
    try {
      const query = activeTab ? `?trang_thai=${activeTab}` : ''
      const response = await apiGet(`/sales/complaints${query}`)
      setComplaints(response.data || [])
    } catch {
      setMessage({ type: 'error', text: 'Không tải được danh sách khiếu nại.' })
    } finally {
      setLoadingComplaints(false)
    }
  }, [complaintTab])

  useEffect(() => {
    if (mainTab === 'complaints') loadComplaints(complaintTab)
  }, [mainTab, complaintTab, loadComplaints])

  async function updateOrderStatus(orderId, newStatus, label) {
    setOrderBusyId(orderId)
    setMessage(null)
    try {
      await apiPatch(`/orders/${orderId}/status`, { trang_thai: newStatus })
      setMessage({ type: 'ok', text: `${label} thành công.` })
      await loadOrders(orderTab)
    } catch (error) {
      setMessage({ type: 'error', text: readError(error) })
    } finally {
      setOrderBusyId(null)
    }
  }

  async function handleFlashSale(batchId, basePrice) {
    const suggested = Math.round(Number(basePrice || 30000) * 0.7)
    const input = window.prompt('Nhập giá xả hàng cận hạn (VNĐ):', String(suggested))
    if (input === null) return
    try {
      await apiPatch(`/batches/${batchId}/flash-sale`, { gia_xa_hang: Number(input) })
      setMessage({ type: 'ok', text: 'Đã cập nhật giá xả hàng cận hạn.' })
      const res = await apiGet('/batch-alerts')
      setExpiring(res.data || [])
    } catch (err) {
      setMessage({ type: 'error', text: readError(err) })
    }
  }

  function openHandleModal(complaint) {
    setActiveComplaint(complaint)
    setHandleForm({
      huong_xu_ly: 'hoan_tien',
      so_tien_hoan: Number(complaint.order_detail?.thanh_tien || 0),
    })
  }

  async function submitComplaintHandling(e) {
    e.preventDefault()
    if (!activeComplaint) return
    setHandlingSubmitting(true)
    try {
      await apiPost(`/sales/complaints/${activeComplaint.id}/handle`, {
        huong_xu_ly: handleForm.huong_xu_ly,
        so_tien_hoan: handleForm.huong_xu_ly === 'hoan_tien' ? Number(handleForm.so_tien_hoan) : 0,
      })
      setMessage({ type: 'ok', text: 'Đã xử lý khiếu nại thành công.' })
      setActiveComplaint(null)
      await loadComplaints(complaintTab)
    } catch (err) {
      alert(readError(err))
    } finally {
      setHandlingSubmitting(false)
    }
  }

  const openAlerts = expiring.filter((a) => a.trang_thai === 'dang_mo')

  return (
    <div className="staff">
      <header className="staff__head">
        <div>
          <h1>Bán Hàng &amp; CSKH</h1>
          <p className="staff__sub">Duyệt đơn FEFO · Xử lý cảnh báo cận hạn · Giải quyết khiếu nại</p>
        </div>

        <nav className="tabs" style={{ marginBottom: 0 }}>
          <button
            type="button"
            className={`tabs__tab ${mainTab === 'orders' ? 'is-active' : ''}`}
            onClick={() => setMainTab('orders')}
          >
            <Icon name="truck" size={15} /> Đơn hàng
          </button>
          <button
            type="button"
            className={`tabs__tab ${mainTab === 'complaints' ? 'is-active' : ''}`}
            onClick={() => setMainTab('complaints')}
          >
            <Icon name="alert" size={15} /> Khiếu nại &amp; Đổi trả
          </button>
        </nav>
      </header>

      {openAlerts.length > 0 && (
        <div className="alert-strip" style={{ justifyContent: 'space-between', flexWrap: 'wrap' }}>
          <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
            <Icon name="alert" size={17} />
            <div>
              <strong className="data">{openAlerts.length} cảnh báo lô cận hạn</strong>{' '}
              <span>
                {openAlerts.slice(0, 2).map((a) => `${a.batch?.ma_lo} (${a.batch?.product?.ten_san_pham})`).join(' · ')}
              </span>
            </div>
          </div>
          {openAlerts[0]?.batch && (
            <button
              type="button"
              className="btn btn--sm btn--outline"
              onClick={() => handleFlashSale(openAlerts[0].batch.id, openAlerts[0].batch.product?.gia_ban_co_ban)}
            >
              Áp giá xả hàng
            </button>
          )}
        </div>
      )}

      {message && (
        <p className={`staff__message staff__message--${message.type}`} role="status">{message.text}</p>
      )}

      {mainTab === 'orders' && (
        <>
          <div className="tabs" role="tablist">
            {ORDER_TABS.map((item) => (
              <button
                key={item.key || 'all'}
                type="button"
                className={`tabs__tab ${orderTab === item.key ? 'is-active' : ''}`}
                onClick={() => setOrderTab(item.key)}
              >
                {item.label}
              </button>
            ))}
          </div>

          {loadingOrders ? (
            <div className="skeleton" style={{ height: 180 }} />
          ) : orders.length === 0 ? (
            <div className="empty"><p className="empty__title">Không có đơn nào ở mục này</p></div>
          ) : (
            <div className="table-wrap">
              <table className="ledger staff-table">
                <thead>
                  <tr>
                    <th>Mã đơn</th>
                    <th>Người nhận</th>
                    <th>Đặt lúc</th>
                    <th className="num">Tổng tiền</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  {orders.map((order) => (
                    <tr key={order.id}>
                      <td className="data staff-table__code">{order.ma_don}</td>
                      <td>
                        <div className="staff-table__person">
                          <span>{order.ten_nguoi_nhan}</span>
                          <span className="data staff-table__phone">{order.so_dien_thoai}</span>
                        </div>
                      </td>
                      <td className="data">{formatDateTime(order.ngay_tao)}</td>
                      <td className="num data">{formatMoney(order.tong_tien)}</td>
                      <td>
                        <span className="tag tag--neutral">
                          {ORDER_STATUS_LABEL[order.trang_thai] ?? order.trang_thai}
                        </span>
                      </td>
                      <td>
                        {order.trang_thai === 'cho_duyet' && (
                          <button
                            type="button"
                            className="btn btn--primary btn--sm"
                            disabled={orderBusyId === order.id}
                            onClick={() => updateOrderStatus(order.id, 'da_duyet', 'Duyệt đơn FEFO')}
                          >
                            Duyệt đơn FEFO
                          </button>
                        )}
                        {order.trang_thai === 'da_duyet' && (
                          <button
                            type="button"
                            className="btn btn--solid btn--sm"
                            disabled={orderBusyId === order.id}
                            onClick={() => updateOrderStatus(order.id, 'dang_giao', 'Chuyển giao hàng')}
                          >
                            Giao hàng
                          </button>
                        )}
                        {order.trang_thai === 'dang_giao' && (
                          <button
                            type="button"
                            className="btn btn--outline btn--sm"
                            disabled={orderBusyId === order.id}
                            onClick={() => updateOrderStatus(order.id, 'hoan_thanh', 'Xác nhận hoàn thành')}
                          >
                            Hoàn thành
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </>
      )}

      {mainTab === 'complaints' && (
        <>
          <div className="tabs">
            {COMPLAINT_TABS.map((item) => (
              <button
                key={item.key || 'all'}
                type="button"
                className={`tabs__tab ${complaintTab === item.key ? 'is-active' : ''}`}
                onClick={() => setComplaintTab(item.key)}
              >
                {item.label}
              </button>
            ))}
          </div>

          {loadingComplaints ? (
            <div className="skeleton" style={{ height: 180 }} />
          ) : complaints.length === 0 ? (
            <div className="empty"><p className="empty__title">Không có khiếu nại nào</p></div>
          ) : (
            <div className="table-wrap">
              <table className="ledger staff-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Khách &amp; Lô hàng</th>
                    <th>Lý do &amp; Nội dung</th>
                    <th>Minh chứng</th>
                    <th>Trạng thái / Hướng xử lý</th>
                    <th style={{ textAlign: 'right' }}>Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  {complaints.map((item) => (
                    <tr key={item.id}>
                      <td className="data">#{item.id}</td>
                      <td>
                        <div><strong>{item.customer?.ho_ten}</strong></div>
                        <div className="data" style={{ fontSize: '0.8rem' }}>
                          {item.order_detail?.order?.ma_don} · Lô {item.order_detail?.batch?.ma_lo ?? '—'}
                        </div>
                      </td>
                      <td>
                        <strong>{item.ly_do}</strong>
                        {item.noi_dung && <p style={{ margin: '4px 0 0', fontSize: '0.85rem' }}>{item.noi_dung}</p>}
                      </td>
                      <td>
                        <a href={item.minh_chung} target="_blank" rel="noreferrer">Xem minh chứng</a>
                      </td>
                      <td>
                        <span className="tag tag--neutral">{COMPLAINT_STATUS_LABEL[item.trang_thai] ?? item.trang_thai}</span>
                        {item.huong_xu_ly && (
                          <div style={{ fontSize: '0.8rem', marginTop: 4 }}>
                            {COMPLAINT_OUTCOME_LABEL[item.huong_xu_ly]}
                          </div>
                        )}
                      </td>
                      <td style={{ textAlign: 'right' }}>
                        {item.trang_thai !== 'da_xu_ly' ? (
                          <button type="button" className="btn btn--primary btn--sm" onClick={() => openHandleModal(item)}>
                            Xử lý
                          </button>
                        ) : 'Đã đóng'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </>
      )}

      {activeComplaint && (
        <div className="modal-backdrop" role="dialog" aria-modal="true">
          <div className="modal">
            <div className="modal__head">
              <h2>Xử lý khiếu nại #{activeComplaint.id}</h2>
              <button type="button" className="modal__close" onClick={() => setActiveComplaint(null)}>✕</button>
            </div>
            <form className="modal__body" onSubmit={submitComplaintHandling}>
              <div className="field">
                <label className="field__label">Hướng xử lý</label>
                <select
                  className="select"
                  value={handleForm.huong_xu_ly}
                  onChange={(e) => setHandleForm((f) => ({ ...f, huong_xu_ly: e.target.value }))}
                >
                  <option value="hoan_tien">Hoàn tiền</option>
                  <option value="giao_bu">Giao bù (tự sinh đơn giao_bu &amp; trừ FEFO)</option>
                  <option value="tu_choi">Từ chối</option>
                </select>
              </div>

              {handleForm.huong_xu_ly === 'hoan_tien' && (
                <div className="field">
                  <label className="field__label">Số tiền hoàn (VNĐ)</label>
                  <input
                    type="number"
                    className="input data"
                    min="0"
                    value={handleForm.so_tien_hoan}
                    onChange={(e) => setHandleForm((f) => ({ ...f, so_tien_hoan: e.target.value }))}
                  />
                </div>
              )}

              <div className="modal__actions">
                <button type="button" className="btn btn--outline" onClick={() => setActiveComplaint(null)}>Huỷ</button>
                <button type="submit" className="btn btn--primary" disabled={handlingSubmitting}>
                  {handlingSubmitting ? 'Đang lưu…' : 'Xác nhận xử lý'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
