<template>
  <div class="flex flex-col h-[calc(100vh-8rem)] bg-white rounded-lg shadow">
    <!-- Header -->
    <div class="px-6 py-4 border-b border-gray-200">
      <div class="flex items-center justify-between">
        <div>
          <router-link to="/" class="text-sm text-indigo-600 hover:text-indigo-800 mb-1 inline-block">
            ← Назад к списку
          </router-link>
          <h1 class="text-xl font-bold text-gray-900">{{ ticket?.subject || 'Загрузка...' }}</h1>
        </div>
        <div v-if="status" class="flex items-center gap-2">
          <span :class="['px-3 py-1 text-sm font-medium rounded-full', STATUS_COLORS[status.code] || 'bg-gray-100 text-gray-800']">
            {{ status.name }}
          </span>
        </div>
      </div>
    </div>

    <!-- Messages area -->
    <div ref="messagesContainer" class="flex-1 overflow-y-auto p-6 space-y-4">
      <div v-if="loading" class="text-center py-8">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
      </div>

      <div v-else-if="!messages?.length" class="text-center py-8 text-gray-500">
        Нет сообщений
      </div>

      <div v-else class="space-y-4">
        <div
          v-for="message in messages"
          :key="message.id"
          :class="[
            'flex',
            message.senderType === 'USER' ? 'justify-end' : 'justify-start',
          ]"
        >
          <div
            :class="[
              'max-w-[70%] rounded-lg px-4 py-2',
              message.senderType === 'USER'
                ? 'bg-indigo-600 text-white'
                : message.statusCode
                  ? 'bg-yellow-50 border border-yellow-200'
                  : 'bg-gray-100',
            ]"
          >
            <p class="text-sm whitespace-pre-wrap">{{ message.content }}</p>
            <div class="flex items-center justify-between mt-1">
              <span :class="['text-xs', message.senderType === 'USER' ? 'text-indigo-200' : 'text-gray-500']">
                {{ message.senderType === 'USER' ? 'Вы' : 'Система' }}
              </span>
              <span :class="['text-xs', message.senderType === 'USER' ? 'text-indigo-200' : 'text-gray-400']">
                {{ formatTime(message.createdAt) }}
              </span>
            </div>
            <!-- Status info for system messages -->
            <div v-if="message.statusName" class="mt-2 pt-2 border-t border-gray-200">
              <span :class="['px-2 py-0.5 text-xs font-medium rounded-full', STATUS_COLORS[message.statusCode] || 'bg-gray-100 text-gray-800']">
                {{ message.statusName }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Input area -->
    <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
      <form @submit.prevent="handleSend" class="flex gap-3">
        <textarea
          v-model="newMessage"
          rows="2"
          placeholder="Введите сообщение..."
          class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 resize-none"
          @keydown.ctrl.enter="handleSend"
        ></textarea>
        <button
          type="submit"
          :disabled="isSending || !newMessage.trim()"
          class="px-6 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed self-end"
        >
          {{ isSending ? '...' : 'Отправить' }}
        </button>
      </form>
      <p class="text-xs text-gray-500 mt-2">Ctrl+Enter для отправки</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useQuery, useMutation } from '@vue/apollo-composable'
import { gql } from 'graphql-tag'
import type { Message, Ticket, TicketStatus, CreateMessageInput } from '@/types'
import { STATUS_COLORS } from '@/types'

const route = useRoute()
const ticketId = route.params.ticketId as string

const messagesContainer = ref<HTMLElement | null>(null)
const newMessage = ref('')
const isSending = ref(false)
let pollingInterval: number | null = null

// GraphQL queries
const TICKET_MESSAGES = gql`
  query TicketMessages($ticketId: ID!) {
    ticketMessages(ticketId: $ticketId) {
      id
      content
      senderType
      statusCode
      statusName
      createdAt
    }
  }
`

const TICKET_STATUS = gql`
  query TicketStatus($ticketId: ID!) {
    ticketStatus(ticketId: $ticketId) {
      code
      name
      message
    }
  }
`

const SEND_MESSAGE = gql`
  mutation SendMessage($input: CreateMessageInput!) {
    sendMessage(input: $input) {
      id
      content
      senderType
      createdAt
    }
  }
`

// Fetch messages
const { result: messagesResult, loading, refetch } = useQuery<{ ticketMessages: Message[] }, { ticketId: string }>(
  TICKET_MESSAGES,
  { ticketId }
)

// Fetch status
const { result: statusResult } = useQuery<{ ticketStatus: TicketStatus }, { ticketId: string }>(
  TICKET_STATUS,
  { ticketId }
)

const messages = ref<Message[]>([])
const status = ref<TicketStatus | null>(null)

// Watch for messages
watch(messagesResult, (newResult) => {
  if (newResult?.ticketMessages) {
    messages.value = newResult.ticketMessages
    scrollToBottom()
  }
}, { immediate: true })

// Watch for status
watch(statusResult, (newResult) => {
  if (newResult?.ticketStatus) {
    status.value = newResult.ticketStatus
  }
}, { immediate: true })

// Polling for updates
onMounted(() => {
  pollingInterval = window.setInterval(async () => {
    await refetch()
  }, 30000) // Every 30 seconds
})

onUnmounted(() => {
  if (pollingInterval) {
    clearInterval(pollingInterval)
  }
})

function formatTime(dateStr: string): string {
  return new Date(dateStr).toLocaleTimeString('ru-RU', {
    hour: '2-digit',
    minute: '2-digit',
  })
}

function scrollToBottom() {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  })
}

async function handleSend() {
  if (!newMessage.value.trim()) return

  isSending.value = true

  try {
    const { mutate } = useMutation<{ sendMessage: Message }, { input: CreateMessageInput }>(SEND_MESSAGE)
    await mutate({
      input: {
        ticketId,
        content: newMessage.value.trim(),
      },
    })

    newMessage.value = ''
    await refetch()
  } catch (err) {
    console.error('Failed to send message:', err)
  } finally {
    isSending.value = false
  }
}
</script>
