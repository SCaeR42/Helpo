<template>
  <div class="flex min-h-screen items-center justify-center bg-gray-100">
    <div class="w-full max-w-md bg-white rounded-lg shadow-md p-8">
      <h1 class="text-2xl font-bold text-center text-gray-900 mb-6">Вход в Helpo</h1>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label for="login" class="block text-sm font-medium text-gray-700 mb-1">
            Логин
          </label>
          <input
            id="login"
            v-model="form.login"
            type="text"
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
            placeholder="user@example.com"
          />
        </div>

        <div>
          <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
            Пароль
          </label>
          <input
            id="password"
            v-model="form.password"
            type="password"
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
            placeholder="••••••••"
          />
        </div>

        <div v-if="error" class="text-red-600 text-sm bg-red-50 p-3 rounded">
          {{ error }}
        </div>

        <button
          type="submit"
          :disabled="isLoading"
          class="w-full px-4 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ isLoading ? 'Вход...' : 'Войти' }}
        </button>
      </form>

      <p class="mt-4 text-xs text-center text-gray-500">
        Если пользователь не найден, будет создан новый аккаунт
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth.store'

const router = useRouter()
const authStore = useAuthStore()

const form = reactive({
  login: '',
  password: '',
})

const error = ref<string | null>(null)
const isLoading = ref(false)

async function handleSubmit() {
  error.value = null
  isLoading.value = true

  try {
    await authStore.login(form)
    router.push({ name: 'Dashboard' })
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Ошибка авторизации'
  } finally {
    isLoading.value = false
  }
}
</script>
