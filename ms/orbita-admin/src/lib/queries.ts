export const PRODUCT_FIELDS = `id value slug is_active categoryId price { value } unit { value } image { hash alt url } text { id key value slug } property { id key value slug }`;
export const LIST = `query AdminProducts($search:String,$categoryId:ID,$status:Boolean,$page:Int,$first:Int){adminProducts(search:$search,categoryId:$categoryId,status:$status,page:$page,first:$first){data{${PRODUCT_FIELDS}}paginatorInfo{currentPage lastPage total}}referenceData{categories{id value}units{value}}}`;
export const DETAIL = `query AdminProduct($id:ID!){adminProduct(id:$id){${PRODUCT_FIELDS}}referenceData{categories{id value}units{value}}}`;
export const REFERENCES = `query References{referenceData{categories{id value}units{value}}}`;
export const CREATE = `mutation CreateProduct($input:ProductInput!){createProduct(input:$input){${PRODUCT_FIELDS}}}`;
export const UPDATE = `mutation UpdateProduct($id:ID!,$input:ProductInput!){updateProduct(id:$id,input:$input){${PRODUCT_FIELDS}}}`;
export const ARCHIVE = `mutation ArchiveProduct($id:ID!){archiveProduct(id:$id){id is_active}}`;
export const RESTORE = `mutation RestoreProduct($id:ID!){restoreProduct(id:$id){id is_active}}`;
