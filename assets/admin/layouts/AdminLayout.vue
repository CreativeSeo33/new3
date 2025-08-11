<template>
  <div class="min-h-screen bg-background text-foreground">
    <!-- Topbar -->
    <header class="sticky top-0 z-30 bg-card/80 backdrop-blur border-b">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <button class="md:hidden inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="Toggle navigation">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
          </button>
          <span class="font-semibold">Admin</span>
           <span class="hidden sm:inline text-xs text-muted-foreground">/</span>
           <span class="hidden sm:inline text-sm text-muted-foreground">Dashboard</span>
        </div>
        <div class="flex items-center gap-2">
          <div class="relative">
            <input type="search" placeholder="Поиск..." class="w-48 sm:w-64 rounded-md border bg-background text-foreground border-input focus-visible:ring-2 focus-visible:ring-ring text-sm" />
          </div>
          <router-link to="/design-system" class="h-9 px-3 rounded-md bg-primary text-primary-foreground text-sm hover:bg-primary/90">DS</router-link>
          <button class="h-9 w-9 grid place-items-center rounded-full border hover:bg-accent">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-600">
              <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0112 20.25a8.967 8.967 0 01-8.312-4.478 23.848 23.848 0 005.454 1.31m5.715 0a24.255 24.255 0 01-5.715 0m5.715 0a3 3 0 10-5.715 0" />
            </svg>
          </button>
        </div>
      </div>
    </header>

    <!-- Body -->
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6 grid grid-cols-1 md:grid-cols-[240px_1fr] gap-6">
      <!-- Sidebar -->
      <aside class="hidden md:block">
        <nav class="space-y-1">
          <router-link to="/dashboard" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm hover:bg-accent">
            <span class="inline-block w-2 h-2 rounded-full bg-primary"></span>
            Дашборд
          </router-link>
          <router-link to="/products" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm hover:bg-accent">
            <span class="inline-block w-2 h-2 rounded-full bg-muted"></span>
            Товары
          </router-link>
          <router-link to="/design-system" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm hover:bg-accent">
            <span class="inline-block w-2 h-2 rounded-full bg-secondary"></span>
            Design System
          </router-link>
        </nav>
      </aside>

      <!-- Content -->
      <main>
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
</script>

<style scoped>
</style>


