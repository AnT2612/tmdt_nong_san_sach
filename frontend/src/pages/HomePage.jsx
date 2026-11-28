import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { apiGet } from '../api/client.js'
import Icon from '../components/Icon.jsx'
import ProduceMark from '../components/ProduceMark.jsx'
import { expiryLevel, formatExpiry, formatMoney, formatQuantity } from '../utils/format.js'
import '../styles/HomePage.css'

export default function HomePage() {
  const [products, setProducts] = useState([])
  const [batches, setBatches] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    let cancelled = false

    async function load() {
      try {
        const [productResponse, batchResponse] = await Promise.all([
          apiGet('/products?per_page=6'),
          apiGet('/available-batches?limit=6'),
        ])

        if (cancelled) return

        setProducts(productResponse.data)
        setBatches(batchResponse.data)
      } catch {
        if (!cancelled) {
          setError('Không tải được dữ liệu. Kiểm tra Backend đã chạy chưa.')
        }
      } finally {
        if (!cancelled) setLoading(false)
      }
    }

    load()
    return () => { cancelled = true }
  }, [])

  return (
    <>
      <section className="hero-banner">
        <div className="wrap">
          <Link to="/san-pham" className="hero-banner__link" aria-label="Xem sản phẩm Nông Sản Sạch Củ Chi">
            <img
              src="/images/banner.jpg"
              alt="Nông Sản Sạch Củ Chi - Rau củ tươi sạch, đặc sản Củ Chi, quà tặng"
              className="hero-banner__img"
            />
          </Link>
        </div>
      </section>

      <section className="board">
        <div className="wrap board__grid">
          <div className="board__ledger">
            <div className="board__head">
              <h1 className="board__title">Hôm nay xuất vườn</h1>
              <p className="board__meta data">
                {new Date().toLocaleDateString('vi-VN', {
                  weekday: 'long',
                  day: '2-digit',
                  month: '2-digit',
                  year: 'numeric',
                })}
                <span className="board__meta-sep" aria-hidden="true">/</span>
                Củ Chi, TP.HCM
              </p>
            </div>

            {loading ? (
              <div className="board__loading">
                {[0, 1, 2, 3].map((row) => (
                  <div key={row} className="skeleton board__skeleton" />
                ))}
              </div>
            ) : error ? (
              <div className="empty">
                <p className="empty__title">Chưa kết nối được nông trại</p>
                <p>{error}</p>
              </div>
            ) : (
              <table className="ledger board__table">
                <caption className="visually-hidden">
                  Các lô nông sản đang bán, xếp theo hạn sử dụng gần nhất
                </caption>
                <thead>
                  <tr>
                    <th scope="col">Mã lô</th>
                    <th scope="col">Nông sản</th>
                    <th scope="col" className="num">Còn</th>
                  </tr>
                </thead>
                <tbody>
                  {batches.map((batch) => {
                    const level = expiryLevel(batch.days_until_expiry ?? 99)

                    return (
                      <tr key={batch.ma_lo}>
                        <td className="data board__code">{batch.ma_lo}</td>
                        <td className="board__product">
                          <Link to={`/san-pham/${batch.product_slug}`}>
                            {batch.ten_san_pham}
                          </Link>
                          <span className="board__sub">
                            <span className="board__cert">Hái {batch.ngay_thu_hoach}</span>
                            <span className={`tag tag--${level}`}>
                              {formatExpiry(batch.days_until_expiry)}
                            </span>
                          </span>
                        </td>
                        <td className="num data">
                          {formatQuantity(batch.so_luong_ton)}
                          <span className="board__unit">{batch.don_vi_tinh}</span>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            )}

            <p className="board__foot">
              Bảng cập nhật trực tiếp từ kho. Mỗi lô là một lần thu hoạch riêng,
              có ngày và hạn dùng riêng.
            </p>
          </div>

          <aside className="board__aside">
            <p className="board__claim">
              Chúng tôi bán theo <strong>lô</strong>, không bán theo “còn hàng”.
            </p>

            <p className="board__body">
              Mỗi lần thu hoạch là một lô riêng, ghi rõ ngày hái và hạn dùng.
              Khi bạn đặt hàng, kho tự lấy lô <strong>gần hết hạn trước</strong> —
              nguyên tắc FEFO — nên rau đến tay bạn luôn tươi nhất và giảm hao hụt nội bộ.
            </p>

            <ol className="board__steps">
              <li><span className="board__step-num data">01</span><span>Lô hái sáng nay được cân và dán mã QR</span></li>
              <li><span className="board__step-num data">02</span><span>Đơn của bạn tự trừ đúng lô cũ nhất</span></li>
              <li><span className="board__step-num data">03</span><span>Quét mã trên túi để xem cả mùa vụ &amp; kiểm định</span></li>
            </ol>

            <Link to="/san-pham" className="btn btn--primary btn--block">
              Xem nông sản hôm nay
              <Icon name="arrow" size={17} />
            </Link>

            <Link to="/truy-xuat" className="board__trace">
              <Icon name="qr" size={16} />
              Tra cứu mã lô trên túi hàng
            </Link>
          </aside>
        </div>
      </section>

      <section className="section">
        <div className="wrap">
          <div className="section__head">
            <h2>Đang có trong vườn</h2>
            <Link to="/san-pham" className="section__more">
              Tất cả sản phẩm
              <Icon name="arrow" size={15} />
            </Link>
          </div>

          {loading ? (
            <div className="product-grid">
              {[0, 1, 2].map((index) => (
                <div key={index} className="product-card">
                  <div className="skeleton product-card__image" />
                  <div className="product-card__body">
                    <div className="skeleton" style={{ height: 20, width: '60%' }} />
                    <div className="skeleton" style={{ height: 16, width: '40%', marginTop: 12 }} />
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="product-grid">
              {products.slice(0, 6).map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>
          )}
        </div>
      </section>

      <section className="section section--dark">
        <div className="wrap">
          <div className="section__head">
            <h2>Từ luống đất đến bếp</h2>
          </div>

          <div className="process">
            <article className="process__item">
              <span className="process__num data">01</span>
              <h3>Ghi nhật ký canh tác</h3>
              <p>Nhân viên nông trại ghi từng lần gieo, tưới, bón phân kèm ảnh và chứng nhận VietGAP.</p>
            </article>
            <article className="process__item">
              <span className="process__num data">02</span>
              <h3>Thu hoạch, tạo lô &amp; QR</h3>
              <p>Mỗi lần hái là một lô riêng với hạn sử dụng rõ ràng và mã QR truy xuất nguồn gốc.</p>
            </article>
            <article className="process__item">
              <span className="process__num data">03</span>
              <h3>Giao trong ngày theo FEFO</h3>
              <p>Đơn hàng được xuất theo lô gần hết hạn trước, giao hỏa tốc nội thành trực tiếp từ vườn.</p>
            </article>
          </div>
        </div>
      </section>
    </>
  )
}

function ProductCard({ product }) {
  const outOfStock = !product.in_stock
  const isFlashSale = product.gia_hien_tai < product.gia_ban_co_ban

  return (
    <Link to={`/san-pham/${product.slug}`} className="product-card">
      <div className="product-card__image">
        {product.hinh_anh_dai_dien ? (
          <img src={product.hinh_anh_dai_dien} alt={product.ten_san_pham} loading="lazy" />
        ) : (
          <ProduceMark name={product.ten_san_pham} sku={product.sku} />
        )}
        {outOfStock && <span className="product-card__soldout">Tạm hết</span>}
      </div>

      <div className="product-card__body">
        <h2 className="product-card__name">{product.ten_san_pham}</h2>
        <div className="product-card__price-row">
          <span className="product-card__price data">{formatMoney(product.gia_hien_tai)}</span>
          <span className="product-card__unit">/ {product.don_vi_tinh}</span>
          {isFlashSale && (
            <span className="product-card__was data">{formatMoney(product.gia_ban_co_ban)}</span>
          )}
        </div>
      </div>

      <div className="product-card__strip">
        <span className="data">
          Còn <strong>{formatQuantity(product.available_quantity)}</strong> {product.don_vi_tinh}
        </span>
        {product.chung_nhan && <span className="product-card__cert">{product.chung_nhan}</span>}
      </div>
    </Link>
  )
}
