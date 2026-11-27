import { useEffect, useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { apiGet, apiPost, readError } from '../api/client.js'
import Icon from '../components/Icon.jsx'
import { useAuth } from '../context/AuthContext.jsx'
import {
  ORDER_STATUS_LABEL,
  PAYMENT_STATUS_LABEL,
  formatDateTime,
  formatMoney,
  formatQuantity,
} from '../utils/format.js'
import '../styles/OrdersPage.css'

const RECENT_ORDERS_KEY = 'nongsan_recent_orders'

function readRecentOrders() {
  try {
    const raw = localStorage.getItem(RECENT_ORDERS_KEY)
    const parsed = raw ? JSON.parse(raw) : []
    return Array.isArray(parsed) ? parsed : []
  } catch {
    return []
  }
}

function statusTone(status) {
  if (status === 'hoan_thanh') return 'good'
  if (status === 'da_huy') return 'danger'
  if (status === 'cho_duyet') return 'warn'
  return 'neutral'
}

export function OrderListPage() {
  const { user, loading: authLoading } = useAuth()
  const [orders, setOrders] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    if (authLoading) return

    if (!user) {
      setOrders(readRecentOrders())
      setLoading(false)
      return
    }

    setLoading(true)
    apiGet('/orders?per_page=20')
      .then((response) => setOrders(response.data))
      .catch(() => setError('Không tải được danh sách đơn hàng.'))
      .finally(() => setLoading(false))
  }, [user, authLoading])

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

      {!user && (
        <p className="order-note" style={{ marginBottom: 'var(--s4)' }}>
          Đang hiển thị các đơn vừa đặt trên trình duyệt này. <Link to="/dang-nhap">Đăng nhập</Link> để xem toàn bộ đơn hàng của tài khoản.
        </p>
      )}

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
  const { hasRole } = useAuth()
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
  const [checkingPayment, setCheckingPayment] = useState(false)
  const [payError, setPayError] = useState('')

  useEffect(() => {
    apiGet(`/orders/${id}`)
      .then((response) => setOrder(response.data))
      .catch(() => setError('Không tìm thấy đơn hàng này.'))
      .finally(() => setLoading(false))
  }, [id])

  useEffect(() => {
    const latest = order?.payments?.[0]
    const paid = latest?.trang_thai === 'thanh_cong'
    if (!order || paid || latest?.cong_thanh_toan !== 'vietqr' || order.trang_thai === 'da_huy') {
      return
    }

    const timer = setInterval(() => {
      apiGet(`/orders/${id}`)
        .then((response) => {
          const updated = response.data
          setOrder(updated)
          if (updated?.payments?.[0]?.trang_thai === 'thanh_cong') {
            setDemoSuccessMsg('Đã nhận chuyển khoản VietQR thành công và xác nhận đơn hàng!')
          }
        })
        .catch(() => {})
    }, 3000)

    return () => clearInterval(timer)
  }, [id, order])

  async function handleConfirmPaid() {
    if (!order) return
    setPayError('')
    try {
      const res = await apiPost('/payments/confirm', { order_id: order.id })
      setOrder(res.data?.order || res.data)
      setDemoSuccessMsg('Đã xác nhận thanh toán và tự động phân bổ lô hàng theo FEFO!')
    } catch (caught) {
      setPayError(readError(caught))
    }
  }

  async function handleCheckVietQrPayment() {
    if (!order) return
    setCheckingPayment(true)
    setPayError('')
    try {
      const res = await apiGet(`/orders/${order.id}`)
      const updated = res.data
      setOrder(updated)
      if (updated?.payments?.[0]?.trang_thai === 'thanh_cong') {
        setDemoSuccessMsg('Đã nhận chuyển khoản VietQR thành công và xác nhận đơn hàng!')
      } else {
        setPayError('Chưa ghi nhận giao dịch chuyển khoản từ ngân hàng. Vui lòng hoàn tất chuyển khoản theo mã QR hoặc chờ vài giây.')
      }
    } catch (caught) {
      setPayError(readError(caught))
    } finally {
      setCheckingPayment(false)
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

      {demoSuccessMsg && (
        <div className="order-alert order-alert--good">
          <Icon name="check" size={20} />
          <div><strong>{demoSuccessMsg}</strong></div>
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
                <span className="badge-fefo"><Icon name="check" size={14} /> Đã phân bổ lô</span>
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
              {order.trang_thai === 'cho_duyet' && hasRole('sales', 'admin') && (
                <div className="order-demo-actions">
                  <button
                    type="button"
                    className="btn btn--sm btn--primary"
                    onClick={handleDemoConfirm}
                    disabled={confirmingFefo}
                  >
                    <Icon name="batch" size={15} />
                    {confirmingFefo ? 'Đang phân bổ…' : 'Duyệt đơn & Gán lô FEFO'}
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
              {latestPayment?.cong_thanh_toan === 'vietqr' && order.vietqr && (
                <div className="vietqr-box">
                  <img
                    src={order.vietqr.qr_url}
                    alt={`Mã VietQR ${order.ma_don}`}
                    className="vietqr-box__img"
                  />
                  <div className="vietqr-box__meta">
                    <p><strong>Ngân hàng:</strong> {order.vietqr.bank}</p>
                    <p><strong>Số TK:</strong> <span className="data">{order.vietqr.account_no}</span></p>
                    <p><strong>Chủ TK:</strong> {order.vietqr.account_name}</p>
                    <p><strong>Số tiền:</strong> <span className="data">{formatMoney(order.vietqr.amount)}</span></p>
                    <p><strong>Nội dung CK:</strong> <code className="data">{order.vietqr.memo}</code></p>
                  </div>
                  <p className="vietqr-box__status">
                    Hệ thống tự động kiểm tra giao dịch mỗi 3 giây sau khi bạn chuyển khoản.
                  </p>
                </div>
              )}

              {latestPayment?.cong_thanh_toan === 'vnpay' ? (
                <button type="button" className="btn btn--primary btn--block" onClick={handlePayVNPay} disabled={payingVNPay}>
                  {payingVNPay ? 'Đang chuyển VNPay…' : 'Thanh toán qua VNPay →'}
                </button>
              ) : latestPayment?.cong_thanh_toan === 'vietqr' ? (
                <button
                  type="button"
                  className="btn btn--primary btn--block"
                  onClick={handleCheckVietQrPayment}
                  disabled={checkingPayment}
                >
                  <Icon name="clock" size={15} />
                  {checkingPayment ? 'Đang kiểm tra giao dịch…' : 'Tôi đã chuyển khoản · Kiểm tra giao dịch'}
                </button>
              ) : (
                <p className="order-block__note" style={{ marginBottom: 'var(--s3)' }}>
                  {latestPayment?.cong_thanh_toan === 'momo'
                    ? 'Đơn hàng đang chờ xác nhận thanh toán từ ví MoMo.'
                    : 'Thanh toán tiền mặt khi nhận hàng (COD). Nhân viên sẽ xác nhận sau khi giao thành công.'}
                </p>
              )}

              {hasRole('sales', 'admin') && latestPayment?.cong_thanh_toan !== 'vnpay' && (
                <button
                  type="button"
                  className="btn btn--outline btn--block"
                  style={{ marginTop: 'var(--s2)' }}
                  onClick={handleConfirmPaid}
                >
                  <Icon name="check" size={15} />
                  Xác nhận đã nhận tiền (Sales/Admin)
                </button>
              )}
              {payError && <p className="checkout__error" style={{ marginTop: 'var(--s2)' }}>{payError}</p>}
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
