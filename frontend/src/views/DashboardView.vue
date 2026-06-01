<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Мои обращения</h1>
      <button
        @click="showCreateModal = true"
        class="px-4 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700"
      >
        + Новое обращение
      </button>
    </div>

    <!-- Loading state -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
      <p class="mt-2 text-gray-500">Загрузка...</p>
    </div>

    <!-- Empty state -->
    <div v-else-if="!tickets?.length" class="text-center py-12 bg-white rounded-lg shadow">
      <p class="text-gray-500">У вас пока нет обращений</p>
      <button
        @click="showCreateModal = true"
        class="mt-4 px-4 py-2 text-indigo-600 border border-indigo-600 rounded-md hover:bg-indigo-50"
      >
        Создать первое обращение
      </button>
    </div>

    <!-- Tickets list -->
    <div v-else class="bg-white rounded-lg shadow overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              ID
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Тема
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Раздел
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Статус
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Дата
            </th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr
            v-for="ticket in tickets"
            :key="ticket.id"
            @click="router.push({ name: 'Chat', params: { ticketId: ticket.id } })"
            class="cursor-pointer hover:bg-gray-50"
          >
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
              #{{ ticket.id }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
              {{ ticket.subject }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
              {{ SECTION_LABELS[ticket.section] }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span :class="['px-2 py-1 text-xs font-medium rounded-full', STATUS_COLORS[ticket.statusCode] || 'bg-gray-100 text-gray-800']">
                {{ ticket.statusName }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
              {{ formatDate(ticket.createdAt) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create Ticket Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg mx-4">
        <h2 class="text-xl font-bold mb-4">Новое обращение</h2>

        <form @submit.prevent="handleCreate" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Тема</label>
            <input
              v-model="newTicket.subject"
              type="text"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500"
              placeholder="Кратко опишите проблему"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Раздел</label>
            <select
              v-model="newTicket.section"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500"
            >
              <option v-for="(label, key) in SECTION_LABELS" :key="key" :value="key">
                {{ label }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Комментарий</label>
            <textarea
              v-model="newTicket.comment"
              rows="4"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500"
              placeholder="Подробное описание проблемы..."
            ></textarea>
          </div>

          <div v-if="createError" class="text-red-600 text-sm bg-red-50 p-3 rounded">
            {{ createError }}
          </div>

          <div class="flex justify-end gap-3">
            <button
              type="button"
              @click="showCreateModal = false"
              class="px-4 py-2 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50"
            >
              Отмена
            </button>
            <button
              type="submit"
              :disabled="isCreating"
              class="px-4 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 disabled:opacity-50"
            >
              {{ isCreating ? 'Создание...' : 'Создать' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useQuery } from '@vue/apollo-composable'
import { gql } from 'graphql-tag'
import { apolloClient } from '@/apollo/client'
import type { Ticket, CreateTicketInput, TicketSection } from '@/types'
import { SECTION_LABELS, STATUS_COLORS } from '@/types'

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
      // Reset form
      newTicket.subject = ''
      newTicket.section = 'GENERAL'
      newTicket.comment = ''
      // Refetch tickets
      await refetch()
    }
  } catch (err) {
    createError.value = err instanceof Error ? err.message : 'Ошибка создания обращения'
  } finally {
    isCreating.value = false
  }
}

// Watch for result changes
watch(result, (newResult) => {
  if (newResult?.myTickets) {
    tickets.value = newResult.myTickets
  }
}, { immediate: true })
</script>
