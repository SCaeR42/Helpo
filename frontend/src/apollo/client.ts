import { ApolloClient, InMemoryCache, createHttpLink } from '@apollo/client/core'
import { setContext } from '@apollo/client/link/context'

// GraphQL HTTP link - use full URL for API server
const httpLink = createHttpLink({
  uri: 'http://localhost:8000/api/graphql',
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
export const apolloClient = new ApolloClient({
  link: authLink.concat(httpLink),
  cache: new InMemoryCache(),
})
