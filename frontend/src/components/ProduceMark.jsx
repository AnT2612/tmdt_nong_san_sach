// Băm chuỗi thành số ổn định - cùng SKU luôn ra cùng nhãn.
function hashString(value) {
  let hash = 0

  for (let index = 0; index < value.length; index += 1) {
    hash = (hash * 31 + value.charCodeAt(index)) % 100000
  }

  return hash
}

// Chọn một góc nghiêng trong 3 mức để các nhãn không giống hệt nhau.
const TILTS = [-12, 0, 10]

export default function ProduceMark({ name = '', sku = '', size = 'md' }) {
  const seed = hashString(sku || name)
  const tilt = TILTS[seed % TILTS.length]

  // Chữ cái đầu của tên sản phẩm, bỏ qua từ chỉ loại như "hữu cơ", "baby".
  const initial = name.trim().charAt(0).toUpperCase() || '?'

  return (
    <div className={`produce-mark produce-mark--${size}`} aria-hidden="true">
      {/* Các đường luống cày, nghiêng theo seed */}
      <svg
        className="produce-mark__rows"
        viewBox="0 0 100 100"
        preserveAspectRatio="none"
        style={{ transform: `rotate(${tilt}deg) scale(1.6)` }}
      >
        {[10, 26, 42, 58, 74, 90].map((x) => (
          <line
            key={x}
            x1={x}
            y1="-20"
            x2={x}
            y2="120"
            stroke="currentColor"
            strokeWidth="0.6"
            opacity="0.28"
          />
        ))}
      </svg>

      {/* Chữ cái đầu của sản phẩm */}
      <span className="produce-mark__letter data">{initial}</span>

      {/* Mã SKU in nhỏ ở góc, đọc như ký hiệu trên nhãn thùng */}
      {sku && <span className="produce-mark__sku data">{sku}</span>}
    </div>
  )
}
