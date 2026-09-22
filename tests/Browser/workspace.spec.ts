import { expect, test } from '@playwright/test';

// Read-only QA with an existing test account; never creates or resets users.
test.skip(!process.env.QA_EMAIL || !process.env.QA_PASSWORD, 'Provide an existing test account through QA_EMAIL and QA_PASSWORD.');

test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill(process.env.QA_EMAIL!);
    await page.getByLabel('Kata sandi', { exact: true }).fill(process.env.QA_PASSWORD!);
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
});

test('workspace fits desktop, tablet and phone, with accessible mobile navigation', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    for (const width of [1440, 768, 375]) {
        await page.setViewportSize({ width, height: 960 });
        for (const route of ['/dashboard', '/students', '/rubrics', '/programs', '/assessments', '/reports', '/backups', '/mushaf/prototype']) {
            await page.goto(route);
            await expect(page.locator('main h1')).toBeVisible();
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${route} at ${width}px`).toBe(true);
            if (width > 760) await expect(page.locator('#main-nav [aria-current="page"]')).toHaveCount(1);
        }
    }
    await page.getByRole('button', { name: 'Buka menu', exact: true }).click();
    await page.locator('#main-nav').getByRole('link', { name: 'Santri dan kegiatan', exact: true }).focus();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('button', { name: 'Buka menu', exact: true })).toBeFocused();
    await page.keyboard.press('Enter');
    await page.locator('#main-nav').getByRole('link', { name: 'Santri dan kegiatan', exact: true }).click();
    await expect(page).toHaveURL(/\/students$/);
    await expect(page.locator('#main-nav')).toBeHidden();
    await page.emulateMedia({ reducedMotion: 'reduce' });
    expect(await page.locator('main').evaluate((el) => getComputedStyle(el).animationName)).toBe('none');
    await page.getByRole('button', { name: 'Keluar', exact: true }).click();
    await expect(page).toHaveURL(/\/login$/);
    expect(errors).toEqual([]);
});

test('switching input sections retains unfinished forms without saving them', async ({ page }) => {
    await page.goto('/students');
    await page.getByLabel('Kode', { exact: true }).fill('UNSAVED-QA');
    await page.getByRole('button', { name: 'Jenis kegiatan', exact: true }).click();
    await expect(page.getByLabel('Kode', { exact: true })).toBeHidden();
    await page.getByLabel('Nama kegiatan', { exact: true }).fill('Belum disimpan');
    await page.getByRole('button', { name: 'Kelompok opsional', exact: true }).click();
    await expect(page.getByLabel('Nama kelompok', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Jenis kegiatan', exact: true }).click();
    await expect(page.getByLabel('Nama kegiatan', { exact: true })).toHaveValue('Belum disimpan');
    await page.getByRole('button', { name: 'Santri', exact: true }).click();
    await expect(page.getByLabel('Kode', { exact: true })).toHaveValue('UNSAVED-QA');
});
