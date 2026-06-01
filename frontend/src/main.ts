import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { DefaultApolloClient } from '@vue/apollo-composable'
import { ApolloClient, InMemoryCache, createHttpLink } from '@apollo/client/core'
import { setContext } from '@apollo/client/link/context'

import App from './App.vue'
import router from './router'

// GraphQL HTTP link
const httpLink = createHttpLink({
  uri: '/api/graphql',
})

// Auth link for JWT
const authLink = setContext((_, { headers }) => {
  const token = localStorage.getItem('jwt_token')
  return {
    headers: {
      ...headers,
      authorization: token ? `Bearer ${token}` : '',
    }
  }
})

// Apollo Client instance
const apolloClient = new ApolloClient({
  link: authLink.concat(httpLink),
  cache: new InMemoryCache(),
})

// Create Vue app
const app = createApp(App)

// Provide Apollo Client
app.provide(DefaultApolloClient, apolloClient)

// Use plugins
app.use(createPinia())
app.use(router)

// Mount
app.mount('#app')
