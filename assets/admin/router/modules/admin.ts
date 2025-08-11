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
        path: 'design-system',
        name: 'admin-design-system',
        component: () => import(/* webpackChunkName: "admin-design-system" */ '@admin/views/DesignSystem.vue'),
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


