import { resolve } from 'node:path';
import { defineConfig } from 'vitest/config';

export default defineConfig({
  resolve: {
    alias: {
      '@': resolve(__dirname, './resources/js'),
    },
  },
  test: {
    environment: 'node',
    include: [
      'resources/js/**/*.test.ts',
      'resources/js/**/*.test.tsx',
      'scripts/**/*.test.mjs',
    ],
    clearMocks: true,
  },
});
