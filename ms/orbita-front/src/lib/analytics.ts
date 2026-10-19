interface Metrika { (...args: unknown[]): void; a?: unknown[][]; l?: number }
export function initializeAnalytics() {
  const host = window as Window & { ym?: Metrika };
  if (host.ym) return;
  const metrika: Metrika = (...args) => { (metrika.a ??= []).push(args); };
  metrika.l = Date.now(); host.ym = metrika;
  const tag = document.createElement('script'); tag.async = true; tag.src = 'https://mc.yandex.ru/metrika/tag.js'; document.head.append(tag);
  metrika(82181533, 'init', { clickmap: true, trackLinks: true, accurateTrackBounce: true, webvisor: true });
  const chat = document.createElement('script'); chat.async = true; chat.src = 'https://code.jivo.ru/widget/2EfbaT2QPD'; document.head.append(chat);
}
