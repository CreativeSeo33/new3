// Базовые типы для API ответов

export interface ApiResponse<T = any> {
  data?: T;
  error?: string;
  message?: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  limit: number;
  'hydra:member'?: T[];
  'hydra:totalItems'?: number;
  'hydra:view'?: {
    'hydra:first'?: string;
    'hydra:last'?: string;
    'hydra:next'?: string;
    'hydra:previous'?: string;
  };
}

// Типы для корзины
export interface CartItem {
  id: string; // Теперь ULID в base32 формате
  productId: number;
  name: string;
  unitPrice: number;
  qty: number;
  rowTotal: number;
  optionsPriceModifier: number;
  effectiveUnitPrice: number;
  selectedOptions: ProductOption[];
  optionsHash: string | null;
}

export interface Cart {
  id: string; // Теперь ULID в base32 формате
  currency: string;
  subtotal: number;
  discountTotal: number;
  total: number;
  totalItemQuantity?: number;
  shipping: {
    method?: string;
    cost: number;
    city?: string;
    data?: any;
  };
  items: CartItem[];
}

export interface CartSummary {
  version: number;
  itemsCount: number;
  totalItemQuantity?: number;
  subtotal: number;
  discountTotal: number;
  total: number;
}

// Типы для товаров
export interface Product {
  id: number;
  name: string;
  sku?: string;
  price?: number;
  effectivePrice?: number;
  description?: string;
  image?: ProductImage[];
  optionAssignments?: ProductOptionAssignment[];
}

export interface ProductImage {
  id: number;
  imageUrl: string;
  sortOrder: number;
}

export interface ProductOptionAssignment {
  id: number;
  option: ProductOption;
  value: ProductOptionValue;
  price: number;
  salePrice?: number;
  sku?: string;
  quantity?: number;
  attributes?: any;
}

export interface ProductOption {
  id: number;
  code: string;
  name: string;
  sortOrder: number;
}

export interface ProductOptionValue {
  id: number;
  code: string;
  value: string;
  sortOrder: number;
}

// Типы для HTTP клиента
export interface HttpOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  headers?: Record<string, string>;
  body?: any;
  signal?: AbortSignal;
  /**
   * Управление глобальным спиннером корзины:
   *  - true  => всегда показывать
   *  - false => никогда не показывать
   *  - undefined => авто (только для /api/cart*)
   */
  showCartSpinner?: boolean;
}

export interface HttpGetOptions extends Omit<HttpOptions, 'method' | 'body'> {
  signal?: AbortSignal;
}
export interface HttpPostOptions extends Omit<HttpOptions, 'method'> {}
export interface HttpPutOptions extends Omit<HttpOptions, 'method'> {}
export interface HttpPatchOptions extends Omit<HttpOptions, 'method'> {}
export interface HttpDeleteOptions extends Omit<HttpOptions, 'method' | 'body'> {}

// Утилитарные типы
export type ProductOptionData = {
  id: number;
  name: string;
  value: string;
  price: number;
  setPrice?: boolean;
  salePrice?: number;
};

// Delta ответы для оптимизации
export interface CartDelta {
  version: number;
  changedItems: Array<{
    id: number;
    qty: number;
    rowTotal: number;
    effectiveUnitPrice: number;
  }>;
  removedItemIds: number[];
  totalItemQuantity?: number;
  totals: {
    itemsCount: number;
    subtotal: number;
    discountTotal: number;
    total: number;
  };
}