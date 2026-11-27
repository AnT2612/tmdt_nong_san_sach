import { useCallback, useEffect, useState } from 'react'
import { apiGet, apiPost, apiPut, readError } from '../../api/client.js'
import Icon from '../../components/Icon.jsx'
import { useAuth } from '../../context/AuthContext.jsx'
import { formatMoney, formatQuantity } from '../../utils/format.js'
import './StaffPages.css'

const ROLE_OPTIONS = [
  { value: 'customer', label: 'Khách hàng' },
  { value: 'farm_staff', label: 'Nhân viên Nông trại' },
  { value: 'sales', label: 'Bán hàng / CSKH' },
  { value: 'admin', label: 'Quản trị viên' },
]

export default function AdminDashboard() {
  const { user: currentAdmin } = useAuth()
  const [activeTab, setActiveTab] = useState('reports')

  const [overview, setOverview] = useState(null)
  const [inventory, setInventory] = useState(null)
  const [topProducts, setTopProducts] = useState([])
  const [revenueData, setRevenueData] = useState([])
  const [loadingReports, setLoadingReports] = useState(true)
  const [reportError, setReportError] = useState('')

  const [users, setUsers] = useState([])
  const [userRoleFilter, setUserRoleFilter] = useState('')
  const [userSearch, setUserSearch] = useState('')
  const [loadingUsers, setLoadingUsers] = useState(false)
  const [actionLoadingId, setActionLoadingId] = useState(null)
  const [userMessage, setUserMessage] = useState(null)

  useEffect(() => {
    async function loadReports() {
      try {
        const [overviewRes, inventoryRes, topRes, revenueRes] = await Promise.all([
          apiGet('/admin/reports/overview'),
          apiGet('/admin/reports/inventory'),
          apiGet('/admin/reports/top-products?limit=5'),
          apiGet('/admin/reports/revenue'),
        ])

        setOverview(overviewRes.data)
        setInventory(inventoryRes.data)
        setTopProducts(topRes.data)
        setRevenueData(revenueRes.data || [])
      } catch {
        setReportError('Không tải được dữ liệu báo cáo.')
      } finally {
        setLoadingReports(false)
      }
    }

    loadReports()
  }, [])

  const loadUsers = useCallback(async () => {
    setLoadingUsers(true)
    try {
      const params = new URLSearchParams()
      if (userRoleFilter) params.append('role', userRoleFilter)
      if (userSearch.trim()) params.append('search', userSearch.trim())

      const res = await apiGet(`/admin/users?${params.toString()}`)
      setUsers(res.data || [])
    } catch (err) {
      setUserMessage({ type: 'error', text: readError(err) })
    } finally {
      setLoadingUsers(false)
    }
  }, [userRoleFilter, userSearch])

  useEffect(() => {
    if (activeTab === 'users') loadUsers()
  }, [activeTab, loadUsers])

  async function handleRoleChange(userId, newRole) {
    setActionLoadingId(userId)
    setUserMessage(null)
    try {
      await apiPut(`/admin/users/${userId}/role`, { ten_vai_tro: newRole })
      setUserMessage({ type: 'ok', text: 'Cập nhật vai trò người dùng thành công.' })
      await loadUsers()
    } catch (err) {
      setUserMessage({ type: 'error', text: readError(err) })
    } finally {
      setActionLoadingId(null)
    }
  }

  async function handleToggleStatus(u) {
    if (u.id === currentAdmin?.id) {
      alert('Bạn không thể tự khóa tài khoản của chính mình.')
      return
    }

    setActionLoadingId(u.id)
    setUserMessage(null)
    try {
      const res = await apiPost(`/admin/users/${u.id}/toggle-status`)
      setUserMessage({ type: 'ok', text: res.message || 'Đã cập nhật trạng thái.' })
      await loadUsers()
    } catch (err) {
      setUserMessage({ type: 'error', text: readError(err) })
    } finally {
      setActionLoadingId(null)
    }
  }

  if (loadingReports && activeTab === 'reports') {
    return <div className="staff"><div className="skeleton" style={{ height: 240 }} /></div>
  }

  if (reportError && activeTab === 'reports') {
    return <div className="staff"><div className="empty"><p className="empty__title">{reportError}</p></div></div>
  }

  const orders = overview?.orders ?? {}
  const totalOrders = Object.values(orders).reduce((sum, value) => sum + Number(value), 0)

  return (
    <div className="staff">
      <header className="staff__head">
        <div>
          <h1>Bảng Quản Trị Hệ Thống</h1>
          <p className="staff__sub">Báo cáo thống kê · Tỷ lệ hao hụt · Phân quyền tài khoản</p>
        </div>

        <nav className="tabs" style={{ marginBottom: 0 }}>
          <button
            type="button"
            className={`tabs__tab ${activeTab === 'reports' ? 'is-active' : ''}`}
            onClick={() => setActiveTab('reports')}
          >
            <Icon name="chart" size={15} /> Báo cáo &amp; Doanh thu
          </button>
          <button
            type="button"
            className={`tabs__tab ${activeTab === 'users' ? 'is-active' : ''}`}
            onClick={() => setActiveTab('users')}
          >
            <Icon name="user" size={15} /> Quản lý tài khoản
          </button>
        </nav>
      </header>

      {activeTab === 'reports' && overview && (
        <>
          <section className="ledger-strip">
            <div className="ledger-strip__cell">
              <span className="data-label">Doanh thu hoàn thành</span>
              <span className="data ledger-strip__value">{formatMoney(overview.revenue)}</span>
            </div>
            <div className="ledger-strip__cell">
              <span className="data-label">Tổng đơn trong kỳ</span>
              <span className="data ledger-strip__value">{totalOrders}</span>
              <span className="ledger-strip__note">{orders.cho_duyet ?? 0} đơn chờ duyệt</span>
            </div>
            <div className="ledger-strip__cell">
              <span className="data-label">Khách hàng</span>
              <span className="data ledger-strip__value">{overview.customers}</span>
            </div>
            <div className={`ledger-strip__cell ${overview.loss.loss_rate > 5 ? 'ledger-strip__cell--alert' : ''}`}>
              <span className="data-label">Tỷ lệ hao hụt</span>
              <span className="data ledger-strip__value">{overview.loss.loss_rate}%</span>
              <span className="ledger-strip__note">
                {formatQuantity(overview.loss.disposed)} / {formatQuantity(overview.loss.imported)} nhập kho
              </span>
            </div>
          </section>

          <div className="admin-grid" style={{ marginTop: '1.5rem' }}>
            <section className="admin-block">
              <h2 className="admin-block__title"><Icon name="chart" size={16} /> Sản phẩm bán chạy</h2>
              {topProducts.length === 0 ? (
                <p className="staff-table__muted">Chưa có dữ liệu bán hàng.</p>
              ) : (
                <table className="ledger">
                  <thead>
                    <tr><th>Sản phẩm</th><th className="num">Đã bán</th><th className="num">Doanh thu</th></tr>
                  </thead>
                  <tbody>
                    {topProducts.map((product) => (
                      <tr key={product.product_id}>
                        <td>{product.ten_san_pham}</td>
                        <td className="num data">{formatQuantity(product.total_quantity)}</td>
                        <td className="num data">{formatMoney(product.total_revenue)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </section>

            <section className="admin-block">
              <h2 className="admin-block__title"><Icon name="alert" size={16} /> Lô cận hạn (≤ 2 ngày)</h2>
              {inventory?.expiring_soon?.length === 0 ? (
                <p className="staff-table__muted">Không có lô cận hạn.</p>
              ) : (
                <ul className="expiry-list">
                  {inventory?.expiring_soon?.map((batch) => (
                    <li key={batch.ma_lo} className="expiry-item">
                      <div>
                        <span className="data expiry-item__code">{batch.ma_lo}</span>
                        <span className="expiry-item__name">{batch.ten_san_pham}</span>
                      </div>
                      <div className="expiry-item__right">
                        <span className="data">{formatQuantity(batch.so_luong_ton)}</span>
                        <span className="tag tag--danger">{batch.days_until_expiry} ngày</span>
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </section>
          </div>
        </>
      )}

      {activeTab === 'users' && (
        <section className="admin-block" style={{ marginTop: '1rem' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap', gap: '1rem', marginBottom: '1rem' }}>
            <h2 className="admin-block__title" style={{ margin: 0 }}>Danh sách tài khoản ({users.length})</h2>
            <div style={{ display: 'flex', gap: '0.75rem' }}>
              <input
                type="text"
                className="input input--sm"
                placeholder="Tìm tên, email, sđt..."
                value={userSearch}
                onChange={(e) => setUserSearch(e.target.value)}
              />
              <select
                className="select select--sm"
                value={userRoleFilter}
                onChange={(e) => setUserRoleFilter(e.target.value)}
              >
                <option value="">-- Tất cả vai trò --</option>
                {ROLE_OPTIONS.map((opt) => (
                  <option key={opt.value} value={opt.value}>{opt.label}</option>
                ))}
              </select>
            </div>
          </div>

          {userMessage && (
            <p className={`staff__message staff__message--${userMessage.type}`}>{userMessage.text}</p>
          )}

          {loadingUsers ? (
            <div className="skeleton" style={{ height: 220 }} />
          ) : (
            <div className="table-wrap">
              <table className="ledger">
                <thead>
                  <tr>
                    <th>Họ và tên</th>
                    <th>Email / SĐT</th>
                    <th>Vai trò</th>
                    <th>Trạng thái</th>
                    <th style={{ textAlign: 'right' }}>Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  {users.map((u) => {
                    const isSelf = u.id === currentAdmin?.id
                    const isBusy = actionLoadingId === u.id
                    const roleVal = u.ten_vai_tro || u.role?.ten_vai_tro || 'customer'

                    return (
                      <tr key={u.id}>
                        <td><strong>{u.ho_ten}</strong></td>
                        <td>
                          <div>{u.email}</div>
                          {u.so_dien_thoai && <div className="data" style={{ fontSize: '0.75rem' }}>{u.so_dien_thoai}</div>}
                        </td>
                        <td>
                          <select
                            className="select select--sm"
                            value={roleVal}
                            disabled={isSelf || isBusy}
                            onChange={(e) => handleRoleChange(u.id, e.target.value)}
                          >
                            {ROLE_OPTIONS.map((opt) => (
                              <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                          </select>
                        </td>
                        <td>
                          <span className={`tag tag--${u.trang_thai ? 'good' : 'danger'}`}>
                            {u.trang_thai ? 'Hoạt động' : 'Đã khoá'}
                          </span>
                        </td>
                        <td style={{ textAlign: 'right' }}>
                          <button
                            type="button"
                            className="btn btn--sm btn--outline"
                            onClick={() => handleToggleStatus(u)}
                            disabled={isSelf || isBusy}
                          >
                            {u.trang_thai ? 'Khoá' : 'Mở khoá'}
                          </button>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
        </section>
      )}
    </div>
  )
}
