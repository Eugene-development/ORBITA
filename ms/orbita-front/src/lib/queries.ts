export const productFields = `id value slug is_active price { value } unit { value } image { hash alt url } text { key value } property { id slug value } parentable { ... on Category { id value slug parentable { ... on Rubric { id value slug parentable { ... on Catalog { id value slug } } } } } }`;
export const PRODUCTS = `query Products($search:String,$categoryId:ID,$page:Int,$first:Int) { products(search:$search,categoryId:$categoryId,page:$page,first:$first) { data { ${productFields} } paginatorInfo { currentPage lastPage total hasMorePages } } }`;
export const PRODUCT_IDS = `query ProductIds($ids:[ID!]!) { productIds(ids:$ids) { ${productFields} } }`;
export const PRODUCT = `query Product($slug:String!) { product_one(slug:$slug,key:"1") { ${productFields} } }`;
export const CATALOG = `query Catalog { catalog(key:"1") { id value slug rubric { id value slug category { id value slug } } } }`;
export const CATALOG_ONE = `query CatalogOne($slug:String!) { catalog_one(slug:$slug,key:"1") { id value slug text { key value } rubric { id value slug category { id value slug } } } }`;
export const RUBRIC_ONE = `query RubricOne($slug:String!) { rubric_one(slug:$slug,key:"1") { id value slug text { key value } category { id value slug } parentable { ... on Catalog { id value slug } } } }`;
export const CATEGORY_ONE = `query CategoryOne($slug:String!) { category_one(slug:$slug,key:"1") { id value slug text { key value } parentable { ... on Rubric { id value slug parentable { ... on Catalog { id value slug } } } } } }`;
