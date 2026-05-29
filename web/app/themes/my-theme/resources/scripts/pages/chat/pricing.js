export function quotationPricingLine(quoteDetail, productId) {
  const lines = quoteDetail?.pricing?.lines || [];

  return lines.find((line) => Number(line.product_id) === Number(productId)) || null;
}

export function quoteLineMath(quantity, unitPrice, discountPercent) {
  const rawQty = Number(quantity || 1);
  const rawPrice = Number(unitPrice || 0);
  const rawDiscount = Number(discountPercent || 0);
  const qty = Math.max(1, Number.isFinite(rawQty) ? rawQty : 1);
  const price = Math.max(0, Number.isFinite(rawPrice) ? rawPrice : 0);
  const discount = Math.min(100, Math.max(0, Number.isFinite(rawDiscount) ? rawDiscount : 0));
  const subtotal = qty * price;
  const discountAmount = subtotal * (discount / 100);
  const total = Math.max(0, subtotal - discountAmount);

  return {
    quantity: qty,
    unitPrice: price,
    discountPercent: discount,
    subtotal,
    discountAmount,
    total,
  };
}

