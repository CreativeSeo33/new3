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
        path: 'users',
        name: 'admin-users',
        component: () => import(/* webpackChunkName: "admin-users" */ '@admin/views/Users.vue'),
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


