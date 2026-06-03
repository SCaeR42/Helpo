import {ref, onUnmounted} from 'vue'
import type {Message} from '@/types'

const WS_URL = import.meta.env.VITE_WS_URL || 'ws://127.0.0.1:8080'

/**
 * Composable для работы с чатом через WebSocket.
 * 
 * Логика:
 * 1. При вызове connect() устанавливается WebSocket соединение
 * 2. После подключения отправляется подписка на ticketId
 * 3. Сервер возвращает последние 10 сообщений (type: 'history')
 * 4. Новые сообщения приходят через type: 'message'
 * 5. При отправке сообщения через GraphQL, оно также придёт через WS (от других клиентов)
 */
export function useWebSocketChat() {
  const messages = ref<Message[]>([])
  const isConnected = ref(false)
  const isConnecting = ref(false)
  const error = ref<string | null>(null)

  let ws: WebSocket | null = null
  let reconnectTimeout: number | null = null
  let currentTicketId: string | null = null
  let reconnectAttempts = 0
  const maxReconnectAttempts = 5
  const reconnectDelay = 3000

  /**
   * Подключиться к WebSocket серверу и подписаться на тикет.
   */
  function connect(ticketId: string) {
    console.log('[WS] Connecting to chat:', ticketId)
    
    if (isConnected.value || isConnecting.value) {
      console.log('[WS] Already connected or connecting, skipping')
      return
    }

    currentTicketId = ticketId
    isConnecting.value = true
    error.value = null

    const token = localStorage.getItem('jwt_token')
    if (!token) {
      console.error('[WS] JWT token not found')
      error.value = 'JWT token not found'
      isConnecting.value = false
      return
    }

    const wsUrl = `${WS_URL}?token=${encodeURIComponent(token)}`
    console.log('[WS] URL:', wsUrl)
    
    try {
      ws = new WebSocket(wsUrl)
    } catch (e) {
      console.error('[WS] Failed to create WebSocket:', e)
      error.value = 'Failed to create WebSocket connection'
      isConnecting.value = false
      return
    }

    ws.onopen = () => {
      console.log('[WS] Connection opened')
      isConnected.value = true
      isConnecting.value = false
      reconnectAttempts = 0
      
      // Отправляем подписку на тикет
      const subscribeMsg = JSON.stringify({
        action: 'subscribe',
        ticketId: parseInt(ticketId),
      })
      console.log('[WS] Sending subscribe:', subscribeMsg)
      ws?.send(subscribeMsg)
    }

    ws.onmessage = (event) => {
      console.log('[WS] Raw message received:', event.data)
      try {
        const data = JSON.parse(event.data)
        console.log('[WS] Parsed message:', data)
        handleMessage(data)
      } catch (e) {
        console.error('[WS] Failed to parse WebSocket message:', e)
      }
    }

    ws.onclose = (event) => {
      console.log('[WS] Connection closed:', event.code, event.reason)
      isConnected.value = false
      isConnecting.value = false
      
      // Пробуем переподключиться
      if (reconnectAttempts < maxReconnectAttempts && currentTicketId) {
        reconnectAttempts++
        console.log(`[WS] Reconnecting attempt ${reconnectAttempts}/${maxReconnectAttempts}...`)
        reconnectTimeout = window.setTimeout(() => {
          connect(currentTicketId!)
        }, reconnectDelay)
      }
    }

    ws.onerror = (err) => {
      console.error('[WS] Connection error:', err)
      error.value = 'WebSocket connection error'
    }
  }

  /**
   * Обработать входящее сообщение от сервера.
   */
  function handleMessage(data: any) {
    switch (data.type) {
      case 'history':
        // Заменяем все сообщения историей
        messages.value = data.messages || []
        break
        
      case 'message':
        // Добавляем новое сообщение
        if (data.message) {
          messages.value.push(data.message)
        }
        break
        
      case 'error':
        error.value = data.message || 'Unknown error'
        break
        
      case 'pong':
        // Ответ на ping, игнорируем
        break
    }
  }

  /**
   * Отправить ping для поддержания соединения.
   */
  function ping() {
    if (ws && ws.readyState === WebSocket.OPEN) {
      ws.send(JSON.stringify({action: 'ping'}))
    }
  }

  /**
   * Отключиться от WebSocket сервера.
   */
  function disconnect() {
    if (reconnectTimeout) {
      clearTimeout(reconnectTimeout)
      reconnectTimeout = null
    }
    
    if (ws) {
      ws.close()
      ws = null
    }
    
    isConnected.value = false
    isConnecting.value = false
    currentTicketId = null
  }

  /**
   * Очистить сообщения.
   */
  function clearMessages() {
    messages.value = []
  }

  // Автоматическая очистка при уничтожении компонента
  onUnmounted(() => {
    disconnect()
  })

  return {
    messages,
    isConnected,
    isConnecting,
    error,
    connect,
    disconnect,
    ping,
    clearMessages,
  }
}
