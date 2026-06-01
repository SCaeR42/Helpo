<template>
  <div class="animate-slide-up">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 sm:mb-8 gap-4">
      <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Мои обращения</h1>
        <p class="text-sm text-gray-500 mt-1">Управляйте вашими обращениями в техническую поддержку</p>
      </div>
      <button
        @click="showCreateModal = true"
        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 font-semibold rounded-xl bg-gradient-to-r from-primary-500 to-accent-500 text-white hover:from-primary-600 hover:to-accent-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 shadow-lg shadow-primary-500/30 hover:shadow-xl hover:shadow-primary-500/40 hover:-translate-y-0.5"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Новое обращение
      </button>
    </div>

    <!-- Loading state -->
    <div v-if="loading" class="flex flex-col items-center justify-center py-16 sm:py-20">
      <div class="relative">
        <div class="w-12 h-12 sm:w-16 sm:h-16 border-4 border-mint-200 rounded-full animate-spin border-t-primary-500"></div>
      </div>
      <p class="mt-4 text-gray-500 font-medium">Загрузка обращений...</p>
    </div>

    <!-- Empty state -->
    <div v-else-if="!tickets?.length" class="flex flex-col items-center justify-center py-16 sm:py-20 bg-white rounded-2xl shadow-md border border-gray-100 overflow-hidden">
      <div class="w-20 h-20 sm:w-24 sm:h-24 bg-gradient-to-br from-mint-100 to-accent-100 rounded-full flex items-center justify-center mb-6">
        <svg class="w-10 h-10 sm:w-12 sm:h-12 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
      </div>
      <h3 class="text-lg sm:text-xl font-semibold text-gray-800 mb-2 text-center px-4">У вас пока нет обращений</h3>
      <p class="text-gray-500 mb-6 text-center px-4">Создайте первое обращение в техническую поддержку</p>
      <button
        @click="showCreateModal = true"
        class="inline-flex items-center justify-center gap-2 px-6 py-3 font-semibold rounded-xl bg-gradient-to-r from-primary-500 to-accent-500 text-white hover:from-primary-600 hover:to-accent-600 transition-all duration-200 shadow-lg shadow-primary-500/30"
      >
        Создать обращение
      </button>
    </div>

    <!-- Tickets list -->
    <div v-else>
      <!-- Desktop table -->
      <div class="bg-white rounded-2xl shadow-md border border-gray-100 overflow-hidden hidden sm:block">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gradient-to-r from-mint-50 to-accent-50">
              <tr>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ID</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Тема</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Раздел</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Статус</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Дата</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
              <tr
                v-for="ticket in tickets"
                :key="ticket.id"
                @click="router.push({ name: 'Chat', params: { ticketId: ticket.id } })"
                class="cursor-pointer hover:bg-mint-50/50 transition-colors duration-150"
              >
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-primary-600">#{{ ticket.id }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-800">{{ ticket.subject }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                  <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-xs font-medium">
                    {{ SECTION_LABELS[ticket.section] }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="['inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg', getStatusColor(ticket.statusCode)]">
                    <span class="w-1.5 h-1.5 rounded-full" :class="getStatusDot(ticket.statusCode)"></span>
                    {{ ticket.statusName }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDate(ticket.createdAt) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Mobile cards -->
      <div class="space-y-3 sm:hidden">
        <div
          v-for="ticket in tickets"
          :key="ticket.id"
          @click="router.push({ name: 'Chat', params: { ticketId: ticket.id } })"
          class="bg-white rounded-2xl shadow-md border border-gray-100 overflow-hidden p-4 cursor-pointer hover:bg-mint-50/50 transition-colors duration-150"
        >
          <div class="flex items-start justify-between mb-3">
            <div>
              <span class="text-sm font-medium text-primary-600">#{{ ticket.id }}</span>
              <h3 class="text-base font-semibold text-gray-800 mt-1">{{ ticket.subject }}</h3>
            </div>
            <span :class="['inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg', getStatusColor(ticket.statusCode)]">
              <span class="w-1.5 h-1.5 rounded-full" :class="getStatusDot(ticket.statusCode)"></span>
            </span>
          </div>
          <div class="flex items-center justify-between text-sm">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-gray-100 text-gray-700 rounded-lg text-xs font-medium">
              {{ SECTION_LABELS[ticket.section] }}
            </span>
            <span class="text-gray-500 text-xs">{{ formatDate(ticket.createdAt) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Ticket Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4" @click.self="showCreateModal = false">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden animate-fade-in">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-primary-500 to-accent-500 px-6 py-4">
          <div class="flex items-center justify-between">
            <h2 class="text-lg sm:text-xl font-bold text-white">Новое обращение</h2>
            <button @click="showCreateModal = false" class="text-white/80 hover:text-white transition-colors p-1">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
        </div>

        <form @submit.prevent="handleCreate" class="p-4 sm:p-6 space-y-4 sm:space-y-5">
          <!-- Subject -->
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Тема обращения</label>
            <input
              v-model="newTicket.subject"
              type="text"
              required
              class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-200 bg-gray-50 hover:bg-white"
              placeholder="Кратко опишите проблему"
            />
          </div>

          <!-- Section -->
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Раздел</label>
            <select
              v-model="newTicket.section"
              required
              class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-200 bg-gray-50 hover:bg-white"
            >
              <option v-for="(label, key) in SECTION_LABELS" :key="key" :value="key">{{ label }}</option>
            </select>
          </div>

          <!-- Comment -->
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Комментарий</label>
            <textarea
              v-model="newTicket.comment"
              rows="4"
              class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-200 bg-gray-50 hover:bg-white resize-none"
              placeholder="Подробное описание проблемы..."
            ></textarea>
          </div>

          <!-- Error Message -->
          <div v-if="createError" class="flex items-center gap-2 text-red-600 text-sm bg-red-50 p-4 rounded-xl border border-red-100 animate-fade-in">
            <svg class="h-5 w-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
            </svg>
            {{ createError }}
          </div>

          <!-- Actions -->
          <div class="flex flex-col sm:flex-row justify-end gap-3 pt-2">
            <button
              type="button"
              @click="showCreateModal = false"
              class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 font-semibold rounded-xl bg-white text-gray-700 border border-gray-200 hover:bg-gray-50 hover:text-gray-800 transition-all duration-200"
            >
              Отмена
            </button>
            <button
              type="submit"
              :disabled="isCreating"
              class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 font-semibold rounded-xl bg-gradient-to-r from-primary-500 to-accent-500 text-white hover:from-primary-600 hover:to-accent-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 shadow-lg shadow-primary-500/30"
            >
              {{ isCreating ? 'Создание...' : 'Создать обращение' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useQuery } from '@vue/apollo-composable'
import { gql } from 'graphql-tag'
import { apolloClient } from '@/apollo/client'
import type { Ticket, CreateTicketInput, TicketSection } from '@/types'
import { SECTION_LABELS } from '@/types'

const router = useRouter()

const MY_TICKETS = gql`
  query MyTickets {
    myTickets {
      id
      subject
      section
      statusCode
      statusName
      createdAt
    }
  }
`

const CREATE_TICKET = gql`
  mutation CreateTicket($input: CreateTicketInput!) {
    createTicket(input: $input) {
      id
      subject
      statusCode
    }
  }
`

const { result, loading, refetch } = useQuery<{ myTickets: Ticket[] }>(MY_TICKETS)

const tickets = ref<Ticket[]>([])
const showCreateModal = ref(false)
const isCreating = ref(false)
const createError = ref<string | null>(null)

const newTicket = reactive<CreateTicketInput>({
  subject: '',
  section: 'GENERAL' as TicketSection,
  comment: '',
})

function getStatusColor(statusCode: string): string {
  const colors: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700',
    processing: 'bg-blue-100 text-blue-700',
    in_progress: 'bg-purple-100 text-purple-700',
    review: 'bg-orange-100 text-orange-700',
    completed: 'bg-emerald-100 text-emerald-700',
  }
  return colors[statusCode] || 'bg-gray-100 text-gray-700'
}

function getStatusDot(statusCode: string): string {
  const colors: Record<string, string> = {
    pending: 'bg-amber-500',
    processing: 'bg-blue-500',
    in_progress: 'bg-purple-500',
    review: 'bg-orange-500',
    completed: 'bg-emerald-500',
  }
  return colors[statusCode] || 'bg-gray-500'
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

async function handleCreate() {
  isCreating.value = true
  createError.value = null

  try {
    const result = await apolloClient.mutate<{ createTicket: Ticket }, { input: CreateTicketInput }>({
      mutation: CREATE_TICKET,
      variables: { input: { ...newTicket } },
    })

    if (result?.data?.createTicket) {
      showCreateModal.value = false
      newTicket.subject = ''
      newTicket.section = 'GENERAL' as TicketSection
      newTicket.comment = ''
      await refetch()
    }
  } catch (err) {
    createError.value = err instanceof Error ? err.message : 'Ошибка создания обращения'
  } finally {
    isCreating.value = false
  }
}

watch(result, (newResult) => {
  if (newResult?.myTickets) {
    tickets.value = newResult.myTickets
  }
}, { immediate: true })
</script>
