/** Prices are transported as decimal strings; binary floating point is never used. */
export function parseDecimal(value, scale = 2) {
  const normalized = String(value).trim().replace(',', '.');
  if (!/^\d+(?:\.\d+)?$/.test(normalized)) throw new TypeError('Некорректное число');
  const [whole, fraction = ''] = normalized.split('.');
  if (fraction.length > scale) throw new RangeError('Слишком много знаков после запятой');
  return BigInt(whole) * (10n ** BigInt(scale)) + BigInt(fraction.padEnd(scale, '0') || '0');
}
export function formatDecimal(units, scale = 2) {
  const n = BigInt(units); const factor = 10n ** BigInt(scale);
  return `${n / factor}.${String(n % factor).padStart(scale, '0')}`;
}
export function lineTotal(price, quantity) {
  const amount = parseDecimal(price, 2) * parseDecimal(quantity, 3);
  return formatDecimal((amount + 500n) / 1000n);
}
export function money(value) {
  const fixed = formatDecimal(parseDecimal(value));
  const [whole, fraction] = fixed.split('.');
  return `${whole.replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0')},${fraction}\u00a0₽`;
}
