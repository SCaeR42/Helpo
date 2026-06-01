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
