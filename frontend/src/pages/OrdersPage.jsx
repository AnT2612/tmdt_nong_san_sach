import { useEffect, useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { apiGet, apiPost, readError } from '../api/client.js'
import Icon from '../components/Icon.jsx'
import {
  ORDER_STATUS_LABEL,
  PAYMENT_STATUS_LABEL,
  formatDateTime,
  formatMoney,
  formatQuantity,
} from '../utils/format.js'
import './OrdersPage.css'

function statusTone(status) {
  if (status === 'hoan_thanh') return 'good'
  if (status === 'da_huy') return 'danger'
  if (status === 'cho_duyet') return 'warn'
  return 'neutral'
}

export function OrderListPage() {
  const [orders, setOrders] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    apiGet('/orders?per_page=20')
      .then((response) => setOrders(response.data))
      .catch(() => setError('Không tải được danh sách đơn hàng.'))
      .finally(() => setLoading(false))
  }, [])

  if (loading) {
    return (
      <div className="wrap orders">
        <h1 className="orders__title">Đơn của tôi</h1>
        <div className="stack">
          {[0, 1, 2].map((index) => (
            <div key={index} className="skeleton" style={{ height: 96 }} />
          ))}
        </div>
      </div>
    )
  }

  if (error) {
    return (
      <div className="wrap orders">
        <h1 className="orders__title">Đơn của tôi</h1>
        <div className="empty"><p className="empty__title">{error}</p></div>
      </div>
    )
  }

  return (
    <div className="wrap orders">
      <div className="orders__head">
        <h1 className="orders__title">Đơn của tôi</h1>
        <p className="data orders__count">{orders.length} đơn</p>
      </div>

      {orders.length === 0 ? (
        <div className="empty">
          <p className="empty__title">Bạn chưa có đơn hàng nào</p>
          <p><Link to="/san-pham" className="btn btn--primary">Chọn nông sản</Link></p>
        </div>
      ) : (
        <div className="order-list">
          {orders.map((order) => (
            <Link key={order.id} to={`/don-hang/${order.id}`} className="order-row">
              <div className="order-row__main">
                <span className="order-row__code data">{order.ma_don}</span>
                <span className="order-row__date data">{formatDateTime(order.ngay_tao)}</span>
              </div>
              <span className={`tag tag--${statusTone(order.trang_thai)}`}>
                {ORDER_STATUS_LABEL[order.trang_thai] ?? order.trang_thai}
              </span>
              <span className="order-row__items data">{order.order_details_count ?? order.order_details?.length ?? 0} dòng</span>
              <span className="order-row__total data">{formatMoney(order.tong_tien)}</span>
              <Icon name="arrow" size={16} className="order-row__arrow" />
            </Link>
          ))}
        </div>
      )}
    </div>
  )
}

export function OrderDetailPage() {
  const { id } = useParams()
  const [searchParams] = useSearchParams()
  const paymentStatus = searchParams.get('payment')
  const vnpCode = searchParams.get('vnp_code')
  const vnpTxn = searchParams.get('vnp_txn')

  const [order, setOrder] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [selectedBatchQr, setSelectedBatchQr] = useState(null)
  const [confirmingFefo, setConfirmingFefo] = useState(false)
  const [demoSuccessMsg, setDemoSuccessMsg] = useState('')
  const [payingVNPay, setPayingVNPay] = useState(false)
  const [payError, setPayError] = useState('')

  useEffect(() => {
    apiGet(`/orders/${id}`)
      .then((response) => setOrder(response.data))
      .catch(() => setError('Không tìm thấy đơn hàng này.'))
      .finally(() => setLoading(false))
  }, [id])

  async function handleConfirmPaid() {
    if (!order) return
    try {
      const res = await apiPost('/payments/confirm', { order_id: order.id })
      setOrder(res.data?.order || res.data)
      setDemoSuccessMsg('Đã xác nhận thanh toán và tự động phân bổ lô hàng theo FEFO!')
    } catch (caught) {
      alert(readError(caught))
    }
  }

  async function handlePayVNPay() {
    if (!order) return
    setPayingVNPay(true)
    setPayError('')
    try {
      const res = await apiPost('/vnpay/create', { order_id: order.id })
      if (res.payment_url) {
        window.location.href = res.payment_url
        return
      }
      setPayError('Không nhận được URL thanh toán.')
    } catch (caught) {
      setPayError(readError(caught))
    } finally {
      setPayingVNPay(false)
    }
  }

  async function handleDemoConfirm() {
    if (!order) return
    setConfirmingFefo(true)
    setDemoSuccessMsg('')
    try {
      const response = await apiPost(`/orders/${order.id}/demo-confirm`)
      setOrder(response.data)
      setDemoSuccessMsg('Đã mô phỏng Sales duyệt đơn và phân bổ lô FEFO thành công!')
    } catch (caught) {
      alert(readError(caught))
    } finally {
      setConfirmingFefo(false)
    }
  }

  if (loading) return <div className="wrap orders"><div className="skeleton" style={{ height: 260 }} /></div>
  if (error || !order) {
    return (
      <div className="wrap orders">
        <div className="empty">
          <p className="empty__title">{error}</p>
          <Link to="/don-hang">← Về danh sách đơn</Link>
        </div>
      </div>
    )
  }

  const details = order.order_details || []
  const allocatedDetails = details.filter((d) => d.batch)
  const latestPayment = order.payments?.[0]
  const isPaid = latestPayment?.trang_thai === 'thanh_cong'
  const canComplain = order.trang_thai === 'hoan_thanh'

  return (
    <div className="wrap orders">
      <Link to="/don-hang" className="orders__back">
        <Icon name="back" size={15} />
        Đơn của tôi
      </Link>

      {paymentStatus === 'success' && (
        <div className="order-alert order-alert--good">
          <Icon name="check" size={20} />
          <div>
            <strong>Thanh toán VNPay thành công!</strong>
            <p>Đơn {order.ma_don} đã thanh toán {formatMoney(order.tong_tien)}. {vnpTxn && `Mã GD: ${vnpTxn}`}</p>
          </div>
        </div>
      )}

      {paymentStatus === 'failed' && (
        <div className="order-alert order-alert--danger">
          <Icon name="alert" size={20} />
          <div><strong>Thanh toán thất bại (Mã {vnpCode})</strong></div>
        </div>
      )}

      <div className="order-detail__head">
        <div>
          <h1 className="orders__title data">{order.ma_don}</h1>
          <p className="order-detail__date data">Đặt lúc {formatDateTime(order.ngay_tao)}</p>
        </div>

        <div className="order-detail__tags">
          <span className={`tag tag--${statusTone(order.trang_thai)}`}>
            {ORDER_STATUS_LABEL[order.trang_thai] ?? order.trang_thai}
          </span>
          <span className={`tag tag--${isPaid ? 'good' : 'neutral'}`}>
            {PAYMENT_STATUS_LABEL[latestPayment?.trang_thai || 'cho']}
          </span>
        </div>
      </div>

      <div className="order-detail__grid">
        <div>
          <section className="order-block">
            <h2 className="order-block__title">Chi tiết đơn hàng</h2>
            <table className="ledger">
              <thead>
                <tr>
                  <th>Sản phẩm</th>
                  <th>Lô xuất (FEFO)</th>
                  <th className="num">SL</th>
                  <th className="num">Đơn giá</th>
                  <th className="num">Thành tiền</th>
                </tr>
              </thead>
              <tbody>
                {details.map((item) => (
                  <tr key={item.id}>
                    <td>{item.product?.ten_san_pham ?? `#${item.product_id}`}</td>
                    <td className="data">{item.batch?.ma_lo ?? 'Chờ duyệt FEFO'}</td>
                    <td className="num data">{formatQuantity(item.so_luong)} {item.product?.don_vi_tinh}</td>
                    <td className="num data">{formatMoney(item.don_gia)}</td>
                    <td className="num data">{formatMoney(item.thanh_tien)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </section>

          {allocatedDetails.length > 0 ? (
            <section className="order-block">
              <div className="order-block__header-row">
                <h2 className="order-block__title">Truy xuất nguồn gốc từng lô (FEFO)</h2>
                <span className="badge-fefo">✓ Đã phân bổ lô</span>
              </div>

              <div className="alloc-cards">
                {allocatedDetails.map((detail) => (
                  <div key={detail.id} className="alloc-card">
                    <div className="alloc-card__info">
                      <div className="alloc-card__title-row">
                        <strong>{detail.product?.ten_san_pham}</strong>
                        <span className="data">{formatQuantity(detail.so_luong)} {detail.product?.don_vi_tinh}</span>
                      </div>
                      <div className="alloc-card__meta">
                        <span className="data">Mã lô: <strong>{detail.batch.ma_lo}</strong></span>
                        <span className="data">HSD: {detail.batch.ngay_het_han}</span>
                      </div>
                    </div>
                    <div className="alloc-card__actions">
                      <Link to={`/truy-xuat/${detail.batch.ma_lo}`} className="btn btn--sm btn--primary">
                        Kiểm tra nguồn gốc →
                      </Link>
                      <button
                        type="button"
                        className="btn btn--sm btn--outline"
                        onClick={() => setSelectedBatchQr(detail.batch)}
                      >
                        Mã QR tem
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            </section>
          ) : (
            <section className="order-block">
              <h2 className="order-block__title">Phân bổ lô FEFO</h2>
              <p className="order-block__note">
                Đơn hàng đang chờ Sales duyệt để chạy thuật toán FEFO trừ kho.
              </p>
              {demoSuccessMsg && <p className="order-demo-alert">{demoSuccessMsg}</p>}
              {order.trang_thai === 'cho_duyet' && (
                <div className="order-demo-actions">
                  <button
                    type="button"
                    className="btn btn--sm btn--primary"
                    onClick={handleDemoConfirm}
                    disabled={confirmingFefo}
                  >
                    {confirmingFefo ? 'Đang phân bổ…' : '⚡ Duyệt đơn & Gán lô FEFO (Demo)'}
                  </button>
                </div>
              )}
            </section>
          )}
        </div>

        <aside>
          {!isPaid && order.trang_thai !== 'da_huy' && (
            <section className="order-block order-block--pay">
              <h2 className="order-block__title">Thanh toán ({latestPayment?.cong_thanh_toan?.toUpperCase()})</h2>
              {latestPayment?.cong_thanh_toan === 'vnpay' ? (
                <button type="button" className="btn btn--primary btn--block" onClick={handlePayVNPay} disabled={payingVNPay}>
                  {payingVNPay ? 'Đang chuyển VNPay…' : 'Thanh toán qua VNPay →'}
                </button>
              ) : (
                <button type="button" className="btn btn--primary btn--block" onClick={handleConfirmPaid}>
                  ✓ Xác nhận đã thanh toán
                </button>
              )}
              {payError && <p className="checkout__error">{payError}</p>}
            </section>
          )}

          <section className="order-block order-block--kraft">
            <h2 className="order-block__title">Tổng tiền</h2>
            <dl className="order-totals">
              <div><dt>Phí giao ({order.khoang_cach_km ?? '—'} km)</dt><dd className="data">{formatMoney(order.phi_van_chuyen)}</dd></div>
              {Number(order.tien_giam_diem) > 0 && (
                <div><dt>Giảm điểm ({order.diem_su_dung}đ)</dt><dd className="data">−{formatMoney(order.tien_giam_diem)}</dd></div>
              )}
            </dl>
            <div className="order-totals__grand">
              <span>Tổng thanh toán</span>
              <strong className="data">{formatMoney(order.tong_tien)}</strong>
            </div>
          </section>

          <section className="order-block">
            <h2 className="order-block__title">Giao đến</h2>
            <p className="order-address">
              <strong>{order.ten_nguoi_nhan}</strong><br />
              <span className="data">{order.so_dien_thoai}</span><br />
              {order.dia_chi_giao}
            </p>
          </section>

          {canComplain && (
            <section className="order-block">
              <h2 className="order-block__title">Khiếu nại / Đổi trả (trong 24h)</h2>
              <Link to={`/khieu-nai/${order.id}`} className="btn btn--outline btn--block">
                Gửi khiếu nại sản phẩm
              </Link>
            </section>
          )}
        </aside>
      </div>

      {selectedBatchQr && (
        <div className="qr-modal__backdrop" role="dialog" aria-modal="true">
          <div className="qr-modal__card qr-modal__card--sm">
            <div className="qr-modal__header">
              <h2 className="qr-modal__title">Mã QR tem lô {selectedBatchQr.ma_lo}</h2>
              <button type="button" className="btn btn--sm btn--ghost" onClick={() => setSelectedBatchQr(null)}>✕</button>
            </div>
            <div className="qr-modal__body qr-modal__body--center">
              <div className="qr-modal__qr-frame">
                <img
                  src={`/api/v1/traceability/${selectedBatchQr.ma_lo}/qr.svg`}
                  alt={`QR ${selectedBatchQr.ma_lo}`}
                  className="qr-modal__qr-img"
                />
              </div>
              <Link to={`/truy-xuat/${selectedBatchQr.ma_lo}`} className="btn btn--primary" onClick={() => setSelectedBatchQr(null)}>
                Mở trang truy xuất nguồn gốc →
              </Link>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
