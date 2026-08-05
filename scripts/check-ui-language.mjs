import { readdirSync, readFileSync } from 'node:fs';
import { extname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const PROJECT_ROOT = join(fileURLToPath(new URL('.', import.meta.url)), '..');
const UI_ROOT = join(PROJECT_ROOT, 'resources', 'js');
const SOURCE_EXTENSIONS = new Set(['.js', '.jsx', '.ts', '.tsx']);
const IGNORED_DIRECTORIES = new Set(['actions', 'routes', 'wayfinder']);
const IGNORED_FILES = /\.(?:test|spec)\.[^.]+$/;

/**
 * Terms that identify Rioplatense voseo in user-facing UI text.
 * Add new terms here when the language policy needs to cover another form.
 */
const FORBIDDEN_TERMS = [
  'vos',
  'sos',
  'tenés',
  'podés',
  'querés',
  'sabés',
  'hacés',
  'decís',
  'venís',
  'seguís',
  'elegís',
  'recibís',
  'subís',
  'escribís',
  'leés',
  'oís',
  'salís',
  'tené',
  'podé',
  'queré',
  'sabé',
  'hacé',
  'decí',
  'seguí',
  'elegí',
  'recibí',
  'subí',
  'escribí',
  'leé',
  'oí',
  'salí',
  'vení',
  'enviá',
  'creá',
  'revisá',
  'guardá',
  'cancelá',
  'cerrá',
  'abrí',
  'seleccioná',
  'configurá',
  'confirmá',
  'eliminá',
  'agregá',
  'intentá',
  'continuá',
  'iniciá',
  'finalizá',
  'promové',
  'promovés',
  'degradá',
  'degradás',
  'cambiá',
  'cambiás',
  'buscá',
  'buscás',
  'filtrá',
  'filtrás',
  'añadí',
  'decidí',
  'volvé',
  'pasá',
  'dame',
  'decime',
  'mostrame',
  'ayudame',
];

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function patternFor(term) {
  return new RegExp(
    `(?<![\\p{L}\\p{M}])${escapeRegExp(term)}(?![\\p{L}\\p{M}])`,
    'giu',
  );
}

function collectSourceFiles(directory) {
  const files = [];

  for (const entry of readdirSync(directory, { withFileTypes: true })) {
    const path = join(directory, entry.name);

    if (entry.isDirectory()) {
      if (!IGNORED_DIRECTORIES.has(entry.name)) {
        files.push(...collectSourceFiles(path));
      }
      continue;
    }

    if (
      entry.isFile() &&
      SOURCE_EXTENSIONS.has(extname(entry.name)) &&
      !IGNORED_FILES.test(entry.name)
    ) {
      files.push(path);
    }
  }

  return files;
}

function locationAt(source, index) {
  const lineStart = source.lastIndexOf('\n', index - 1) + 1;
  const line = source.slice(0, index).split('\n').length;

  return { line, column: index - lineStart + 1 };
}

const violations = [];

for (const filePath of collectSourceFiles(UI_ROOT)) {
  const source = readFileSync(filePath, 'utf8');
  const relativePath = relative(PROJECT_ROOT, filePath);

  for (const term of FORBIDDEN_TERMS) {
    const pattern = patternFor(term);

    for (const match of source.matchAll(pattern)) {
      const { line, column } = locationAt(source, match.index ?? 0);

      violations.push({
        column,
        filePath: relativePath,
        line,
        term: match[0],
      });
    }
  }
}

if (violations.length > 0) {
  violations.sort(
    (left, right) =>
      left.filePath.localeCompare(right.filePath) ||
      left.line - right.line ||
      left.column - right.column,
  );

  console.error('UI language check failed: Rioplatense voseo found.');

  for (const violation of violations) {
    console.error(
      `- ${violation.filePath}:${violation.line}:${violation.column} — ${violation.term}`,
    );
  }

  process.exitCode = 1;
} else {
  console.log('UI language check passed: no Rioplatense voseo found.');
}
