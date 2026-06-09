import {defineConfig} from 'vite'
import vue from '@vitejs/plugin-vue'
// import { fileURLToPath, URL } from 'node:url'
import tailwindcss from '@tailwindcss/vite'
import {resolve} from 'path'

// https://vitejs.dev/config/
export default defineConfig({
    plugins: [
        vue(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': resolve(__dirname, 'src'),
            // Актуальный способ задания алиаса @ для папки src
            // '@': fileURLToPath(new URL('./src', import.meta.url))
        },
    },
    server: {
        host: "::",
        port: 5173,
        watch: {
            // Использовать опрос вместо системных событий
            usePolling: true,
            // Интервал опроса в мс (опционально)
            interval: 100
        },
        proxy: {
            '/api': {
                target: 'http://localhost:8000',
                changeOrigin: true,
            },
        },
    },
})
