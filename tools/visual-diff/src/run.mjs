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
    threshold: null,
    headless: true,
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
    if (arg.startsWith('--threshold=')) {
      args.threshold = Number(arg.slice('--threshold='.length));
      continue;
    }
    if (arg === '--headed') {
      args.headless = false;
    }
  }

  return args;
}

async function main() {
  const args = parseArgs(process.argv.slice(2));

  if (args.command === 'capture') {
    const result = await captureScreenshots({
      runId: args.runId ?? undefined,
      side: args.side,
      pageFilter: args.pages,
      headless: args.headless,
    });
    console.log(`Captured ${result.manifest.pages.length} screenshots to output/${result.runId}`);
    return;
  }

  if (args.command === 'diff') {
    const { runDir, report } = diffRun({ runId: args.runId, threshold: args.threshold });
    const htmlPath = writeHtmlReport(report, runDir);
    console.log(`Report written to ${htmlPath}`);
    console.log(`High-diff pages: ${report.summary.highDiff}/${report.summary.compared}`);
    console.log(`Open report: npm run open-report -- ${report.runId}`);
    return;
  }

  const capture = await captureScreenshots({
    runId: args.runId ?? undefined,
    side: 'both',
    pageFilter: args.pages,
    headless: args.headless,
  });
  const { runDir, report } = diffRun({ runId: capture.runId, threshold: args.threshold });
  const htmlPath = writeHtmlReport(report, runDir);
  console.log(`Visual diff complete: output/${capture.runId}/index.html`);
  console.log(`Compared ${report.summary.compared} captures; ${report.summary.highDiff} above 5% mismatch`);
  console.log(`Open report: npm run open-report -- ${capture.runId}`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
