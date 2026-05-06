import { test, expect } from '@playwright/test';

test('home page renders the MDS welcome', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByRole('heading', { name: /Million Dollar Software/i })).toBeVisible();
});

test('login page is reachable', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByText(/Sign in/i)).toBeVisible();
});

test('register page is reachable', async ({ page }) => {
    await page.goto('/register');
    await expect(page.getByText(/Create/i).first()).toBeVisible();
});
