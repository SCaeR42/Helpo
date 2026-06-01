import { defineStore } from 'pinia'
import { ref } from 'vue'
import { gql } from 'graphql-tag'
import type { Ticket, CreateTicketInput, TicketStatus } from '@/types'
import { apolloClient } from '@/apollo/client'

const MY_TICKETS_QUERY = gql`
  query MyTickets {
    myTickets {
      id
      subject
      section
      statusCode
      statusName
      createdAt
      updatedAt
    }
  }
`

const CREATE_TICKET_MUTATION = gql`
  mutation CreateTicket($input: CreateTicketInput!) {
    createTicket(input: $input) {
      id
      subject
      section
      statusCode
      statusName
      createdAt
    }
  }
`

const TICKET_STATUS_QUERY = gql`
  query TicketStatus($ticketId: ID!) {
    ticketStatus(ticketId: $ticketId) {
      code
      name
      message
    }
  }
`

export const useTicketStore = defineStore('ticket', () => {
  const tickets = ref<Ticket[]>([])
  const currentTicket = ref<Ticket | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  /**
   * Fetch user's tickets
   */
  async function fetchTickets() {
    loading.value = true
    error.value = null

    try {
      const result = await apolloClient.query<{ myTickets: Ticket[] }>(MY_TICKETS_QUERY)
      tickets.value = result.data?.myTickets || []
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to fetch tickets'
    } finally {
      loading.value = false
    }
  }

  /**
   * Create a new ticket
   */
  async function createTicket(input: CreateTicketInput): Promise<Ticket | null> {
    try {
      const result = await apolloClient.mutate<{ createTicket: Ticket }, { input: CreateTicketInput }>({
        mutation: CREATE_TICKET_MUTATION,
        variables: { input },
      })
      return result?.data?.createTicket || null
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to create ticket'
      throw err
    }
  }

  /**
   * Get ticket status
   */
  async function getTicketStatus(ticketId: string): Promise<TicketStatus | null> {
    try {
      const result = await apolloClient.query<{ ticketStatus: TicketStatus }, { ticketId: string }>(
        TICKET_STATUS_QUERY,
        { ticketId }
      )
      return result.data?.ticketStatus || null
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to fetch status'
      return null
    }
  }

  return {
    tickets,
    currentTicket,
    loading,
    error,
    fetchTickets,
    createTicket,
    getTicketStatus,
  }
})
