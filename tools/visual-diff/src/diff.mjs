import fs from 'node:fs';
import path from 'node:path';
import { PNG } from 'pngjs';
import pixelmatch from 'pixelmatch';
import { ensureDir, formatPercent, loadConfig, percent, readJson, resolveRunId, writeJson } from './utils.mjs';

function readPng(filePath) {
  return PNG.sync.read(fs.readFileSync(filePath));
}

function writePng(filePath, png) {
  ensureDir(path.dirname(filePath));
  fs.writeFileSync(filePath, PNG.sync.write(png));
}

function cropToSameSize(reference, target) {
  const width = Math.min(reference.width, target.width);
  const height = Math.min(reference.height, target.height);
  if (reference.width === width && reference.height === height && target.width === width && target.height === height) {
    return { reference, target, cropped: false };
  }

  const crop = (png) => {
    const cropped = new PNG({ width, height });
    PNG.bitblt(png, cropped, 0, 0, width, height, 0, 0);
    return cropped;
  };

  return {
    reference: crop(reference),
    target: crop(target),
    cropped: true,
  };
}

export function diffRun({ runId, threshold = null } = {}) {
  const config = loadConfig();
  const resolvedRunId = resolveRunId(runId);
  const runDir = path.join(process.cwd(), 'output', resolvedRunId);
  const manifestPath = path.join(runDir, 'manifest.json');
  const manifest = readJson(manifestPath);

  if (!manifest) {
    throw new Error(`Missing manifest at ${manifestPath}`);
  }

  const diffThreshold = threshold ?? config.defaults.threshold ?? 0.1;
  const results = [];

  for (const entry of manifest.pages) {
    const refPath = path.join(runDir, entry.files.reference);
    const targetPath = path.join(runDir, entry.files.target);
    const diffPath = path.join(runDir, 'diff', `${entry.id}-${entry.viewport}.png`);

    if (!fs.existsSync(refPath) || !fs.existsSync(targetPath)) {
      results.push({
        ...entry,
        status: 'missing',
        mismatchPercent: null,
        diffFile: null,
        cropped: false,
      });
      continue;
    }

    const referencePng = readPng(refPath);
    const targetPng = readPng(targetPath);
    const { reference, target, cropped } = cropToSameSize(referencePng, targetPng);
    const diffPng = new PNG({ width: reference.width, height: reference.height });
    const mismatchedPixels = pixelmatch(
      reference.data,
      target.data,
      diffPng.data,
      reference.width,
      reference.height,
      { threshold: diffThreshold, includeAA: true },
    );
    writePng(diffPath, diffPng);

    const mismatchPercent = percent(mismatchedPixels, reference.width * reference.height);
    results.push({
      ...entry,
      status: 'compared',
      mismatchPercent,
      diffFile: path.relative(runDir, diffPath),
      cropped,
      dimensions: {
        reference: { width: referencePng.width, height: referencePng.height },
        target: { width: targetPng.width, height: targetPng.height },
        compared: { width: reference.width, height: reference.height },
      },
    });
  }

  const report = {
    runId: resolvedRunId,
    generatedAt: new Date().toISOString(),
    threshold: diffThreshold,
    summary: {
      total: results.length,
      compared: results.filter((item) => item.status === 'compared').length,
      missing: results.filter((item) => item.status === 'missing').length,
      highDiff: results.filter((item) => (item.mismatchPercent ?? 0) >= 5).length,
    },
    results,
  };

  writeJson(path.join(runDir, 'report.json'), report);
  return { runDir, report };
}

export function renderHtmlReport(report, runDir) {
  const rows = report.results.map((item) => {
    const mismatch = item.mismatchPercent == null ? '—' : formatPercent(item.mismatchPercent);
    const severity = item.mismatchPercent == null
      ? 'missing'
      : item.mismatchPercent >= 15
        ? 'high'
        : item.mismatchPercent >= 5
          ? 'medium'
          : 'low';

    const refImg = item.files?.reference
      ? `<img src="${item.files.reference}" alt="reference" loading="lazy" />`
      : '<p>Missing reference capture</p>';
    const targetImg = item.files?.target
      ? `<img src="${item.files.target}" alt="target" loading="lazy" />`
      : '<p>Missing target capture</p>';
    const diffImg = item.diffFile
      ? `<img src="${item.diffFile}" alt="diff" loading="lazy" />`
      : '<p>No diff</p>';

    return `
      <section class="card severity-${severity}" id="${item.id}-${item.viewport}">
        <header>
          <h2>${item.label} <span class="viewport">${item.viewport}</span></h2>
          <p class="meta">
            <strong>Mismatch:</strong> ${mismatch}
            ${item.cropped ? ' · cropped to common size' : ''}
            ${item.matrixRefs?.length ? ` · matrix #${item.matrixRefs.join(', #')}` : ''}
          </p>
          <p class="urls"><code>${item.reference}</code><br /><code>${item.target}</code></p>
          ${item.notes ? `<p class="notes">${item.notes}</p>` : ''}
        </header>
        <div class="shots">
          <figure><figcaption>Reference (prod/LTS)</figcaption>${refImg}</figure>
          <figure><figcaption>Target (migrated)</figcaption>${targetImg}</figure>
          <figure><figcaption>Diff</figcaption>${diffImg}</figure>
        </div>
      </section>
    `;
  }).join('\n');

  return `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Visual diff ${report.runId}</title>
  <style>
    :root { color-scheme: light dark; font-family: system-ui, sans-serif; }
    body { margin: 0; padding: 24px; background: #111; color: #eee; }
    h1 { margin-top: 0; }
    .summary { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; }
    .summary div { background: #1c1c1c; border: 1px solid #333; border-radius: 8px; padding: 12px 16px; }
    .card { background: #1a1a1a; border: 1px solid #333; border-radius: 12px; padding: 16px; margin-bottom: 24px; }
    .card.severity-high { border-color: #b33; }
    .card.severity-medium { border-color: #b93; }
    .card.severity-low { border-color: #393; }
    .viewport { font-size: 0.85rem; color: #aaa; font-weight: normal; }
    .meta, .urls, .notes { color: #bbb; }
    .urls code { word-break: break-all; }
    .shots { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px; }
    figure { margin: 0; }
    figcaption { margin-bottom: 8px; color: #aaa; }
    img { width: 100%; height: auto; border: 1px solid #444; border-radius: 6px; background: #fff; }
  </style>
</head>
<body>
  <h1>Visual diff — ${report.runId}</h1>
  <p>Generated ${report.generatedAt}. Threshold ${report.threshold}. Human triage required — high mismatch may include dynamic content, dates, or search results.</p>
  <div class="summary">
    <div><strong>Total</strong><br />${report.summary.total}</div>
    <div><strong>Compared</strong><br />${report.summary.compared}</div>
    <div><strong>Missing</strong><br />${report.summary.missing}</div>
    <div><strong>High diff (≥5%)</strong><br />${report.summary.highDiff}</div>
  </div>
  ${rows}
</body>
</html>`;
}

export function writeHtmlReport(report, runDir) {
  const html = renderHtmlReport(report, runDir);
  const htmlPath = path.join(runDir, 'index.html');
  fs.writeFileSync(htmlPath, html);
  return htmlPath;
}
