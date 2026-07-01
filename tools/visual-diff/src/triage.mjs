import fs from 'node:fs';
import path from 'node:path';
import { readJson, resolveRunId, writeJson } from './utils.mjs';

const FIX_CLASSES = ['migrate', 'tokens', 'config', 'shim', 'accepted', 'investigate'];

function parseArgs(argv) {
  const args = { runId: null, write: false };
  for (const arg of argv) {
    if (arg.startsWith('--run=')) {
      args.runId = arg.slice('--run='.length);
    }
    if (arg === '--write') {
      args.write = true;
    }
  }
  return args;
}

function suggestFixClass(item) {
  const notes = `${item.notes ?? ''} ${(item.matrixRefs ?? []).join(' ')}`.toLowerCase();
  if (notes.includes('token') || notes.includes('radius') || notes.includes('typography')) {
    return 'tokens';
  }
  if (notes.includes('shim') || notes.includes('childpage') || notes.includes('taglist')) {
    return 'shim';
  }
  if (notes.includes('modularity') || notes.includes('migrate') || notes.includes('manual_inputs')) {
    return 'migrate';
  }
  if (notes.includes('search') || notes.includes('dynamic')) {
    return 'accepted';
  }
  if ((item.mismatchPercent ?? 0) >= 5) {
    return 'investigate';
  }
  return 'accepted';
}

function main() {
  const args = parseArgs(process.argv.slice(2));
  const runId = resolveRunId(args.runId);
  const runDir = path.join(process.cwd(), 'output', runId);
  const reportPath = path.join(runDir, 'report.json');
  const report = readJson(reportPath);

  if (!report) {
    throw new Error(`Missing report at ${reportPath}. Run npm run run first.`);
  }

  const triage = {
    runId,
    generatedAt: new Date().toISOString(),
    instructions: 'Set fixClass per item after reviewing index.html. Valid values: migrate, tokens, config, shim, accepted, investigate.',
    items: report.results.map((item) => ({
      id: `${item.id}-${item.viewport}`,
      pageId: item.id,
      label: item.label,
      viewport: item.viewport,
      mismatchPercent: item.mismatchPercent,
      matrixRefs: item.matrixRefs ?? [],
      notes: item.notes ?? '',
      suggestedFixClass: suggestFixClass(item),
      fixClass: suggestFixClass(item),
      action: '',
      status: 'pending',
    })),
  };

  const triagePath = path.join(runDir, 'triage.json');
  if (args.write || !fs.existsSync(triagePath)) {
    writeJson(triagePath, triage);
    console.log(`Wrote ${triagePath}`);
  } else {
    console.log(`Triage file already exists: ${triagePath}`);
    console.log('Pass --write to regenerate.');
  }

  const counts = triage.items.reduce((acc, item) => {
    acc[item.fixClass] = (acc[item.fixClass] ?? 0) + 1;
    return acc;
  }, {});

  console.log('Suggested triage counts:', counts);
  console.log('Valid fix classes:', FIX_CLASSES.join(', '));
}

main();
