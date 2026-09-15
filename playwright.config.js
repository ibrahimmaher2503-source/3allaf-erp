import { defineConfig, devices } from '@playwright/test';

/**
 * Critical-path browser E2E configuration. Chromium-only for the routine
 * suite per the QA audit's "broad Chromium coverage plus a smaller critical
 * cross-browser suite" guidance — Firefox/WebKit projects can be added once
 * the routine suite is stable.
 *
 * baseURL points at a dedicated local server + MySQL/MariaDB database seeded
 * specifically for this suite (see testing/e2e/README.md); it must never
 * point at a developer's real local database or any staging/production URL.
 */
export default defineConfig({
    testDir: './testing/e2e',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: 0,
    workers: 1,
    reporter: [['html', { open: 'never', outputFolder: 'testing/e2e/report' }], ['list']],
    outputDir: 'testing/e2e/results',
    webServer: process.env.PLAYWRIGHT_START_SERVER === '1' ? {
        command: `php artisan serve --host=127.0.0.1 --port=${process.env.PLAYWRIGHT_SERVER_PORT || '8820'}`,
        url: process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8820',
        reuseExistingServer: false,
        timeout: 120_000,
    } : undefined,
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8791',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'off',
        // WebKit on the local PHP built-in server can take longer on the
        // first authenticated navigation after a fresh disposable schema.
        actionTimeout: 30_000,
        navigationTimeout: 30_000,
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'firefox',
            use: { ...devices['Desktop Firefox'] },
        },
        {
            name: 'webkit',
            use: { ...devices['Desktop Safari'] },
        },
    ],
});
