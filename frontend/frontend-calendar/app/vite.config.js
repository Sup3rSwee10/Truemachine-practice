import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Базовый URL API берётся из переменной окружения VITE_API_BASE_URL (см. .env.example)
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
  },
});
