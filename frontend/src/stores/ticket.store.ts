import { defineStore } from 'pinia'
import { ref } from 'vue'
import { useQuery, useMutation } from '@vue/apollo-composable'
import { gql } from 'graphql-tag'
import type { Ticket, CreateTicketInput, TicketStatus } from '@/types'

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
  function fetchTickets() {
    const { result, loading: isLoading, error: err } = useQuery(MY_TICKETS_QUERY)

    loading.value = isLoading.value
    error.value = err.value?.message || null

    return { result, loading: isLoading, error: err }
  }

  /**
   * Create a new ticket
   */
  async function createTicket(input: CreateTicketInput): Promise<Ticket | null> {
    const { mutate } = useMutation<{ createTicket: Ticket }, { input: CreateTicketInput }>(
      CREATE_TICKET_MUTATION
    )

    try {
      const result = await mutate({ input })
      return result?.data?.createTicket || null
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to create ticket'
      throw err
    }
  }

  /**
   * Get ticket status
   */
  function getTicketStatus(ticketId: string) {
    const { result, loading: isLoading, error: err } = useQuery<{ ticketStatus: TicketStatus }, { ticketId: string }>(
      TICKET_STATUS_QUERY,
      { ticketId }
    )

    return { result, loading: isLoading, error: err }
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
