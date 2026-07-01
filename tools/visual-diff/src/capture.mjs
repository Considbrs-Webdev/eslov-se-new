import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
import { ensureDir, filterPages, loadConfig, slug, timestamp } from './utils.mjs';

const COOKIE_DISMISS_SELECTORS = [
  '#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll',
  '#onetrust-accept-btn-handler',
  'button:has-text("Acceptera")',
  'button:has-text("Godkänn")',
];

async function dismissOverlays(page) {
  for (const selector of COOKIE_DISMISS_SELECTORS) {
    try {
      const button = page.locator(selector).first();
      if (await button.isVisible({ timeout: 500 })) {
        await button.click({ timeout: 2000 });
        await page.waitForTimeout(400);
      }
    } catch {
      // Optional overlay — ignore.
    }
  }
}

async function captureOne(page, url, filePath, viewport, defaults) {
  await page.setViewportSize({ width: viewport.width, height: viewport.height });
  await page.goto(url, {
    waitUntil: defaults.waitUntil ?? 'networkidle',
    timeout: 120000,
  });
  await dismissOverlays(page);
  if (defaults.postLoadDelayMs) {
    await page.waitForTimeout(defaults.postLoadDelayMs);
  }
  ensureDir(path.dirname(filePath));
  await page.screenshot({
    path: filePath,
    fullPage: defaults.fullPage ?? true,
    animations: 'disabled',
  });
}

export async function captureScreenshots({
  runId = timestamp(),
  side = 'both',
  pageFilter = null,
  headless = true,
} = {}) {
  const config = loadConfig();
  const pages = filterPages(config.pages, pageFilter);
  const runDir = path.join(process.cwd(), 'output', runId);
  const manifest = {
    runId,
    createdAt: new Date().toISOString(),
    side,
    viewports: config.viewports,
    pages: [],
  };

  const browser = await chromium.launch({ headless });
  const context = await browser.newContext({
    ignoreHTTPSErrors: true,
    locale: 'sv-SE',
  });
  const page = await context.newPage();

  for (const entry of pages) {
    for (const viewport of config.viewports) {
      const baseName = `${entry.id}-${viewport.id}`;
      const record = {
        id: entry.id,
        label: entry.label,
        viewport: viewport.id,
        matrixRefs: entry.matrixRefs ?? [],
        notes: entry.notes ?? '',
        reference: entry.reference,
        target: entry.target,
        files: {},
      };

      if (side === 'both' || side === 'reference') {
        const refPath = path.join(runDir, 'reference', `${baseName}.png`);
        await captureOne(page, entry.reference, refPath, viewport, config.defaults);
        record.files.reference = path.relative(runDir, refPath);
      }

      if (side === 'both' || side === 'target') {
        const targetPath = path.join(runDir, 'target', `${baseName}.png`);
        await captureOne(page, entry.target, targetPath, viewport, config.defaults);
        record.files.target = path.relative(runDir, targetPath);
      }

      manifest.pages.push(record);
    }
  }

  await browser.close();
  fs.writeFileSync(path.join(runDir, 'manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`);
  return { runId, runDir, manifest };
}
