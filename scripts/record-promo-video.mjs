#!/usr/bin/env node
/**
 * record-promo-video.mjs
 *
 * Playwright script that records a ~60s promo video touring all key screens
 * of a VirtuaFC career mode game at 1440×810 (16:9).
 *
 * Captures lossless PNG screenshots in a continuous loop, then stitches
 * them with ffmpeg using the actual capture rate for real-time playback.
 *
 * Prerequisites:
 *   - `composer dev` running (server + queue + vite)
 *   - A game created via `scripts/setup-promo-game.sh`
 *   - `npm install -D playwright && npx playwright install chromium`
 *   - `ffmpeg` installed
 *
 * Usage:
 *   GAME_ID=<uuid> node scripts/record-promo-video.mjs
 *   GAME_ID=<uuid> COMPETITION_ID=<uuid> BASE_URL=http://virtuafc.test node scripts/record-promo-video.mjs
 *
 * Output:
 *   videos/virtuafc-promo.mp4
 */

import { chromium } from 'playwright';
import { existsSync, mkdirSync, readdirSync, unlinkSync, rmdirSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';
import { execSync } from 'child_process';

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
const FRAMES_DIR = resolve(VIDEO_DIR, 'frames');

if (!existsSync(VIDEO_DIR)) mkdirSync(VIDEO_DIR, { recursive: true });
if (existsSync(FRAMES_DIR)) {
  for (const f of readdirSync(FRAMES_DIR)) unlinkSync(resolve(FRAMES_DIR, f));
} else {
  mkdirSync(FRAMES_DIR, { recursive: true });
}

// ---------------------------------------------------------------------------
// Screenshot capture engine
// ---------------------------------------------------------------------------
let frameCount = 0;
let capturing = false;
let captureStartTime = 0;

function startCapture(page) {
  if (capturing) return;
  capturing = true;
  captureStartTime = Date.now();

  (async () => {
    while (capturing) {
      try {
        const framePath = resolve(FRAMES_DIR, `frame-${String(frameCount).padStart(6, '0')}.png`);
        await page.screenshot({ path: framePath, type: 'png' });
        frameCount++;
      } catch {
        // Page might be navigating, skip this frame
      }
    }
  })();
}

function stopCapture() {
  capturing = false;
}

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

// ── Step 2: Create browser context ────────────────────────────────────────
console.log('2. Setting up browser...');
const context = await browser.newContext({
  viewport: { width: 1440, height: 810 },
  deviceScaleFactor: 2,
  locale: 'es-ES',
  storageState,
});

// Set live match speed to 4x via localStorage before any navigation
await context.addInitScript(() => {
  localStorage.setItem('liveMatchSpeed', '4');
});

const page = await context.newPage();
console.log('   ✓ Browser ready\n');

// Start capturing frames
startCapture(page);

try {
  // ── Screen 1: Game Home (~4s) ─────────────────────────────────────────
  console.log('3. Screen: Game Home');
  await page.goto(`${BASE_URL}/game/${GAME_ID}`, { waitUntil: 'networkidle' });
  await pause(2000);
  await smoothScrollToBottom(page, 1000);
  await pause(500);
  await smoothScrollToTop(page, 500);

  // ── Screen 2: Squad (~4s) ─────────────────────────────────────────────
  console.log('4. Screen: Squad');
  await page.goto(`${BASE_URL}/game/${GAME_ID}/squad`, { waitUntil: 'networkidle' });
  await pause(1000);
  await smoothScrollToBottom(page, 1500);
  await pause(500);
  await smoothScrollToTop(page, 500);

  // ── Screen 3: Lineup — configure tactics (~5s) ────────────────────────
  console.log('5. Screen: Lineup');
  await page.goto(`${BASE_URL}/game/${GAME_ID}/lineup`, { waitUntil: 'networkidle' });
  await pause(1000);

  // Select 4-3-3 formation
  console.log('   Selecting 4-3-3 formation...');
  const formationBtn = page.locator('.formation-option', { hasText: '4-3-3' });
  await formationBtn.click();
  await pause(500);

  // Select offensive mentality via Alpine.js data
  console.log('   Selecting offensive mentality...');
  await page.evaluate(() => {
    const el = document.querySelector('[x-data]');
    if (el && el._x_dataStack) {
      el._x_dataStack[0].selectedMentality = 'attacking';
    }
  });
  await pause(500);

  // Click auto-select button
  console.log('   Auto-selecting lineup...');
  const autoSelectBtn = page.locator('button', { hasText: /auto/i });
  await autoSelectBtn.click();
  await pause(1500);

  // ── Screen 4: Play Match ──────────────────────────────────────────────
  console.log('6. Screen: Playing match (advance)');
  await page.goto(`${BASE_URL}/game/${GAME_ID}`, { waitUntil: 'networkidle' });
  await pause(500);

  const advanceForm = page.locator(`form[action*="/game/${GAME_ID}/advance"]`).first();
  await advanceForm.locator('button').click();

  await page.waitForURL(`**/game/${GAME_ID}/live/**`, { timeout: 30000 });
  await page.waitForLoadState('networkidle');

  // ── Screen 5: Live Match (~12s) — watch 10s then skip ──────────────────
  console.log('7. Screen: Live Match');
  await pause(10000);

  // Skip to end (Escape key triggers skipToEnd())
  console.log('   Skipping to full time...');
  await page.keyboard.press('Escape');
  await pause(1500);

  // Click stats tab
  console.log('   Viewing stats...');
  const statsTab = page.locator('button', { hasText: /estad[íi]sticas|stats/i });
  await statsTab.click();
  await pause(2000);

  // Click results tab
  console.log('   Viewing results...');
  const resultsTab = page.locator('button', { hasText: /resultados|results/i });
  await resultsTab.click();
  await pause(2000);

  // ── Screen 6: Finalize Match (~3s) ────────────────────────────────────
  console.log('8. Screen: Finalizing match');

  await page.waitForFunction(
    () => {
      const el = document.querySelector('[x-data]');
      if (!el || !el._x_dataStack) return false;
      return el._x_dataStack[0].processingReady === true;
    },
    { timeout: 30000 }
  );

  const finalizeForm = page.locator(`form[action*="/finalize-match"]`);
  await finalizeForm.locator('button').click();
  await page.waitForLoadState('networkidle');
  await pause(1000);

  // ── Screen 7: Standings (~3s) ─────────────────────────────────────────
  console.log('9. Screen: Standings');
  if (COMPETITION_ID) {
    await page.goto(`${BASE_URL}/game/${GAME_ID}/competition/${COMPETITION_ID}`, { waitUntil: 'networkidle' });
  } else {
    const compLink = page.locator('a[href*="/competition/"]').first();
    await compLink.click();
    await page.waitForLoadState('networkidle');
  }
  await pause(1000);
  await smoothScrollToBottom(page, 1000);
  await pause(500);
  await smoothScrollToTop(page, 500);

  console.log('\n✓ All screens captured!\n');
} catch (err) {
  console.error('\n✗ Recording failed:', err.message);
  console.error(err.stack);
}

// ── Stop capture & stitch video ───────────────────────────────────────────
stopCapture();
await pause(500);
await page.close();
await context.close();
await browser.close();

const elapsedSecs = (Date.now() - captureStartTime) / 1000;
const actualFps = (frameCount / elapsedSecs).toFixed(2);
console.log(`   Captured ${frameCount} frames in ${elapsedSecs.toFixed(1)}s (~${actualFps} fps)\n`);

// Stitch frames into MP4 with ffmpeg
// Use actual capture rate as input framerate so playback matches real time,
// then interpolate to 30fps output for smooth playback
const outputPath = resolve(VIDEO_DIR, 'virtuafc-promo.mp4');
console.log('3. Stitching video with ffmpeg...');
try {
  execSync(
    `ffmpeg -y -framerate ${actualFps} -i "${FRAMES_DIR}/frame-%06d.png" ` +
    `-vf "minterpolate=fps=30:mi_mode=blend,scale=1440:810:flags=lanczos" ` +
    `-c:v libx264 -preset slow -crf 18 -pix_fmt yuv420p ` +
    `"${outputPath}"`,
    { stdio: 'inherit', timeout: 600000 }
  );
  console.log(`\n📹 Video saved: ${outputPath}`);
} catch (err) {
  console.error('\n✗ ffmpeg failed:', err.message);
  console.error('Frames are preserved in:', FRAMES_DIR);
  process.exit(1);
}

// Clean up frames
console.log('   Cleaning up frames...');
for (const f of readdirSync(FRAMES_DIR)) unlinkSync(resolve(FRAMES_DIR, f));
rmdirSync(FRAMES_DIR);
console.log('   ✓ Done\n');
