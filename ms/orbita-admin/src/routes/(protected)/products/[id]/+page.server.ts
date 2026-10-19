import { error } from '@sveltejs/kit'; import { gql } from '#lib/server/api.ts'; import { DETAIL } from '#lib/queries.ts'; import type { Product } from '@orbita/contracts'; import type { PageServerLoad } from './$types';
export const load: PageServerLoad = async event => {
  let result;
  try { result = await gql<{adminProduct:Product|null;referenceData:{categories:{id:string;value:string}[];units:{value:string}[];catalogWritesEnabled:boolean}}>(event, DETAIL.replace('categories{','catalogWritesEnabled categories{'),{id:event.params.id}); }
  catch { error(503,'Карточка временно недоступна. Повторите попытку.'); }
  if (!result.adminProduct) error(404,'Товар не найден'); return {...result,adminProduct:result.adminProduct,saved:event.url.searchParams.get('saved')==='1'};
};
