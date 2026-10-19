<script lang="ts">
  import { post } from '#lib/api.ts';
  let { demo = false }: { demo?: boolean } = $props();
  let email = $state(''), pending = $state(false), message = $state(''), failure = $state(false);
  async function subscribe(event: SubmitEvent) {
    event.preventDefault(); if (demo) { message = 'Подписка недоступна в демонстрационном режиме.'; return; } pending = true; message = ''; failure = false;
    try { const result = await post<{ message: string }>('/api/subscriptions', { email }); message = result.message; email = ''; }
    catch (error) { message = error instanceof Error ? error.message : 'Не удалось подписаться. Попробуйте ещё раз.'; failure = true; }
    finally { pending = false; }
  }
</script>
<div class="brands-section"><div class="brands container" aria-label="Производители">{#each ['Ceresit', 'Makroflex', 'Tarkett', 'EK', 'Krafor'] as brand, i}<img src={`https://lumen-image-bucket.s3.eu-central-1.amazonaws.com/brands/${i + 1}.png`} alt={brand} loading="lazy" />{/each}</div></div>
<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-links">
      <address class="footer-contacts"><h2>Контакты:</h2><p>г. Дзержинск, пр. Ленина, д. 121-В</p><a href="tel:+78314237096">8 (8314) 23-70-96</a><p>г. Дзержинск, ул. Космонавтов, 4-Б</p><a href="tel:+79023033654">8 (902) 303-36-54</a><a href="tel:+79306888862">8 (930) 688-88-62</a></address>
      <div class="footer-client-groups"><nav aria-label="Клиентам"><h2>Клиентам:</h2>{#each [{ href: '/information/partner', label: 'Партнёры' }, { href: '/information/delivery', label: 'Доставка' }, { href: '/information/payment', label: 'Оплата' }, { href: '/information/testimonials', label: 'Отзывы' }] as link}<a href={link.href}><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 11v6M12 7h.01" stroke-linecap="round" /></svg>{link.label}</a>{/each}</nav><div><h2>Партнёрам:</h2></div></div>
    </div>
    <form onsubmit={subscribe}><h2>Подписаться на новости</h2><p>Будьте в курсе новинок и спец предложений на стройматериалы в Дзержинске.</p><div class="newsletter-row"><label class="sr-only" for="newsletter-email">Ваша почта</label><input class="field" id="newsletter-email" name="email" type="email" required bind:value={email} placeholder="Ваша почта" autocomplete="email" /><button class="button" type="submit" disabled={pending}>{pending ? 'Отправляем…' : 'Подписаться'}</button></div>{#if message}<p role={failure ? 'alert' : 'status'} class:subscription-error={failure}>{message}</p>{/if}</form>
  </div>
  <div class="container footer-copyright"><p>© 2015-{new Date().getFullYear()} База строительных материалов "Орбита-строй". Интернет магазин стройматериалов в Дзержинске.<br />ООО "СТРОЙРЕСУРСНН" ИНН 5249164285 Юр. адрес: 603005, Нижегородская обл, г. Нижний Новгород, ул. Минина, 20<br />Р/С 40702810642000047648, ВОЛГО-ВЯТСКИЙ БАНК ПАО СБЕРБАНК, БИК 042202603</p><a href="/admin">Вход в административную панель</a></div>
</footer>
