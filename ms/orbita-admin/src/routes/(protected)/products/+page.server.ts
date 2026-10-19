import { gql } from '#lib/server/api.ts'; import { LIST } from '#lib/queries.ts'; import type { Product } from '@orbita/contracts';
import type { PageServerLoad } from './$types';
export const load: PageServerLoad = async event => {
  const search = event.url.searchParams.get('search') || ''; const categoryId = event.url.searchParams.get('category') || undefined;
  const statusParam = event.url.searchParams.get('status') || ''; const status = statusParam === '' ? undefined : statusParam === 'active';
  const page = Math.max(1, Number(event.url.searchParams.get('page')) || 1);
  try { const result = await gql<{adminProducts:{data:Product[];paginatorInfo:{currentPage:number;lastPage:number;total:number}};referenceData:{categories:{id:string;value:string}[];units:{value:string}[]}}>(event, LIST, { search, categoryId, status, page, first: 24 }); return { ...result, search, categoryId, statusParam, error: '' }; }
  catch { return { adminProducts: { data: [], paginatorInfo: { currentPage: page, lastPage: 1, total: 0 } }, referenceData: { categories: [], units: [] }, search, categoryId, statusParam, error: 'Не удалось загрузить каталог. Проверьте доступность API и повторите попытку.' }; }
};
