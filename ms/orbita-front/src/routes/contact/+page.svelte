<script lang="ts">import type { PageProps } from './$types'; import ContentPage from '#lib/components/ContentPage.svelte'; let { data }: PageProps = $props();</script>
<ContentPage content={data.content} />
<nav class="container mb-12 flex flex-wrap gap-4" aria-label="Маршруты к магазинам">
  <a class="button button-secondary" href="https://yandex.ru/maps/?text=Дзержинск%2C%20проспект%20Ленина%2C%20121-В" target="_blank" rel="noopener noreferrer">Маршрут: пр. Ленина, 121-В</a>
  <a class="button button-secondary" href="https://yandex.ru/maps/?text=Дзержинск%2C%20улица%20Космонавтов%2C%204-Б" target="_blank" rel="noopener noreferrer">Маршрут: ул. Космонавтов, 4-Б</a>
</nav>
