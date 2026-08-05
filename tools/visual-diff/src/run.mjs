#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { captureScreenshots } from './capture.mjs';
import { diffRun, writeHtmlReport } from './diff.mjs';

function parseArgs(argv) {
  const args = {
    command: 'run',
    runId: null,
    side: 'both',
    pages: null,
    tags: null,
    threshold: null,
    headless: true,
    continueOnError: true,
    resume: false,
    waitUntil: null,
    navigationTimeoutMs: null,
    postLoadDelayMs: null,
  };

  for (const arg of argv) {
    if (arg === 'capture' || arg === 'diff' || arg === 'run') {
      args.command = arg;
      continue;
    }
    if (arg.startsWith('--run=')) {
      args.runId = arg.slice('--run='.length);
      continue;
    }
    if (arg.startsWith('--side=')) {
      args.side = arg.slice('--side='.length);
      continue;
    }
    if (arg.startsWith('--pages=')) {
      args.pages = arg.slice('--pages='.length);
      continue;
    }
    if (arg.startsWith('--tags=')) {
      args.tags = arg.slice('--tags='.length);
      continue;
    }
    if (arg.startsWith('--threshold=')) {
      args.threshold = Number(arg.slice('--threshold='.length));
      continue;
    }
    if (arg.startsWith('--wait-until=')) {
      args.waitUntil = arg.slice('--wait-until='.length);
      continue;
    }
    if (arg.startsWith('--timeout=')) {
      args.navigationTimeoutMs = Number(arg.slice('--timeout='.length));
      continue;
    }
    if (arg.startsWith('--delay=')) {
      args.postLoadDelayMs = Number(arg.slice('--delay='.length));
      continue;
    }
    if (arg === '--headed') {
      args.headless = false;
    }
    if (arg === '--fail-fast') {
      args.continueOnError = false;
    }
    if (arg === '--resume') {
      args.resume = true;
    }
  }

  return args;
}

function captureOptions(args) {
  const captureDefaults = {};
  if (args.waitUntil) {
    captureDefaults.waitUntil = args.waitUntil;
  }
  if (args.navigationTimeoutMs) {
    captureDefaults.navigationTimeoutMs = args.navigationTimeoutMs;
  }
  if (args.postLoadDelayMs) {
    captureDefaults.postLoadDelayMs = args.postLoadDelayMs;
  }

  return {
    runId: args.runId ?? undefined,
    side: args.side,
    pageFilter: args.pages,
    tagFilter: args.tags,
    headless: args.headless,
    continueOnError: args.continueOnError,
    resume: args.resume,
    captureDefaults,
  };
}

async function main() {
  const args = parseArgs(process.argv.slice(2));
  const captureOpts = captureOptions(args);

  if (args.command === 'capture') {
    const result = await captureScreenshots(captureOpts);
    console.log(`Captured ${result.manifest.pages.length} page entries to output/${result.runId}`);
    if (result.manifest.failures?.length) {
      process.exitCode = 1;
    }
    return;
  }

  if (args.command === 'diff') {
    const { report } = diffRun({ runId: args.runId, threshold: args.threshold });
    const htmlPath = writeHtmlReport(report, path.join(process.cwd(), 'output', report.runId));
    console.log(`Report written to ${htmlPath}`);
    console.log(`High-diff pages: ${report.summary.highDiff}/${report.summary.compared}`);
    console.log(`Open report: npm run open-report -- ${report.runId}`);
    return;
  }

  const capture = await captureScreenshots(captureOpts);
  const { report } = diffRun({ runId: capture.runId, threshold: args.threshold });
  const htmlPath = writeHtmlReport(report, path.join(process.cwd(), 'output', capture.runId));
  console.log(`Visual diff complete: output/${capture.runId}/index.html`);
  console.log(`Compared ${report.summary.compared} captures; ${report.summary.highDiff} above 5% mismatch`);
  if (report.summary.missing > 0) {
    console.warn(`Missing captures: ${report.summary.missing} (see manifest.failures)`);
  }
  console.log(`Open report: npm run open-report -- ${capture.runId}`);
  if (capture.manifest.failures?.length) {
    process.exitCode = 1;
  }
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
