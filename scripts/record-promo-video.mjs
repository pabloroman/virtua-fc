#!/usr/bin/env node
/**
 * record-promo-video.mjs
 *
 * Playwright script that records a ~60s promo video touring all key screens
 * of a VirtuaFC career mode game at 2880×1620 (2x Retina).
 *
 * Prerequisites:
 *   - `composer dev` running (server + queue + vite)
 *   - A game created via `scripts/setup-promo-game.sh`
 *   - `npm install -D playwright && npx playwright install chromium`
 *
 * Usage:
 *   GAME_ID=<uuid> node scripts/record-promo-video.mjs
 *   GAME_ID=<uuid> COMPETITION_ID=<uuid> BASE_URL=http://virtuafc.test node scripts/record-promo-video.mjs
 *
 * Output:
 *   videos/virtuafc-promo.webm
 */

import { chromium } from 'playwright';
import { existsSync, mkdirSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------
const GAME_ID = process.env.GAME_ID;
const COMPETITION_ID = process.env.COMPETITION_ID || '';
const BASE_URL = process.env.BASE_URL || 'http://virtuafc.test';
const EMAIL = 'test@test.com';
const PASSWORD = 'password';

if (!GAME_ID) {
  console.error('ERROR: GAME_ID env var is required.\n');
  console.error('Usage: GAME_ID=<uuid> node scripts/record-promo-video.mjs');
  process.exit(1);
}

const __dirname = dirname(fileURLToPath(import.meta.url));
const PROJECT_ROOT = resolve(__dirname, '..');
const VIDEO_DIR = resolve(PROJECT_ROOT, 'videos');

if (!existsSync(VIDEO_DIR)) mkdirSync(VIDEO_DIR, { recursive: true });

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Smooth scroll with ease-in-out cubic interpolation */
async function smoothScroll(page, targetY, durationMs = 1200) {
  await page.evaluate(
    ({ targetY, durationMs }) => {
      return new Promise((resolve) => {
        const startY = window.scrollY;
        const distance = targetY - startY;
        const startTime = performance.now();

        function easeInOutCubic(t) {
          return t < 0.5
            ? 4 * t * t * t
            : 1 - Math.pow(-2 * t + 2, 3) / 2;
        }

        function step(now) {
          const elapsed = now - startTime;
          const progress = Math.min(elapsed / durationMs, 1);
          const eased = easeInOutCubic(progress);
          window.scrollTo(0, startY + distance * eased);

          if (progress < 1) {
            requestAnimationFrame(step);
          } else {
            resolve();
          }
        }

        requestAnimationFrame(step);
      });
    },
    { targetY, durationMs }
  );
}

/** Smooth scroll to bottom of page */
async function smoothScrollToBottom(page, durationMs = 1500) {
  const scrollHeight = await page.evaluate(() => document.body.scrollHeight);
  const viewportHeight = await page.evaluate(() => window.innerHeight);
  const target = Math.max(0, scrollHeight - viewportHeight);
  await smoothScroll(page, target, durationMs);
}

/** Smooth scroll back to top */
async function smoothScrollToTop(page, durationMs = 800) {
  await smoothScroll(page, 0, durationMs);
}

/** Wait with a visible pause */
function pause(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
console.log('🎬 VirtuaFC Promo Video Recorder');
console.log(`   Game ID: ${GAME_ID}`);
console.log(`   Base URL: ${BASE_URL}`);
console.log('');

const browser = await chromium.launch({ headless: true });

// ── Step 1: Authenticate (off-camera) ─────────────────────────────────────
console.log('1. Authenticating...');
const authContext = await browser.newContext({
  viewport: { width: 1440, height: 810 },
  locale: 'es-ES',
});
const authPage = await authContext.newPage();
await authPage.goto(`${BASE_URL}/login`);
await authPage.fill('input[name="email"]', EMAIL);
await authPage.fill('input[name="password"]', PASSWORD);
await authPage.click('button[type="submit"]');
await authPage.waitForURL('**/dashboard');
const storageState = await authContext.storageState();
await authContext.close();
console.log('   ✓ Logged in\n');

// ── Step 2: Create recorded context ───────────────────────────────────────
console.log('2. Starting recording...');
const context = await browser.newContext({
  viewport: { width: 1440, height: 810 },
  deviceScaleFactor: 2,
  locale: 'es-ES',
  storageState,
  recordVideo: {
    dir: VIDEO_DIR,
    size: { width: 2880, height: 1620 },
  },
});

// Set live match speed to 4x via localStorage before any navigation
await context.addInitScript(() => {
  localStorage.setItem('liveMatchSpeed', '4');
});

const page = await context.newPage();
console.log('   ✓ Recording context ready\n');

try {
  // ── Screen 1: Game Home (~6s) ─────────────────────────────────────────
  console.log('3. Screen: Game Home');
  await page.goto(`${BASE_URL}/game/${GAME_ID}`, { waitUntil: 'networkidle' });
  await pause(3000);
  await smoothScrollToBottom(page, 1500);
  await pause(1500);
  await smoothScrollToTop(page, 800);

  // ── Screen 3: Squad (~6s) ─────────────────────────────────────────────
  console.log('5. Screen: Squad');
  await page.goto(`${BASE_URL}/game/${GAME_ID}/squad`, { waitUntil: 'networkidle' });
  await pause(2000);
  await smoothScrollToBottom(page, 2500);
  await pause(1500);
  await smoothScrollToTop(page, 800);

  // ── Screen 4: Lineup — configure tactics & save (~10s) ──────────────
  console.log('6. Screen: Lineup');
  await page.goto(`${BASE_URL}/game/${GAME_ID}/lineup`, { waitUntil: 'networkidle' });
  await pause(1500);

  // Select 4-3-3 formation
  console.log('   Selecting 4-3-3 formation...');
  const formationBtn = page.locator('.formation-option', { hasText: '4-3-3' });
  await formationBtn.click();
  await pause(800);

  // Select offensive mentality via Alpine.js data
  console.log('   Selecting offensive mentality...');
  await page.evaluate(() => {
    const el = document.querySelector('[x-data]');
    if (el && el._x_dataStack) {
      el._x_dataStack[0].selectedMentality = 'attacking';
    }
  });
  await pause(800);

  // Click auto-select button
  console.log('   Auto-selecting lineup...');
  const autoSelectBtn = page.locator('button', { hasText: /auto/i });
  await autoSelectBtn.click();
  await pause(1500);

  // Click save/confirm button
  console.log('   Saving lineup...');
  const saveBtn = page.locator('button[type="submit"]', { hasText: /confirmar|confirm/i });
  await saveBtn.click();
  await page.waitForLoadState('networkidle');
  await pause(1500);

  // ── Screen 5: Play Match ──────────────────────────────────────────────
  console.log('7. Screen: Playing match (advance)');
  // Navigate to game home
  await page.goto(`${BASE_URL}/game/${GAME_ID}`, { waitUntil: 'networkidle' });
  await pause(500);

  // Click the advance button in the desktop header
  // The form posts to the advance route
  const advanceForm = page.locator(`form[action*="/game/${GAME_ID}/advance"]`).first();
  await advanceForm.locator('button').click();

  // Wait for redirect to live match page
  await page.waitForURL(`**/game/${GAME_ID}/live/**`, { timeout: 30000 });
  await page.waitForLoadState('networkidle');

  // ── Screen 6: Live Match (~17s) ───────────────────────────────────────
  console.log('8. Screen: Live Match');

  // Speed should already be 4x from localStorage init script
  // Wait for the match to reach full_time
  // At 4x speed, a match takes ~15s
  await pause(2000); // Let the match start and show initial state

  // Wait for full_time phase (timeout after 60s to be safe)
  console.log('   Waiting for full time...');
  await page.waitForFunction(
    () => {
      // The Alpine component sets phase on the x-data element
      const el = document.querySelector('[x-data]');
      if (!el || !el._x_dataStack) return false;
      return el._x_dataStack[0].phase === 'full_time';
    },
    { timeout: 60000 }
  );
  console.log('   ✓ Match complete');
  await pause(2000); // Brief pause to show the final score

  // Click stats tab
  console.log('   Viewing stats...');
  const statsTab = page.locator('button', { hasText: /estad[íi]sticas|stats/i });
  await statsTab.click();
  await pause(3000);

  // Click results tab
  console.log('   Viewing results...');
  const resultsTab = page.locator('button', { hasText: /resultados|results/i });
  await resultsTab.click();
  await pause(3000);

  // ── Screen 7: Finalize Match (~4s) ────────────────────────────────────
  console.log('9. Screen: Finalizing match');

  // Wait for processingReady to become true (queue worker needs to finish)
  await page.waitForFunction(
    () => {
      const el = document.querySelector('[x-data]');
      if (!el || !el._x_dataStack) return false;
      return el._x_dataStack[0].processingReady === true;
    },
    { timeout: 30000 }
  );

  // Click the finalize button
  const finalizeForm = page.locator(`form[action*="/finalize-match"]`);
  await finalizeForm.locator('button').click();
  await page.waitForLoadState('networkidle');
  await pause(1000);

  // ── Screen 8: Standings (~5s) ─────────────────────────────────────────
  console.log('10. Screen: Standings');
  if (COMPETITION_ID) {
    await page.goto(`${BASE_URL}/game/${GAME_ID}/competition/${COMPETITION_ID}`, { waitUntil: 'networkidle' });
  } else {
    // Try to find the La Liga link in the nav
    const compLink = page.locator('a[href*="/competition/"]').first();
    await compLink.click();
    await page.waitForLoadState('networkidle');
  }
  await pause(2000);
  await smoothScrollToBottom(page, 1500);
  await pause(1500);
  await smoothScrollToTop(page, 800);

  console.log('\n✓ All screens recorded!\n');
} catch (err) {
  console.error('\n✗ Recording failed:', err.message);
  console.error(err.stack);
}

// ── Teardown & save video ─────────────────────────────────────────────────
const outputPath = resolve(VIDEO_DIR, 'virtuafc-promo.webm');
const video = page.video();
await page.close(); // Triggers video finalization
if (video) {
  await video.saveAs(outputPath);
  // Delete the temp file Playwright created
  try {
    const tempPath = await video.path();
    if (tempPath !== outputPath && existsSync(tempPath)) {
      (await import('fs')).unlinkSync(tempPath);
    }
  } catch { /* temp file may already be cleaned up */ }
}
await context.close();
await browser.close();

console.log(`\n📹 Video saved: ${outputPath}`);
console.log('');
console.log('Optional: Convert to MP4 (visually lossless) with:');
console.log(
  '  ffmpeg -i videos/virtuafc-promo.webm -c:v libx264 -preset veryslow -crf 0 -pix_fmt yuv444p videos/virtuafc-promo.mp4'
);
console.log('');
console.log('Or for a smaller file with near-lossless quality:');
console.log(
  '  ffmpeg -i videos/virtuafc-promo.webm -c:v libx264 -preset veryslow -crf 12 -pix_fmt yuv444p videos/virtuafc-promo.mp4'
);
