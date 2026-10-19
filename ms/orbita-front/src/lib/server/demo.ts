import type { CatalogNode, Product, ProductPage } from '../types.ts';
import { navigationCatalog } from '../site.ts';
import promotions from '../content/promotions.json';
const category: CatalogNode = { id: '12', value: 'Сухие строительные смеси', slug: 'suxie-stroitelnye-smesi', parentable: { id: '2', value: 'Строительные смеси', slug: 'stroitelnye-smesi', parentable: navigationCatalog[0] } };
const examples = [...promotions.homeProducts.filter((item) => !!item?.id), ...promotions.monthProducts.filter((item) => item.link.startsWith('/shop/product/'))];
export const demoProducts: Product[] = Array.from(new Map(examples.map((item) => [item!.id, item])).values()).map((item) => ({ id: item!.id, value: item!.action, slug: item!.link.split('/').pop()!, is_active: true, price: { value: `${item!.price.includes('.') ? item!.price : `${item!.price}.00`}` }, unit: { value: item!.unit }, image: { hash: item!.img.split('/').pop()!, alt: item!.action }, text: [{ key: 'description', value: '<p>Демонстрационная карточка. Наличие товара уточняйте у менеджера.</p>' }], property: [], parentable: category }));
export const demoCatalog: CatalogNode[] = navigationCatalog.map((item) => ({ ...item, rubric: [{ id: '2', value: 'Строительные смеси', slug: item.id === '1' ? 'stroitelnye-smesi' : `${item.slug}-rubrika`, category: [{ ...category, product: demoProducts }] }] }));
export function demoQuery(query: string, variables: Record<string, unknown>) {
  if (query.includes('query CatalogOne')) return { catalog_one: demoCatalog.find((item) => item.slug === variables.slug) ?? null };
  if (query.includes('query RubricOne')) { const parent = demoCatalog.find((item) => item.rubric?.some((rubric) => rubric.slug === variables.slug)); return { rubric_one: parent ? { ...parent.rubric![0], parentable: parent } : null }; }
  if (query.includes('query CategoryOne')) return { category_one: variables.slug === category.slug ? category : null };
  if (query.includes('query Product(')) return { product_one: demoProducts.find((item) => item.slug === variables.slug) ?? null };
  if (query.includes('query ProductIds')) return { productIds: demoProducts.filter((item) => (variables.ids as string[]).includes(item.id)) };
  if (query.includes('query Products')) {
    const products = demoProducts.filter((item) => item.value.toLocaleLowerCase('ru').includes(String(variables.search ?? '').toLocaleLowerCase('ru')));
    const page = Number(variables.page ?? 1), first = Number(variables.first ?? 12), lastPage = Math.max(1, Math.ceil(products.length / first));
    const result: ProductPage = { data: products.slice((page - 1) * first, page * first), paginatorInfo: { currentPage: page, lastPage, total: products.length, hasMorePages: page < lastPage } };
    return { products: result };
  }
  return { catalog: demoCatalog };
}
