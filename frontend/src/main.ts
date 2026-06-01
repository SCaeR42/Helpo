import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { DefaultApolloClient } from '@vue/apollo-composable'

import App from './App.vue'
import router from './router'
import { apolloClient } from './apollo/client'
import './style.css'

// Create Vue app
const app = createApp(App)

// Provide Apollo Client
app.provide(DefaultApolloClient, apolloClient)

// Use plugins
app.use(createPinia())
app.use(router)

// Mount
app.mount('#app')
