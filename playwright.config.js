import { defineConfig, devices } from '@playwright/test'

const binary = process.env.CHROMIUM_PATH
export default defineConfig({
    testDir: 'tests/browser',
    fullyParallel: false,
    timeout: 60000,
    use: { baseURL: 'http://127.0.0.1:8765', trace: 'retain-on-failure' },
    projects: [
        { name: 'desktop Chromium', use: { ...devices['Desktop Chrome'], launchOptions: { executablePath: binary } } },
        { name: 'mobile Chromium', use: { ...devices['Pixel 7'], launchOptions: { executablePath: binary } } },
    ],
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8765 --no-reload',
        url: 'http://127.0.0.1:8765/up',
        env: {
            APP_URL: 'http://127.0.0.1:8765',
            APP_ENV: 'local',
            SESSION_SECURE_COOKIE: 'false',
            CACHE_STORE: 'array',
        },
    },
})
