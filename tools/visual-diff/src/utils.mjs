import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
export const ROOT = path.resolve(__dirname, '..');

export function loadConfig() {
  const configPath = path.join(ROOT, 'pages.json');
  return JSON.parse(fs.readFileSync(configPath, 'utf8'));
}

export function ensureDir(dir) {
  fs.mkdirSync(dir, { recursive: true });
}

export function slug(value) {
  return String(value).replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '').toLowerCase();
}

export function timestamp() {
  const now = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  return `${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}-${pad(now.getHours())}${pad(now.getMinutes())}${pad(now.getSeconds())}`;
}

export function readJson(filePath, fallback = null) {
  if (!fs.existsSync(filePath)) {
    return fallback;
  }
  return JSON.parse(fs.readFileSync(filePath, 'utf8'));
}

export function writeJson(filePath, data) {
  ensureDir(path.dirname(filePath));
  fs.writeFileSync(filePath, `${JSON.stringify(data, null, 2)}\n`);
}

export function listRuns() {
  const outputDir = path.join(ROOT, 'output');
  if (!fs.existsSync(outputDir)) {
    return [];
  }
  return fs.readdirSync(outputDir)
    .filter((name) => fs.statSync(path.join(outputDir, name)).isDirectory())
    .sort()
    .reverse();
}

export function resolveRunId(runId) {
  if (runId) {
    return runId;
  }
  const runs = listRuns();
  if (runs.length === 0) {
    throw new Error('No runs found. Run `npm run run` first.');
  }
  return runs[0];
}

export function filterPages(pages, pageFilter) {
  if (!pageFilter) {
    return pages;
  }
  const ids = new Set(pageFilter.split(',').map((id) => id.trim()).filter(Boolean));
  return pages.filter((page) => ids.has(page.id));
}

export function filterPagesByTags(pages, tagFilter) {
  if (!tagFilter) {
    return pages;
  }
  const tags = new Set(tagFilter.split(',').map((tag) => tag.trim()).filter(Boolean));
  return pages.filter((page) => (page.tags ?? []).some((tag) => tags.has(tag)));
}

export function percent(part, total) {
  if (total === 0) {
    return 0;
  }
  return (part / total) * 100;
}

export function formatPercent(value) {
  return `${value.toFixed(2)}%`;
}
