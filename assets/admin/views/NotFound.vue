<template>
  <div class="flex flex-col items-center justify-center min-h-screen bg-gray-100 p-4">
    <h1 class="text-9xl font-extrabold text-gray-800">404</h1>
    <p class="text-2xl text-gray-600 mt-4">Страница не найдена</p>
    <router-link to="/" class="mt-6 inline-block px-6 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">
      На главную
    </router-link>
  </div>
</template>

<script setup lang="ts">
// Здесь можно подключить логику или трекинг, если нужно
</script>

<style scoped>
.not-found {
  text-align: center;
  margin-top: 50px;
}
</style>


