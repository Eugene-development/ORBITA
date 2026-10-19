import { test, expect } from '@playwright/test';

test('guest cannot enter admin; employee creates, edits, archives and restores product',async({page})=>{
  await page.goto('/admin/products'); await expect(page).toHaveURL(/\/admin\/login/);
  await page.getByLabel('Email',{exact:true}).fill('editor@example.test'); await page.getByLabel('Пароль',{exact:true}).fill('Orbita-test-2026!'); await page.getByRole('button',{name:'Войти',exact:true}).click();
  await expect(page).toHaveURL(/\/admin\/products$/); await expect(page.getByRole('heading',{name:'Товары',exact:true})).toBeVisible();
  await page.getByRole('link',{name:'Создать товар',exact:true}).click(); await page.getByLabel('Название',{exact:true}).fill('Браузерный товар'); await page.getByLabel('Адрес страницы').fill('browser-product'); await page.getByLabel('Категория',{exact:true}).selectOption('12'); await page.getByLabel('Цена, ₽').fill('123.45'); await page.getByLabel('Единица измерения').fill('шт');
  await page.getByLabel('JPEG, PNG или WebP, до 10 МБ').setInputFiles(new URL('../../ms/orbita-front/static/favicon.png', import.meta.url).pathname); await Promise.all([page.waitForResponse(response=>response.url().endsWith('/api/media')&&response.status()===201),page.getByRole('button',{name:'Загрузить изображение',exact:true}).click()]);
  await expect(page.locator('img').last()).toHaveAttribute('src',/\/media\/[a-f0-9-]+\.png$/); await expect.poll(()=>page.locator('img').last().evaluate(image=>(image as HTMLImageElement).naturalWidth)).toBeGreaterThan(0);
  await page.getByRole('button',{name:'Добавить текст',exact:true}).click(); await page.getByLabel('Содержание',{exact:true}).fill('<p>Описание браузерного товара</p>');
  await page.getByRole('button',{name:'Добавить характеристику',exact:true}).click(); await page.getByLabel('Обозначение',{exact:true}).fill('weight'); await page.getByLabel('Значение',{exact:true}).fill('25 кг');
  await page.getByRole('button',{name:'Сохранить товар',exact:true}).click(); await expect(page).toHaveURL(/\/admin\/products\/\d+\?saved=1/); await expect(page.getByRole('status')).toContainText('Карточка сохранена');
  await page.getByLabel('Цена, ₽').fill('234.56'); await Promise.all([page.waitForResponse(response=>response.url().endsWith('/graphql')&&response.request().method()==='POST'),page.getByRole('button',{name:'Сохранить товар',exact:true}).click()]); await expect(page.getByRole('status')).toContainText('Карточка сохранена');
  await page.screenshot({path:'../runtime/screenshots/admin-editor.png',fullPage:true}); await page.getByRole('link',{name:'К списку товаров'}).click(); const row=page.getByRole('row').filter({hasText:'Браузерный товар'}); await expect(row).toContainText('234.56');
  page.once('dialog',dialog=>dialog.accept()); await row.getByRole('button',{name:'Отключить',exact:true}).click(); await expect(row).toContainText('Отключён');
  await row.getByRole('button',{name:'Восстановить',exact:true}).click(); await expect(row).toContainText('Активен');
  await page.screenshot({path:'../runtime/screenshots/admin-products.png',fullPage:true}); await page.getByRole('button',{name:'Выйти',exact:true}).click(); await expect(page).toHaveURL(/\/admin\/login/);
});

test('actual catalogue, persistent cart, quote and offline order work with real CSRF',async({page})=>{
  await page.goto('/shop/product/product-1'); await expect(page.getByRole('heading',{name:'Товар 1',exact:true})).toBeVisible();
  await page.getByRole('button',{name:'В корзину',exact:true}).click(); await page.goto('/shop/cart'); await expect(page.getByText('Товар 1',{exact:true}).first()).toBeVisible(); await page.reload(); await expect(page.getByText('Товар 1',{exact:true}).first()).toBeVisible();
  await page.getByRole('button',{name:'Проверить цены и оформить заказ',exact:true}).click(); await page.getByLabel(/имя/i).fill('Покупатель'); await page.getByLabel(/телефон/i).fill('+79001234567');
  const address=page.getByLabel(/адрес/i); if(await address.count()) await address.fill('Дзержинск, тестовый адрес');
  await page.getByRole('button',{name:'Отправить менеджеру',exact:true}).click(); await expect(page).toHaveURL(/\/spasibo\?order=/); await expect(page.getByText(/заказ.*сохран|заказ.*получен|спасибо/i).first()).toBeVisible(); await page.screenshot({path:'../runtime/screenshots/order-confirmation.png',fullPage:true});
});

test('public search and unknown products handle errors without exposing other project',async({page})=>{
  await page.goto('/'); await page.getByRole('button',{name:/поиск товара/i}).first().click(); const input=page.getByRole('combobox'); await input.fill('Товар'); await expect(page.getByRole('dialog')).toContainText('Товар 1'); await expect(page.getByRole('dialog')).not.toContainText('Товар 3'); await page.keyboard.press('Escape'); await expect(page.getByRole('dialog')).toBeHidden();
  const res=await page.goto('/shop/product/does-not-exist'); expect(res?.status()).toBe(404);
});

test('protected order overview shows actual API dates, statuses and snapshot contacts',async({page})=>{
  expect((await page.request.get('/api/admin/orders',{headers:{Accept:'application/json'}})).status()).toBe(401);
  await page.request.get('/auth/csrf');
  const cookies=await page.context().cookies();
  const token=decodeURIComponent(cookies.find(cookie=>cookie.name==='XSRF-TOKEN')!.value);
  const headers={'X-XSRF-TOKEN':token,Accept:'application/json'};
  const items=[{productId:'1',quantity:'1'}];
  const quote=await (await page.request.post('/api/checkout/quote',{headers,data:{items}})).json();
  const created=await page.request.post('/api/orders',{headers,data:{items,quoteHash:quote.quoteHash,customer:{name:'Покупатель списка',phone:'+79001234567',address:'Дзержинск, тестовый адрес'},onlinePayment:false,idempotencyKey:crypto.randomUUID()}});
  expect(created.status()).toBe(201); const order=await created.json();
  await page.goto('/admin/login');
  await page.getByLabel('Email',{exact:true}).fill('editor@example.test'); await page.getByLabel('Пароль',{exact:true}).fill('Orbita-test-2026!'); await page.getByRole('button',{name:'Войти',exact:true}).click();
  await expect(page).toHaveURL(/\/admin\/products$/);
  await page.getByRole('link',{name:'Заказы и оплата',exact:true}).click();
  const row=page.getByRole('row').filter({hasText:order.id});
  await expect(row).toContainText('Принят'); await expect(row).toContainText('Оплата с менеджером'); await expect(row).not.toContainText('Invalid Date');
  await row.getByText('Состав и контакты',{exact:true}).click(); await expect(row).toContainText('Покупатель списка'); await expect(row).toContainText('+79001234567'); await expect(row).toContainText('Товар 1');
  await page.screenshot({path:'../runtime/screenshots/admin-orders.png',fullPage:true});
});

test('desktop and mobile pages have no horizontal overflow',async({page})=>{
  for(const width of [360,390,768,1024,1440]) {
    await page.setViewportSize({width,height:900});
    for(const path of ['/','/contact','/shop/products/goods','/shop/product/product-1','/shop/cart','/admin/login']) {
      await page.goto(path); const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth+1); expect(overflow,`${path} at ${width}px`).toBe(false);
    }
    await page.goto('/'); await page.screenshot({path:`../runtime/screenshots/implementation-${width}.png`,fullPage:true});
  }
});
