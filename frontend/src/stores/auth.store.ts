import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { gql } from 'graphql-tag'
import type { User, AuthPayload, LoginInput } from '@/types'
import { apolloClient } from '@/apollo/client'
import router from '@/router'

const LOGIN_MUTATION = gql`
  mutation Login($input: LoginInput!) {
    login(input: $input) {
      token
      user {
        id
        login
        createdAt
      }
    }
  }
`

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(null)
  const isInitialized = ref(false)

  const isAuthenticated = computed(() => !!token.value)

  /**
   * Initialize auth state from localStorage
   */
  async function init() {
    if (isInitialized.value) return

    const storedToken = localStorage.getItem('jwt_token')
    const storedUser = localStorage.getItem('jwt_user')

    if (storedToken && storedUser) {
      token.value = storedToken
      user.value = JSON.parse(storedUser)
    }

    isInitialized.value = true
  }

  /**
   * Login user with credentials
   */
  async function login(input: LoginInput): Promise<AuthPayload> {
    try {
      const result = await apolloClient.mutate<AuthPayload, { input: LoginInput }>({
        mutation: LOGIN_MUTATION,
        variables: { input },
      })

      if (!result?.data?.login) {
        throw new Error('Login failed: no data returned')
      }

      const { token: newToken, user: newUser } = result.data.login

      // Save to state
      token.value = newToken
      user.value = newUser

      // Save to localStorage
      localStorage.setItem('jwt_token', newToken)
      localStorage.setItem('jwt_user', JSON.stringify(newUser))

      return result.data.login
    } catch (error) {
      console.error('Login error:', error)
      throw error
    }
  }

  /**
   * Logout user
   */
  function logout() {
    token.value = null
    user.value = null
    localStorage.removeItem('jwt_token')
    localStorage.removeItem('jwt_user')
    router.push({ name: 'Login' })
  }

  return {
    user,
    token,
    isInitialized,
    isAuthenticated,
    init,
    login,
    logout,
  }
})
