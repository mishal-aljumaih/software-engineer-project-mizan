const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests',

  use: {
    baseURL: 'http://localhost/mizan',
    headless: true,
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },

  reporter: [['html', { open: 'never' }], ['json', { outputFile: 'test-results.json' }]],
});