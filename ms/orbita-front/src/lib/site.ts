export const logoUrl = 'https://lumen-image-bucket.s3.eu-central-1.amazonaws.com/logo/logo.png';
export const navigationCatalog = [
  { id: '1', value: 'Материалы', slug: 'stroymaterialy' }, { id: '2', value: 'Изоляция', slug: 'izolyatsiya' },
  { id: '3', value: 'Отделка', slug: 'otdelka' }, { id: '4', value: 'Инструмент', slug: 'instrument' },
  { id: '5', value: 'Сантехника', slug: 'santehnika' }, { id: '6', value: 'Электротовары', slug: 'elektrotovary' }
];
export const navigation = [ { href: '/', label: 'Главная' }, { href: '/about', label: 'Компания' }, { href: '/blog', label: 'Блог' }, { href: '/contact', label: 'Контакты' } ];
export const information = [ { href: '/information/payment', label: 'Оплата' }, { href: '/information/delivery', label: 'Доставка' }, { href: '/information/movers', label: 'Грузчики' } ];
export const promotions = [ { href: '/information/action', label: 'Акции Церезит' }, { href: '/shop/discount', label: 'Наши скидки' }, { href: '/shop/sale', label: 'Распродажа' } ];
export function imageUrl(hash?: string | null, base = 'https://lumen-image-bucket.s3.eu-central-1.amazonaws.com/images/') {
  if (!hash) return '/images/product-placeholder.svg';
  let filename = hash;
  try { filename = decodeURIComponent(hash); } catch {}
  return `${base.replace(/\/$/, '')}/${encodeURIComponent(filename)}`;
}
export function imageFallback(event: Event) { const image = event.currentTarget as HTMLImageElement; image.onerror = null; image.src = '/images/product-placeholder.svg'; }
