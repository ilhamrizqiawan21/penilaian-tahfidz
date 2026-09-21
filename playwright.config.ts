import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    workers: 1,
    retries: 0,
    timeout: 60_000,
    use: {
        channel: 'chrome',
        baseURL: 'https://penilaian-tahfidz.test',
        ignoreHTTPSErrors: true, // CA lokal mkcert, hanya domain Lerd di atas.
        trace: 'off', // Hindari merekam kredensial form.
    },
});
