import type { RouteRecordRaw } from 'vue-router';

export const adminRoutes: RouteRecordRaw[] = [
  {
    path: '/',
    component: () => import(/* webpackChunkName: "admin-layout" */ '@admin/layouts/AdminLayout.vue'),
    children: [
      { path: '', redirect: { name: 'admin-dashboard' } },
      {
        path: 'dashboard',
        name: 'admin-dashboard',
        component: () => import(/* webpackChunkName: "admin-dashboard" */ '@admin/views/Dashboard.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: 'products',
        name: 'admin-products',
        component: () => import(/* webpackChunkName: "admin-products" */ '@admin/views/Products.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: 'categories',
        name: 'admin-categories',
        component: () => import(/* webpackChunkName: "admin-categories" */ '@admin/views/Categories.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: 'categories/:id',
        name: 'admin-category-form',
        component: () => import(/* webpackChunkName: "admin-category-form" */ '@admin/views/CategoryForm.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: 'products/:id',
        name: 'admin-product-form',
        component: () => import(/* webpackChunkName: "admin-product-form" */ '@admin/views/ProductForm.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: 'attributes',
        name: 'admin-attributes',
        component: () => import(/* webpackChunkName: "admin-attributes" */ '@admin/views/Attributes.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: 'attribute-groups',
        name: 'admin-attribute-groups',
        component: () => import(/* webpackChunkName: "admin-attribute-groups" */ '@admin/views/AttributeGroups.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: 'options',
        name: 'admin-options',
        component: () => import(/* webpackChunkName: "admin-options" */ '@admin/views/Options.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: 'option-values',
        name: 'admin-option-values',
        component: () => import(/* webpackChunkName: "admin-option-values" */ '@admin/views/OptionValues.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: 'design-system',
        name: 'admin-design-system',
        component: () => import(/* webpackChunkName: "admin-design-system" */ '@admin/views/DesignSystem.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: 'settings',
        name: 'admin-settings',
        component: () => import(/* webpackChunkName: "admin-settings" */ '@admin/views/Settings.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: 'users',
        name: 'admin-users',
        component: () => import(/* webpackChunkName: "admin-users" */ '@admin/views/Users.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: 'orders',
        name: 'admin-orders',
        component: () => import(/* webpackChunkName: "admin-orders" */ '@admin/views/Orders.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: 'order-statuses',
        name: 'admin-order-statuses',
        component: () => import(/* webpackChunkName: "admin-order-statuses" */ '@admin/views/OrderStatuses.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: 'cities',
        name: 'admin-cities',
        component: () => import(/* webpackChunkName: "admin-cities" */ '@admin/views/City.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: 'pvz-points',
        name: 'admin-pvz-points',
        component: () => import(/* webpackChunkName: "admin-pvz-points" */ '@admin/views/PvzPointsList.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: 'pvz-prices',
        name: 'admin-pvz-prices',
        component: () => import(/* webpackChunkName: "admin-pvz-prices" */ '@admin/views/PvzPrices.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: 'delivery-types',
        name: 'admin-delivery-types',
        component: () => import(/* webpackChunkName: "admin-delivery-types" */ '@admin/views/DeliveryTypes.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: ':pathMatch(.*)*',
        name: 'AdminNotFound',
        component: () => import(/* webpackChunkName: "not-found" */ '@admin/views/NotFound.vue')
      }
    ]
  }
];

// Навигация сайдбара: поддержка вложенных групп
export type AdminSidebarLink = {
  to: { name: string } | string;
  label: string;
  colorClass?: string;
};

export type AdminSidebarGroup = {
  label: string;
  icon?: string;
  children: AdminSidebarLink[];
};

export type AdminSidebarItem = AdminSidebarLink | AdminSidebarGroup;

export function isGroup(item: AdminSidebarItem): item is AdminSidebarGroup {
  return (item as AdminSidebarGroup).children !== undefined;
}

export const adminSidebarItems: AdminSidebarItem[] = [
  {
    to: { name: 'admin-dashboard' },
    label: 'Дашборд',
    colorClass: 'bg-blue-500'
  },
  { to: { name: 'admin-products' }, label: 'Товары', colorClass: 'bg-emerald-500' },
  { to: { name: 'admin-categories' }, label: 'Категории', colorClass: 'bg-cyan-500' },
  {
    label: 'Атрибуты',
    children: [
      { to: { name: 'admin-attributes' }, label: 'Атрибуты', colorClass: 'bg-violet-500' },
      { to: { name: 'admin-attribute-groups' }, label: 'Группы атрибутов', colorClass: 'bg-fuchsia-500' }
    ]
  },
  {
    label: 'Опции',
    children: [
      { to: { name: 'admin-options' }, label: 'Опции', colorClass: 'bg-rose-500' },
      { to: { name: 'admin-option-values' }, label: 'Значения опций', colorClass: 'bg-sky-500' }
    ]
  },
  {
    label: 'Доставка',
    children: [
      { to: { name: 'admin-cities' }, label: 'Города', colorClass: 'bg-lime-500' },
      { to: { name: 'admin-pvz-points' }, label: 'PVZ точки', colorClass: 'bg-orange-500' },
      { to: { name: 'admin-pvz-prices' }, label: 'PVZ цены', colorClass: 'bg-orange-400' },
      { to: { name: 'admin-delivery-types' }, label: 'Типы доставок', colorClass: 'bg-teal-500' }
    ]
  },
  {
    label: 'Система',
    children: [
      { to: { name: 'admin-orders' }, label: 'Заказы', colorClass: 'bg-purple-500' },
      { to: { name: 'admin-settings' }, label: 'Настройки', colorClass: 'bg-emerald-700' },
      { to: { name: 'admin-order-statuses' }, label: 'Статусы заказов', colorClass: 'bg-indigo-500' },
      { to: { name: 'admin-design-system' }, label: 'Design System', colorClass: 'bg-amber-500' },
      { to: { name: 'admin-users' }, label: 'Пользователи', colorClass: 'bg-slate-500' }
    ]
  }
];

// Обратная совместимость: плоский список ссылок формируется из групп
export const adminSidebarLinks: AdminSidebarLink[] = adminSidebarItems.flatMap((item) =>
  isGroup(item) ? item.children : [item]
);


