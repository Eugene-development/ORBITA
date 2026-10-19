import { defineConfig } from 'vite';
import { sveltekit } from '@sveltejs/kit/vite';
import adapter from '@sveltejs/adapter-node';
import tailwindcss from '@tailwindcss/vite';
const proxy = Object.fromEntries(['/auth', '/graphql', '/api'].map(path => [path, { target: process.env.ORBITA_API_PROXY || 'http://127.0.0.1:8000', changeOrigin: false }]));
export default defineConfig({ plugins: [tailwindcss(), sveltekit({ adapter: adapter(), paths: { base: '/admin' } })], server: { proxy }, preview: { proxy } });
