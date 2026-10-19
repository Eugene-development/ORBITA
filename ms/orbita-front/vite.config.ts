import { defineConfig } from 'vite';
import { sveltekit } from '@sveltejs/kit/vite';
import adapter from '@sveltejs/adapter-node';
import tailwindcss from '@tailwindcss/vite';

const target = process.env.ORBITA_API_PROXY ?? 'http://127.0.0.1:8000';
const proxy = Object.fromEntries(['/graphql', '/api', '/auth', '/media'].map((path) => [path, { target, changeOrigin: false }]));
proxy['/admin'] = { target: process.env.ORBITA_ADMIN_PROXY ?? 'http://127.0.0.1:4152', changeOrigin: false };
export default defineConfig({
  plugins: [tailwindcss(), sveltekit({ adapter: adapter(), compilerOptions: { runes: true } })],
  server: { host: '127.0.0.1', port: 4151, proxy },
  preview: { host: '0.0.0.0', port: 4173, proxy }
});
