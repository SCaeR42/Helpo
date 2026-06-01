// User types
export interface User {
  id: string
  login: string
  createdAt: string
}

// Auth types
export interface AuthPayload {
  token: string
  user: User
}

export interface LoginInput {
  login: string
  password: string
}

// Ticket types
export type TicketSection = 'GENERAL' | 'SUBSCRIPTION' | 'ACCOUNT' | 'ERROR' | 'FEATURE'

export interface Ticket {
  id: string
  userId: string
  subject: string
  section: TicketSection
  comment: string | null
  statusCode: string
  statusName: string
  createdAt: string
  updatedAt: string
}

export interface TicketStatus {
  code: string
  name: string
  message: string | null
}

export interface CreateTicketInput {
  subject: string
  section: TicketSection
  comment?: string
}

// Message types
export type SenderType = 'USER' | 'SYSTEM'

export interface Message {
  id: string
  ticketId: string
  userId: string
  senderType: SenderType
  content: string
  statusCode: string | null
  statusName: string | null
  createdAt: string
}

export interface CreateMessageInput {
  ticketId: string
  content: string
}

// Section display names
export const SECTION_LABELS: Record<TicketSection, string> = {
  GENERAL: 'Общее',
  SUBSCRIPTION: 'Подписка',
  ACCOUNT: 'Работа ЛК',
  ERROR: 'Ошибка',
  FEATURE: 'Предложить функционал',
}

// Status colors
export const STATUS_COLORS: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-800',
  processing: 'bg-blue-100 text-blue-800',
  in_progress: 'bg-purple-100 text-purple-800',
  review: 'bg-orange-100 text-orange-800',
  completed: 'bg-green-100 text-green-800',
}
