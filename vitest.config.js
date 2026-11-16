import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
  plugins: [vue()],
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./vitest.setup.js'],
    // Worktrees under .claude are whole copies of this tree, specs and
    // all. Their specs would be collected from here and resolved against
    // this checkout's "@" alias, so an older copy of a spec fails on
    // code it was never written against.
    exclude: ['**/node_modules/**', '**/dist/**', '**/.claude/**', '**/vendor/**'],
      deps: {
          optimizer: {
              web: {
                  include: ['vue', '@vue/test-utils']
              }
          }
      },
    css: false
  },
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
      '~': fileURLToPath(new URL('./resources/js', import.meta.url)),
    }
  }
});
