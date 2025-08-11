import { createRouter, createWebHistory } from 'vue-router';
import type { RouteRecordRaw } from 'vue-router';
import { adminRoutes } from './modules/admin';

const routes: RouteRecordRaw[] = [
  ...adminRoutes
];

export const router = createRouter({
  history: createWebHistory('/admin'),
  routes
});


