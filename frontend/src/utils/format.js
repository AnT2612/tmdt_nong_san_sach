export function formatMoney(value) {
  const number = Number(value) || 0
  return `${number.toLocaleString('vi-VN', { maximumFractionDigits: 0 })}đ`
}

export function formatQuantity(value) {
  const number = Number(value) || 0
  return number.toLocaleString('vi-VN', { maximumFractionDigits: 2 })
}

export function formatQuantityWithUnit(value, unit) {
  return `${formatQuantity(value)} ${unit || ''}`.trim()
}

export function formatDate(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)

  return date.toLocaleDateString('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

export function formatDateTime(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)

  return date.toLocaleString('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export function daysUntil(dateValue) {
  if (!dateValue) return null
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const target = new Date(dateValue)
  target.setHours(0, 0, 0, 0)
  return Math.round((target - today) / 86400000)
}

export function expiryLevel(daysOrDate) {
  const days = typeof daysOrDate === 'number' ? daysOrDate : daysUntil(daysOrDate)
  if (days === null || days === undefined) return 'good'
  if (days <= 2) return 'danger'
  if (days <= 5) return 'warn'
  return 'good'
}

export function formatExpiry(days) {
  if (days === null || days === undefined) return 'Không rõ hạn'
  if (days < 0) return `Quá hạn ${Math.abs(days)} ngày`
  if (days === 0) return 'Hết hạn hôm nay'
  if (days === 1) return 'Còn 1 ngày'
  return `Còn ${days} ngày`
}

export const ORDER_STATUS_LABEL = {
  cho_duyet: 'Chờ duyệt',
  da_duyet: 'Đã duyệt',
  dang_giao: 'Đang giao',
  hoan_thanh: 'Hoàn thành',
  da_huy: 'Đã huỷ',
}

export const PAYMENT_STATUS_LABEL = {
  cho: 'Chưa thanh toán',
  thanh_cong: 'Đã thanh toán',
  that_bai: 'Thanh toán lỗi',
  hoan_tien: 'Đã hoàn tiền',
}

export const COMPLAINT_STATUS_LABEL = {
  moi: 'Mới gửi',
  dang_xu_ly: 'Đang xử lý',
  da_xu_ly: 'Đã xử lý',
}

export const COMPLAINT_OUTCOME_LABEL = {
  hoan_tien: 'Duyệt hoàn tiền',
  giao_bu: 'Duyệt giao bù',
  tu_choi: 'Từ chối',
}

export const BATCH_STATUS_LABEL = {
  con_hang: 'Còn hàng',
  can_han: 'Cận hạn',
  het_hang: 'Hết hàng',
  het_han: 'Hết hạn',
}

export const COMPLAINT_REASON_LIST = [
  'Hàng bị dập nát',
  'Hàng bị hỏng, thối',
  'Thiếu hàng',
  'Giao sai hàng',
  'Lý do khác',
]

export const DISPOSE_REASON_LIST = [
  'Dập nát khi vận chuyển',
  'Héo úa do lưu kho',
  'Quá hạn sử dụng',
  'Lý do khác',
]
