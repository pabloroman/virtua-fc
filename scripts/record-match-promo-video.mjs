#!/usr/bin/env node
/**
 * record-match-promo-video.mjs
 *
 * Playwright script that records a ~20s promo video focused on the live match
 * experience and tactical center of a VirtuaFC career mode game at 1440×810 (16:9).
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
 *   GAME_ID=<uuid> node scripts/record-match-promo-video.mjs
 *   GAME_ID=<uuid> BASE_URL=http://virtuafc.test node scripts/record-match-promo-video.mjs
 *
 * Output:
 *   videos/virtuafc-match-promo.mp4
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
const BASE_URL = process.env.BASE_URL || 'http://virtuafc.test';
const EMAIL = 'test@test.com';
const PASSWORD = 'password';

if (!GAME_ID) {
  console.error('ERROR: GAME_ID env var is required.\n');
  console.error('Usage: GAME_ID=<uuid> node scripts/record-match-promo-video.mjs');
  process.exit(1);
}

const __dirname = dirname(fileURLToPath(import.meta.url));
const PROJECT_ROOT = resolve(__dirname, '..');
const VIDEO_DIR = resolve(PROJECT_ROOT, 'videos');
const FRAMES_DIR = resolve(VIDEO_DIR, 'frames-match');

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
console.log('🎬 VirtuaFC Match Promo Video Recorder');
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

try {
  // ── Scene 1: Start Match (~1s) ────────────────────────────────────────
  console.log('3. Scene: Starting match');
  await page.goto(`${BASE_URL}/game/${GAME_ID}`, { waitUntil: 'networkidle' });
  await pause(300);

  const advanceForm = page.locator(`form[action*="/game/${GAME_ID}/advance"]`).first();
  await advanceForm.locator('button').click();

  await page.waitForURL(`**/game/${GAME_ID}/live/**`, { timeout: 30000 });
  await page.waitForLoadState('networkidle');

  // Start capturing frames once the live match is loaded
  startCapture(page);

  // ── Scene 2: Live Match Action (~6s) ──────────────────────────────────
  console.log('4. Scene: Live Match (watching at 4x speed)');
  await pause(6000);

  // ── Scene 3: Tactical Center — Tactics Tab (~4s) ──────────────────────
  console.log('5. Scene: Tactical Center — Tactics');

  // Open tactical panel on the tactics tab
  await page.evaluate(() => {
    const el = document.querySelector('[x-data]');
    if (el && el._x_dataStack) {
      el._x_dataStack[0].openTacticalPanel('tactics');
    }
  });
  await pause(800);

  // Change formation to 4-2-3-1
  console.log('   Changing formation to 4-2-3-1...');
  await page.evaluate(() => {
    const el = document.querySelector('[x-data]');
    if (el && el._x_dataStack) {
      el._x_dataStack[0].pendingFormation = '4-2-3-1';
    }
  });
  await pause(800);

  // Change mentality to attacking
  console.log('   Switching mentality to attacking...');
  await page.evaluate(() => {
    const el = document.querySelector('[x-data]');
    if (el && el._x_dataStack) {
      el._x_dataStack[0].pendingMentality = 'attacking';
    }
  });
  await pause(800);

  // Hold on tactics view
  await pause(600);

  // ── Scene 4: Tactical Center — Substitutions Tab (~3s) ────────────────
  console.log('6. Scene: Tactical Center — Substitutions');

  // Switch to substitutions tab
  await page.evaluate(() => {
    const el = document.querySelector('[x-data]');
    if (el && el._x_dataStack) {
      el._x_dataStack[0].tacticalTab = 'substitutions';
    }
  });
  await pause(500);

  // Select a forward from the lineup as player out
  console.log('   Selecting forward out...');
  await page.evaluate(() => {
    const el = document.querySelector('[x-data]');
    if (!el || !el._x_dataStack) return;
    const data = el._x_dataStack[0];
    const forward = data.availableLineupForPicker.find(p => p.positionGroup === 'Forward');
    if (forward) {
      data.selectedPlayerOut = forward;
      data.livePitchSelectedOutId = forward.id;
    }
  });
  await pause(500);

  // Select a forward from the bench as player in
  console.log('   Selecting forward in...');
  await page.evaluate(() => {
    const el = document.querySelector('[x-data]');
    if (!el || !el._x_dataStack) return;
    const data = el._x_dataStack[0];
    const forward = data.availableBenchForPicker.find(p => p.positionGroup === 'Forward');
    if (forward) {
      data.selectedPlayerIn = forward;
    }
  });
  await pause(1000);

  // Show confirmation screen then apply all changes (tactics + substitution)
  console.log('   Showing confirmation...');
  await page.evaluate(() => {
    const el = document.querySelector('[x-data]');
    if (el && el._x_dataStack) {
      el._x_dataStack[0].showConfirmation();
    }
  });
  await pause(1000);

  console.log('   Applying changes...');
  await page.evaluate(() => {
    const el = document.querySelector('[x-data]');
    if (el && el._x_dataStack) {
      el._x_dataStack[0].confirmAllChanges();
    }
  });
  await pause(1500);

  // ── Scene 5: Resume & Final Result (~2s) ──────────────────────────────
  console.log('7. Scene: Resume & Skip to End');

  // Skip to full time
  console.log('   Skipping to full time...');
  await page.keyboard.press('Escape');
  await pause(1500);

  // Wait for processing and show final result
  await page.waitForFunction(
    () => {
      const el = document.querySelector('[x-data]');
      if (!el || !el._x_dataStack) return false;
      return el._x_dataStack[0].processingReady === true;
    },
    { timeout: 30000 }
  );
  await pause(500);

  console.log('\n✓ All scenes captured!\n');
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
const outputPath = resolve(VIDEO_DIR, 'virtuafc-match-promo.mp4');
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
