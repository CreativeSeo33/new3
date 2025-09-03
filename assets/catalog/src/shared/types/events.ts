// Типобезопасные события согласно рекомендациям

// События корзины
export interface CartUpdatedDetail {
  id: number;
  currency: string;
  subtotal: number;
  discountTotal: number;
  total: number;
  shipping: {
    method?: string;
    cost: number;
    city?: string;
    data?: any;
  };
  items: Array<{
    id: number;
    productId: number;
    name: string;
    unitPrice: number;
    qty: number;
    rowTotal: number;
    optionsPriceModifier: number;
    effectiveUnitPrice: number;
    selectedOptions: any[];
    optionsHash: string | null;
  }>;
}

// События опций товара
export interface ProductOptionsChangedDetail {
  selectedOptions: Array<{
    id: number;
    name: string;
    value: string;
    price: number;
  }>;
  totalPriceModifier: number;
  formElement: HTMLFormElement;
}

// Глобальные типы событий
declare global {
  interface DocumentEventMap {
    'cart:updated': CustomEvent<CartUpdatedDetail>;
    'product:options-changed': CustomEvent<ProductOptionsChangedDetail>;
  }
}
