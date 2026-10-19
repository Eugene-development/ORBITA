import { mkdirSync } from 'node:fs';
import { createRequire } from 'node:module';
import assert from 'node:assert/strict';
const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PLAYWRIGHT_MODULE ?? 'playwright');
const base = process.env.ORBITA_BASE_URL ?? 'http://127.0.0.1:5174';
if (!['localhost', '127.0.0.1'].includes(new URL(base).hostname)) throw new Error('Local demo server required.');
const output = new URL('../.artifacts/final/', import.meta.url); mkdirSync(output, { recursive: true });
const browser = await chromium.launch({ headless: true, channel: 'chrome' });
/** @type {{width:number,label:string,message:string}[]} */
const errors = [];
/** @param {number} width @param {string} label @param {string} route @param {{productId:string,quantity:string}[]} cartItems */
async function capture(width, label, route, cartItems = []) {
  const context = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
  await context.addInitScript(/** @param {{productId:string,quantity:string}[]} items */ (items) => { localStorage.setItem('orbita:cart:v1', JSON.stringify(items)); localStorage.removeItem('inCart'); }, cartItems);
  const page = await context.newPage();
  page.on('pageerror', /** @param {Error} error */ error => errors.push({ width, label, message: error.message }));
  await page.route('**/api/orders**', /** @param {{abort():Promise<void>}} request */ request => request.abort());
  await page.route('**/api/subscriptions', /** @param {{abort():Promise<void>}} request */ request => request.abort());
  const response = await page.goto(`${base}${route}`, { waitUntil: 'domcontentloaded' });
  assert.equal(response.status(), 200, label);
  await page.locator('.demo-banner').waitFor();
  await page.locator('.site-footer').waitFor({state:'attached'});
  await page.getByRole('button', {name:'Поиск товара'}).click();
  await page.locator('dialog').waitFor({state:'visible'});
  await page.getByRole('button', {name:'Закрыть поиск'}).click();
  await page.locator('dialog').waitFor({state:'hidden'});
  if (label === 'cart-populated') await page.locator('.quantity-field').first().waitFor();
  if (label === 'cart-empty') await page.getByText('Корзина пуста. Ждём вас снова за покупками.').waitFor();
  /** @type {{src:string,loaded:boolean}[]} */
  const images = await page.evaluate(async () => {
    const images = [...document.images];
    images.forEach(image => { image.loading = 'eager'; });
    [...document.querySelectorAll('iframe')].forEach(frame => { frame.loading = 'eager'; });
    await Promise.race([Promise.all(images.map(image => image.complete ? Promise.resolve() : new Promise(resolve => { image.addEventListener('load', resolve, {once:true}); image.addEventListener('error', resolve, {once:true}); }))), new Promise(resolve => setTimeout(resolve, 20000))]);
    await Promise.all(images.filter(image => image.complete && image.naturalWidth > 0).map(image => image.decode().catch(() => {})));
    await document.fonts.ready;
    return images.map(image => ({ src: image.src, loaded: image.complete && image.naturalWidth > 0 }));
  });
  for (const frame of page.frames().slice(1)) await frame.waitForLoadState('load', { timeout: 10000 }).catch(() => {});
  await page.evaluate(() => window.scrollTo(0, 0));
  assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${label} ${width}: overflow`);
  await page.screenshot({ path: new URL(`${label}-${width}.png`, output).pathname, fullPage: true });
  await page.screenshot({ path: new URL(`${label}-${width}-viewport.png`, output).pathname });
  console.log(JSON.stringify({ label, width, images: images.length, missingImages: images.filter(image => !image.loaded).map(image => image.src) }));
  await context.close();
}
try {
  for (const width of [360,390,768,1024,1440]) { await capture(width, 'home', '/'); await capture(width, 'contact', '/contact'); }
  for (const width of [390,1440]) { await capture(width, 'cart-populated', '/shop/cart', [{ productId:'436', quantity:'3' }, { productId:'267', quantity:'2' }]); await capture(width,'cart-empty','/shop/cart'); await capture(width,'category','/shop/products/suxie-stroitelnye-smesi'); await capture(width,'sale','/shop/sale'); }
  assert.deepEqual(errors, []); console.log(JSON.stringify({ screenshots:36, viewports:5, runtimeErrors:errors, output:output.pathname }));
} finally { await browser.close(); }
