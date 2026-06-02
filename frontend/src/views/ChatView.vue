<template>
  <div class="flex flex-col h-[calc(100vh-7rem)] sm:h-[calc(100vh-8rem)] bg-white rounded-2xl shadow-md border border-gray-100 overflow-hidden animate-slide-up">
    <!-- Header -->
    <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-100 bg-gradient-to-r from-mint-50 to-accent-50">
      <div class="flex items-center justify-between">
        <div class="min-w-0 flex-1">
          <router-link to="/" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 hover:text-primary-700 mb-1 sm:mb-2 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            <span class="hidden sm:inline">Назад к списку</span>
            <span class="sm:hidden">Назад</span>
          </router-link>
          <h1 class="text-base sm:text-xl font-bold text-gray-800 truncate">{{ ticket?.subject || 'Загрузка...' }}</h1>
        </div>
        <div v-if="status" class="flex-shrink-0 ml-2 sm:ml-4">
          <span :class="['inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg', getStatusColor(status.code)]">
            <span class="w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full" :class="getStatusDot(status.code)"></span>
            <span class="hidden sm:inline">{{ status.name }}</span>
          </span>
        </div>
      </div>
    </div>

    <!-- Messages area -->
    <div ref="messagesContainer" class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-3 sm:space-y-4 bg-gray-50/50">
      <div v-if="loading" class="flex flex-col items-center justify-center py-12">
        <div class="w-10 h-10 sm:w-12 sm:h-12 border-4 border-mint-200 rounded-full animate-spin border-t-primary-500"></div>
        <p class="mt-3 text-gray-500 text-sm">Загрузка сообщений...</p>
      </div>

      <div v-else-if="!messages?.length" class="flex flex-col items-center justify-center py-12">
        <div class="w-14 h-14 sm:w-16 sm:h-16 bg-gradient-to-br from-mint-100 to-accent-100 rounded-full flex items-center justify-center mb-4">
          <svg class="w-7 h-7 sm:w-8 sm:h-8 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
          </svg>
        </div>
        <p class="text-gray-500 font-medium">Нет сообщений</p>
        <p class="text-gray-400 text-sm mt-1">Начните диалог с поддержкой</p>
      </div>

      <div v-else class="space-y-3">
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
              'max-w-[85%] sm:max-w-[75%] rounded-2xl px-3 sm:px-4 py-2.5 sm:py-3 shadow-sm',
              message.senderType === 'USER'
                ? 'bg-gradient-to-br from-primary-500 to-accent-500 text-white shadow-primary-500/20'
                : message.statusCode
                  ? 'bg-amber-50 border border-amber-200'
                  : 'bg-white border border-gray-100',
            ]"
          >
            <p class="text-sm whitespace-pre-wrap leading-relaxed">{{ message.content }}</p>
            <div class="flex items-center justify-between mt-2 pt-2" :class="message.senderType === 'USER' ? 'border-t border-white/20' : 'border-t border-gray-100'">
              <div class="flex items-center gap-1.5">
                <div class="w-5 h-5 rounded-full flex items-center justify-center" :class="message.senderType === 'USER' ? 'bg-white/20' : 'bg-gray-200'">
                  <svg class="w-3 h-3" :class="message.senderType === 'USER' ? 'text-white' : 'text-gray-500'" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                  </svg>
                </div>
                <span class="text-xs font-medium" :class="message.senderType === 'USER' ? 'text-white/80' : 'text-gray-500'">
                  {{ message.senderType === 'USER' ? 'Вы' : 'Система' }}
                </span>
              </div>
              <span class="text-xs" :class="message.senderType === 'USER' ? 'text-white/60' : 'text-gray-400'">
                {{ formatTime(message.createdAt) }}
              </span>
            </div>
            <!-- Status info for system messages -->
            <div v-if="message.statusName" class="mt-2 pt-2 border-t border-amber-200">
              <span :class="['inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg', getStatusColor(message.statusCode)]">
                <span class="w-1.5 h-1.5 rounded-full" :class="getStatusDot(message.statusCode)"></span>
                {{ message.statusName }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Input area -->
    <div class="px-4 sm:px-6 py-3 sm:py-4 border-t border-gray-100 bg-white">
      <form @submit.prevent="handleSend" class="flex gap-2 sm:gap-3">
        <textarea
            v-model="newMessage"
            rows="2"
            placeholder="Введите сообщение..."
            class="flex-1 px-3 sm:px-4 py-2.5 sm:py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-200 bg-gray-50 hover:bg-white resize-none text-sm sm:text-base"
            @keydown.ctrl.enter="handleSend"
        ></textarea>
        <button
            type="submit"
            :disabled="isSending || !newMessage.trim()"
            class="inline-flex items-center justify-center gap-2 px-4 sm:px-6 py-2.5 sm:py-3 font-semibold rounded-xl bg-gradient-to-r from-primary-500 to-accent-500 text-white hover:from-primary-600 hover:to-accent-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 shadow-lg shadow-primary-500/30 self-end"
        >
          <span v-if="isSending">
            <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
          </span>
          <span v-else class="flex items-center gap-1.5 sm:gap-2">
            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
            </svg>
            <span class="hidden sm:inline">Отправить</span>
          </span>
        </button>
      </form>
      <p class="text-xs text-gray-400 mt-2 flex items-center gap-1">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        Ctrl+Enter для отправки
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import {ref, watch, nextTick, onMounted, onUnmounted} from 'vue'
import {useRoute} from 'vue-router'
import {useQuery} from '@vue/apollo-composable'
import {gql} from 'graphql-tag'
import {apolloClient} from '@/apollo/client'
import type {Message, TicketStatus, CreateMessageInput} from '@/types'

const route = useRoute()
const ticketId = route.params.ticketId as string

const messagesContainer = ref<HTMLElement | null>(null)
const newMessage = ref('')
const isSending = ref(false)
let pollingInterval: number | null = null

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

const {result: messagesResult, loading, refetch} = useQuery<{ ticketMessages: Message[] }, { ticketId: string }>(
    TICKET_MESSAGES,
    {ticketId}
)

const {result: statusResult} = useQuery<{ ticketStatus: TicketStatus }, { ticketId: string }>(
    TICKET_STATUS,
    {ticketId}
)

const messages = ref<Message[]>([])
const status = ref<TicketStatus | null>(null)
const pollingIntervalMs = Number(import.meta.env.VITE_CHAT_POLLING_INTERVAL) || 30000

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

watch(messagesResult, (newResult) => {
  if (newResult?.ticketMessages) {
    messages.value = newResult.ticketMessages
    scrollToBottom()
  }
}, {immediate: true})

watch(statusResult, (newResult) => {
  if (newResult?.ticketStatus) {
    status.value = newResult.ticketStatus
  }
}, {immediate: true})

onMounted(() => {
  pollingInterval = window.setInterval(async () => {
    await refetch()
  }, pollingIntervalMs)
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
    await apolloClient.mutate<{ sendMessage: Message }, { input: CreateMessageInput }>({
      mutation: SEND_MESSAGE,
      variables: {
        input: {
          ticketId,
          content: newMessage.value.trim(),
        },
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
