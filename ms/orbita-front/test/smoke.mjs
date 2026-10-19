// Run against an explicitly configured ORBITA_DEMO=true local server. Never submit real orders.
import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PLAYWRIGHT_MODULE ?? 'playwright');
const base = process.env.ORBITA_BASE_URL ?? 'http://127.0.0.1:4151';
const captureScreenshots = process.env.ORBITA_SCREENSHOTS !== 'false';
const productPath = '/shop/product/stukaturnaya-smes-knauf-rotband-belaya-30-kg';
if (!['localhost','127.0.0.1'].includes(new URL(base).hostname)) throw new Error('Smoke tests require a local demo server.');
const output = new URL('../.artifacts/', import.meta.url); mkdirSync(output, { recursive: true });
const browser = await chromium.launch({ headless: true, channel: process.env.PLAYWRIGHT_CHANNEL ?? 'chrome' });
try {
  const routes = ['/', '/about', '/contact', '/blog', ...Array.from({length:18}, (_,i) => `/blog/post/${i+1}`), ...['payment','delivery','movers','documents','guarantees','price','certificates','partner','testimonials','action'].map((slug) => `/information/${slug}`), '/shop/rubric/stroymaterialy', '/shop/category/stroitelnye-smesi', '/shop/products/suxie-stroitelnye-smesi', productPath, '/shop/discount', '/shop/sale', '/shop/cart', '/spasibo'];
  /** @type {string[]} */
  const errors = [];
  const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });
  page.on('pageerror', /** @param {Error} error */ error => errors.push(error.message));
  await page.route('**/api/orders**', /** @param {{abort():Promise<void>}} route */ route => route.abort());
  await page.route('**/api/subscriptions', /** @param {{abort():Promise<void>}} route */ route => route.abort());
  for (const path of routes) {
    const response = await page.goto(`${base}${path}`, { waitUntil: 'domcontentloaded' });
    assert.equal(response?.status(), 200, path);
    await page.locator('#main-content').waitFor();
    assert.ok(await page.locator('h1').count() > 0, `${path}: missing h1`);
  }
  const unknown = await page.goto(`${base}/shop/product/does-not-exist`, { waitUntil: 'domcontentloaded' });
  assert.equal(unknown?.status(), 404);
  const health = await page.request.get(`${base}/health`);
  assert.equal(health.status(), 200); assert.deepEqual(await health.json(), { status: 'ok' });
  await page.goto(base, { waitUntil: 'domcontentloaded' });
  assert.ok(await page.locator('.demo-banner').isVisible(), 'Demo flag required before cart actions');
  await page.locator('.product-add:not([disabled])').first().click();
  await page.goto(`${base}/shop/cart`, { waitUntil: 'domcontentloaded' });
  await page.locator('.quantity-field').first().waitFor();
  await page.locator('.quantity-field').first().fill('3');
  await page.reload({ waitUntil: 'domcontentloaded' });
  await assert.doesNotReject(() => page.locator('.quantity-field').first().waitFor());
  assert.equal(await page.locator('.quantity-field').first().inputValue(), '3');
  await page.getByRole('button', { name: 'Поиск товара' }).click();
  await page.getByLabel('Наименование товара').fill('цемент');
  await page.locator('#search-results li').first().waitFor();
  await page.keyboard.press('Escape');
  await page.locator('dialog').waitFor({ state: 'hidden' });
  assert.equal(await page.locator('dialog').isVisible(), false);
  for (const width of [360,390,768,1024,1440]) {
    await page.setViewportSize({ width, height: 960 });
    for (const [label,path] of [['home','/'],['contact','/contact'],['sale','/shop/sale'],['cart','/shop/cart'],['category','/shop/products/suxie-stroitelnye-smesi'],['product',productPath]]) {
      await page.goto(`${base}${path}`, {waitUntil:'domcontentloaded'});
      await page.locator('#main-content').waitFor();
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), `${width} ${path}: horizontal overflow`);
      if (captureScreenshots && [390,1440].includes(width)) {
        await page.evaluate(async () => {
          const images = [...document.images];
          images.forEach((image) => { image.loading = 'eager'; });
          await Promise.race([Promise.all(images.map((image) => image.complete ? Promise.resolve() : new Promise((resolve) => { image.addEventListener('load', resolve, { once: true }); image.addEventListener('error', resolve, { once: true }); }))), new Promise((resolve) => setTimeout(resolve, 5000))]);
        });
        await page.screenshot({ path: new URL(`public-${label}-${width}.png`,output).pathname, fullPage:true });
      }
    }
  }
  assert.deepEqual(errors, [], 'Browser runtime errors');
  console.log(JSON.stringify({ checkedRoutes: routes.length + 2, viewports: 5, cartPersistence: true, keyboardSearch: true, screenshots: captureScreenshots ? 12 : 0, runtimeErrors: errors }));
} finally { await browser.close(); }
