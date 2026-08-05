import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
import { ensureDir, filterPages, filterPagesByTags, loadConfig, timestamp } from './utils.mjs';

const COOKIE_DISMISS_SELECTORS = [
  '#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll',
  '#onetrust-accept-btn-handler',
  'button:has-text("Acceptera")',
  'button:has-text("Godkänn")',
];

const BLOCKED_URL_PATTERNS = [
  /google-analytics\.com/i,
  /googletagmanager\.com/i,
  /doubleclick\.net/i,
  /hotjar\.com/i,
  /clarity\.ms/i,
  /facebook\.net/i,
  /connect\.facebook\.net/i,
  /snap\.licdn\.com/i,
  /analytics/i,
];

function shouldBlockUrl(url) {
  return BLOCKED_URL_PATTERNS.some((pattern) => pattern.test(url));
}

function mergeDefaults(configDefaults, overrides = {}) {
  return { ...configDefaults, ...overrides };
}

async function setupRequestBlocking(context) {
  await context.route('**/*', (route) => {
    if (shouldBlockUrl(route.request().url())) {
      route.abort();
      return;
    }
    route.continue();
  });
}

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

async function gotoWithFallback(page, url, defaults) {
  const timeout = defaults.navigationTimeoutMs ?? 60000;
  const strategies = [
    defaults.waitUntil ?? 'load',
    'domcontentloaded',
  ];

  let lastError;
  for (const waitUntil of strategies) {
    try {
      await page.goto(url, { waitUntil, timeout });
      return waitUntil;
    } catch (error) {
      lastError = error;
      if (waitUntil === 'domcontentloaded') {
        throw error;
      }
    }
  }

  throw lastError;
}

async function captureOne(page, url, filePath, viewport, defaults) {
  await page.setViewportSize({ width: viewport.width, height: viewport.height });
  const waitUsed = await gotoWithFallback(page, url, defaults);
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

  return waitUsed;
}

async function captureSide({
  page,
  url,
  filePath,
  viewport,
  defaults,
  resume,
  label,
}) {
  if (resume && fs.existsSync(filePath)) {
    return { status: 'skipped', waitUntil: null };
  }

  const waitUntil = await captureOne(page, url, filePath, viewport, defaults);
  return { status: 'captured', waitUntil };
}

export async function captureScreenshots({
  runId = timestamp(),
  side = 'both',
  pageFilter = null,
  tagFilter = null,
  headless = true,
  continueOnError = true,
  resume = false,
  captureDefaults = {},
} = {}) {
  const config = loadConfig();
  const defaults = mergeDefaults(config.defaults ?? {}, captureDefaults);
  let pages = filterPages(config.pages, pageFilter);
  pages = filterPagesByTags(pages, tagFilter);

  const runDir = path.join(process.cwd(), 'output', runId);
  const manifest = {
    runId,
    createdAt: new Date().toISOString(),
    side,
    defaults,
    options: { continueOnError, resume },
    viewports: config.viewports,
    pages: [],
    failures: [],
  };

  const browser = await chromium.launch({ headless });
  const context = await browser.newContext({
    ignoreHTTPSErrors: true,
    locale: 'sv-SE',
  });
  await setupRequestBlocking(context);
  const page = await context.newPage();

  const total = pages.length * config.viewports.length * (side === 'both' ? 2 : 1);
  let step = 0;

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
        capture: {},
      };

      const sides = side === 'both' ? ['reference', 'target'] : [side];

      for (const captureSideName of sides) {
        step += 1;
        const url = captureSideName === 'reference' ? entry.reference : entry.target;
        const filePath = path.join(runDir, captureSideName, `${baseName}.png`);
        const progress = `[${step}/${total}]`;

        try {
          console.log(`${progress} ${captureSideName} ${entry.id} (${viewport.id})`);
          const result = await captureSide({
            page,
            url,
            filePath,
            viewport,
            defaults,
            resume,
            label: entry.label,
          });
          record.files[captureSideName] = path.relative(runDir, filePath);
          record.capture[captureSideName] = result;
        } catch (error) {
          const failure = {
            id: entry.id,
            viewport: viewport.id,
            side: captureSideName,
            url,
            error: error.message,
          };
          manifest.failures.push(failure);
          record.capture[captureSideName] = { status: 'failed', error: error.message };

          console.warn(`${progress} FAILED ${url}: ${error.message}`);

          if (!continueOnError) {
            await browser.close();
            fs.writeFileSync(path.join(runDir, 'manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`);
            throw error;
          }
        }
      }

      manifest.pages.push(record);
    }
  }

  await browser.close();
  fs.writeFileSync(path.join(runDir, 'manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`);

  if (manifest.failures.length > 0) {
    console.warn(`Capture finished with ${manifest.failures.length} failure(s). See manifest.failures`);
  }

  return { runId, runDir, manifest };
}
