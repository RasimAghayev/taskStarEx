import { defineConfig } from 'vitest/config'

// Phase 11: a plain Vitest config, deliberately NOT @nuxt/test-utils's
// heavier Nuxt-environment runner (that pulls in a full Nitro/Nuxt test
// context -- disproportionate for this app's actual testing need today:
// unit-level coverage of plain-JS service/constant modules, not mounting
// full Nuxt pages/components). `import.meta.client`/`import.meta.server`
// are Nuxt build-time macros (normally replaced by Nuxt's own Vite
// plugin); `define` below replicates just that one substitution so
// services/authService.js's client-only branches behave the same way
// under test as they do in a real browser bundle, without the rest of
// the Nuxt build pipeline.
export default defineConfig({
  define: {
    'import.meta.client': 'true',
    'import.meta.server': 'false',
  },
  test: {
    environment: 'happy-dom',
    include: ['tests/unit/**/*.test.ts'],
    coverage: {
      provider: 'v8',
      include: ['services/**', 'constants/**'],
      reporter: ['text', 'json-summary'],
    },
  },
})
