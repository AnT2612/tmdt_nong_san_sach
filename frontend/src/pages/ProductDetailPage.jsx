import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { apiGet } from '../api/client.js'
import Icon from '../components/Icon.jsx'
import ProduceMark from '../components/ProduceMark.jsx'
import { useCart } from '../context/CartContext.jsx'
import { expiryLevel, formatExpiry, formatMoney, formatQuantity } from '../utils/format.js'
import './ProductDetailPage.css'

export default function ProductDetailPage() {
  const { slug } = useParams()
  const navigate = useNavigate()
  const { addItem } = useCart()

  const [product, setProduct] = useState(null)
  const [batches, setBatches] = useState([])
  const [quantity, setQuantity] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [added, setAdded] = useState(false)

  useEffect(() => {
    let cancelled = false

    async function load() {
      setLoading(true)
      setError('')

      try {
        const response = await apiGet(`/products/${slug}`)
        if (cancelled) return

        setProduct(response.data)
        setQuantity(1)

        const batchResponse = await apiGet('/available-batches?limit=40').catch(() => null)
        if (!cancelled && batchResponse?.data) {
          setBatches(
            batchResponse.data.filter((batch) => batch.product_slug === response.data.slug),
          )
        }
      } catch {
        if (!cancelled) setError('Không tìm thấy sản phẩm này.')
      } finally {
        if (!cancelled) setLoading(false)
      }
    }

    load()
    return () => { cancelled = true }
  }, [slug])

  if (loading) {
    return (
      <div className="wrap product-detail">
        <div className="product-detail__grid">
          <div className="skeleton product-detail__media" />
          <div>
            <div className="skeleton" style={{ height: 36, width: '70%' }} />
            <div className="skeleton" style={{ height: 24, width: '35%', marginTop: 16 }} />
            <div className="skeleton" style={{ height: 90, width: '100%', marginTop: 24 }} />
          </div>
        </div>
      </div>
    )
  }

  if (error || !product) {
    return (
      <div className="wrap product-detail">
        <div className="empty">
          <p className="empty__title">{error || 'Không tìm thấy sản phẩm'}</p>
          <p><Link to="/san-pham">← Quay lại danh sách sản phẩm</Link></p>
        </div>
      </div>
    )
  }

  const maxQuantity = product.available_quantity
  const outOfStock = !product.in_stock
  const isFlashSale = product.gia_hien_tai < product.gia_ban_co_ban

  function handleAddToCart() {
    addItem(product, quantity)
    setAdded(true)
    window.setTimeout(() => setAdded(false), 2200)
  }

  function changeQuantity(delta) {
    setQuantity((current) => {
      const next = Math.round((current + delta) * 100) / 100
      if (next < 0.5) return 0.5
      if (next > maxQuantity) return maxQuantity
      return next
    })
  }

  return (
    <div className="wrap product-detail">
      <nav className="product-detail__crumbs" aria-label="Đường dẫn">
        <Link to="/">Trang chủ</Link>
        <span aria-hidden="true">/</span>
        <Link to="/san-pham">Sản phẩm</Link>
        <span aria-hidden="true">/</span>
        <span aria-current="page">{product.ten_san_pham}</span>
      </nav>

      <div className="product-detail__grid">
        <div className="product-detail__media">
          {product.hinh_anh_dai_dien ? (
            <img src={product.hinh_anh_dai_dien} alt={product.ten_san_pham} />
          ) : (
            <ProduceMark name={product.ten_san_pham} sku={product.sku} size="lg" />
          )}
        </div>

        <div className="product-detail__info">
          <h1 className="product-detail__name">{product.ten_san_pham}</h1>

          {product.chung_nhan && (
            <p className="product-detail__origin">
              <Icon name="leaf" size={15} />
              Chứng nhận: {product.chung_nhan}
            </p>
          )}

          <div className="product-detail__price-row">
            <span className="product-detail__price data">{formatMoney(product.gia_hien_tai)}</span>
            <span className="product-detail__unit">/ {product.don_vi_tinh}</span>
            {isFlashSale && (
              <span className="product-detail__was data">{formatMoney(product.gia_ban_co_ban)}</span>
            )}
          </div>

          {product.mo_ta && <p className="product-detail__desc">{product.mo_ta}</p>}

          <div className="product-detail__buy">
            <div className="stepper">
              <button
                type="button"
                className="stepper__btn"
                onClick={() => changeQuantity(-0.5)}
                disabled={outOfStock || quantity <= 0.5}
                aria-label="Giảm số lượng"
              >
                <Icon name="minus" size={16} />
              </button>

              <input
                type="number"
                className="stepper__input data"
                value={quantity}
                min={0.5}
                max={maxQuantity}
                step={0.5}
                onChange={(event) => {
                  const value = Number(event.target.value)
                  if (value > 0 && value <= maxQuantity) setQuantity(value)
                }}
                aria-label={`Số lượng, đơn vị ${product.don_vi_tinh}`}
              />

              <button
                type="button"
                className="stepper__btn"
                onClick={() => changeQuantity(0.5)}
                disabled={outOfStock || quantity >= maxQuantity}
                aria-label="Tăng số lượng"
              >
                <Icon name="plus" size={16} />
              </button>

              <span className="stepper__unit">{product.don_vi_tinh}</span>
            </div>

            <button
              type="button"
              className="btn btn--primary product-detail__add"
              onClick={handleAddToCart}
              disabled={outOfStock}
            >
              {outOfStock ? 'Tạm hết hàng' : added ? 'Đã thêm vào giỏ' : 'Thêm vào giỏ'}
              {!outOfStock && !added && <Icon name="cart" size={17} />}
            </button>
          </div>

          {!outOfStock && (
            <p className="product-detail__stock data">
              Còn {formatQuantity(maxQuantity)} {product.don_vi_tinh} trong kho
            </p>
          )}

          {added && (
            <button
              type="button"
              className="product-detail__goto-cart"
              onClick={() => navigate('/gio-hang')}
            >
              Xem giỏ hàng
              <Icon name="arrow" size={15} />
            </button>
          )}
        </div>
      </div>

      {batches.length > 0 && (
        <section className="batch-panel">
          <div className="batch-panel__head">
            <h2>Các lô đang bán (ưu tiên FEFO)</h2>
            <p className="batch-panel__note">
              Kho sẽ tự lấy lô gần hết hạn trước. Nếu đơn cần nhiều hơn 1 lô, hệ thống tự tách lô và lưu vết minh bạch.
            </p>
          </div>

          <div className="batch-panel__list">
            {batches.map((batch, index) => {
              const level = expiryLevel(batch.days_until_expiry ?? 99)

              return (
                <article key={batch.ma_lo} className="batch-row">
                  <div className="batch-row__rank data">
                    {String(index + 1).padStart(2, '0')}
                    {index === 0 && <span className="batch-row__next">xuất trước</span>}
                  </div>

                  <div className="batch-row__main">
                    <span className="batch-row__code data">{batch.ma_lo}</span>
                    <span className="batch-row__harvest data">
                      Thu hoạch {batch.ngay_thu_hoach} · HSD {batch.ngay_het_han}
                    </span>
                  </div>

                  <div className="batch-row__amount data">
                    {formatQuantity(batch.so_luong_ton)}
                    <span className="batch-row__unit">{batch.don_vi_tinh}</span>
                  </div>

                  <span className={`tag tag--${level}`}>
                    {formatExpiry(batch.days_until_expiry)}
                  </span>

                  <Link to={`/truy-xuat/${batch.ma_lo}`} className="btn btn--sm btn--outline">
                    Truy xuất QR
                  </Link>
                </article>
              )
            })}
          </div>
        </section>
      )}
    </div>
  )
}
