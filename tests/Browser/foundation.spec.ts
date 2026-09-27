import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { existsSync, writeFileSync, unlinkSync } from 'node:fs';
import { resolve } from 'node:path';

const fixturePath = resolve('storage/app/private/browser-fixture.json');
const fixture = {
    name: 'Guru Uji Browser',
    email: `browser-${randomBytes(8).toString('hex')}@example.test`,
    password: randomBytes(36).toString('base64url'),
};
let created = false;

function artisan(code: string) {
    return execFileSync('lerd', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' });
}

test.beforeAll(() => {
    // Tidak reset/seed database. Menolak instalasi yang sudah memiliki pemilik.
    artisan("if (App\\Models\\User::exists()) { throw new RuntimeException('Browser QA refused: owner exists'); }");
    if (existsSync(fixturePath)) throw new Error('Fixture lama masih ada; periksa sebelum QA.');
    writeFileSync(fixturePath, JSON.stringify(fixture), { mode: 0o600, flag: 'wx' });
    try {
        artisan("if (App\\Models\\User::exists()) { throw new RuntimeException('Owner exists'); } $data=json_decode(file_get_contents(storage_path('app/private/browser-fixture.json')),true); App\\Models\\User::create($data); echo 'Browser fixture ready';");
        created = true;
    } catch (error) {
        unlinkSync(fixturePath);
        throw error;
    }
});

test.afterAll(() => {
    if (!created) return;
    artisan("$path=storage_path('app/private/browser-fixture.json'); $data=json_decode(file_get_contents($path),true); $user=App\\Models\\User::where('email',$data['email'])->firstOrFail(); if (!Hash::check($data['password'],$user->password)) { throw new RuntimeException('Fixture mismatch'); } DB::transaction(function () use ($user) { $groupIds=DB::table('study_groups')->where('owner_id',$user->id)->pluck('id'); DB::table('group_memberships')->whereIn('group_id',$groupIds)->delete(); DB::table('study_groups')->where('owner_id',$user->id)->delete(); DB::table('activity_types')->where('owner_id',$user->id)->delete(); DB::table('students')->where('owner_id',$user->id)->delete(); DB::table('sessions')->where('user_id',$user->id)->delete(); $user->delete(); }); unlink($path); echo 'Browser fixture removed';");
});

test('login, responsive navigation, logout, and request protection on actual Lerd domain', async ({ page, request }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));

    for (const width of [1440, 768, 360]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login$/);
        await expect(page.getByRole('heading', { name: 'Masuk ke ruang Anda' })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await page.getByLabel('Email', { exact: true }).fill(fixture.email);
        await page.getByLabel('Kata sandi', { exact: true }).fill('invalid');
        await page.getByRole('button', { name: 'Masuk', exact: true }).click();
        await expect(page.getByRole('alert')).toHaveText('Email atau kata sandi tidak sesuai.');
        await expect(page.getByRole('alert')).toBeFocused();
        await expect(page.getByLabel('Kata sandi', { exact: true })).toHaveValue('');
        await page.getByLabel('Kata sandi', { exact: true }).fill(fixture.password);
        await page.getByRole('button', { name: 'Masuk', exact: true }).click();
        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.getByRole('heading', { name: 'Assalamu’alaikum, Guru Uji Browser.' })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await page.reload();
        await expect(page.getByRole('heading', { name: 'Akun pemilik' })).toBeVisible();

        if (width === 360) {
            const toggle = page.getByRole('button', { name: 'Buka menu' });
            await toggle.focus();
            await page.keyboard.press('Enter');
            await expect(page.getByRole('navigation')).toBeVisible();
            await expect(page.getByRole('button', { name: 'Tutup menu' })).toHaveAttribute('aria-expanded', 'true');
            await page.getByRole('link', { name: 'Ringkasan', exact: true }).click();
            await expect(page.getByRole('navigation')).not.toBeVisible();
        }
        const cookie = (await page.context().cookies()).find((item) => item.name.endsWith('-session'));
        expect(cookie?.httpOnly).toBe(true);
        expect(cookie?.secure).toBe(true);
        expect(cookie?.sameSite).toBe('Lax');
        await page.screenshot({ path: `test-results/dashboard-${width}.png`, fullPage: true });
        await page.getByRole('button', { name: 'Keluar' }).click();
        await expect(page).toHaveURL(/\/login$/);
        await page.goBack();
        await expect(page).toHaveURL(/\/login$/);
        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login$/);
    }

    const csrf = await request.post('/login', {
        headers: { Origin: 'https://foreign.invalid', 'Sec-Fetch-Site': 'cross-site' },
        form: { email: fixture.email, password: 'invalid' },
        maxRedirects: 0,
    });
    expect(csrf.status()).toBe(419);
    expect((await request.get('/register')).status()).toBe(404);
    expect(errors).toEqual([]);
});

test('active Quran reference renders, navigates surahs and fits mobile', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill(fixture.email);
    await page.getByLabel('Kata sandi', { exact: true }).fill(fixture.password);
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await page.getByRole('link', { name: 'Al-Qur’an', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Al-Qur’an' })).toBeVisible();
    await expect(page.getByText('Tanzil Project', { exact: false })).toBeVisible();
    await page.getByLabel('Surah').selectOption('18');
    await expect(page.getByRole('heading', { name: 'الكهف' })).toBeVisible();
    await expect(page.getByLabel('Ayat 1')).toBeVisible();
    await page.getByRole('button', { name: 'Perbesar teks' }).click();
    await expect(page.getByText('110%')).toBeVisible();
    await page.setViewportSize({ width: 360, height: 800 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: 'test-results/quran-reader-mobile.png', fullPage: true });
    expect(errors).toEqual([]);
});

test('owner manages students, optional group, activity and archive in browser', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill(fixture.email);
    await page.getByLabel('Kata sandi', { exact: true }).fill(fixture.password);
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await page.getByRole('link', { name: 'Santri dan kegiatan' }).click();
    await expect(page.getByRole('heading', { name: 'Santri dan pengaturan' })).toBeVisible();
    await page.getByLabel('Kode', { exact: true }).fill('BR-01');
    await page.getByLabel('Nama', { exact: true }).fill('Santri Browser');
    await page.getByRole('button', { name: 'Tambah santri' }).click();
    await expect(page.getByText('Santri Browser', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Kelompok opsional', exact: true }).click();
    await page.getByLabel('Nama kelompok').fill('Kelompok Browser');
    await page.getByRole('checkbox', { name: 'Santri Browser · BR-01' }).check();
    await page.getByRole('button', { name: 'Tambah kelompok' }).click();
    await expect(page.getByText('1 anggota')).toBeVisible();
    await page.getByRole('button', { name: 'Jenis kegiatan', exact: true }).click();
    await page.getByLabel('Nama kegiatan').fill('Setoran Browser');
    await page.getByRole('button', { name: 'Tambah kegiatan' }).click();
    await expect(page.getByText('Setoran Browser')).toBeVisible();
    await page.getByRole('button', { name: 'Santri', exact: true }).click();
    await page.getByRole('button', { name: 'Arsipkan' }).first().click();
    await page.getByRole('link', { name: 'Lihat arsip' }).click();
    await expect(page.getByText('Santri Browser', { exact: true })).toBeVisible();
    await page.setViewportSize({ width: 360, height: 800 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: 'test-results/students-mobile.png', fullPage: true });
    expect(errors).toEqual([]);
});
