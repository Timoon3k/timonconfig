/** Bounded PHP child processes; no WordPress bootstrap, database writes or licensed bytes. */
const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const { mkdirSync, writeFileSync } = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const environment = { ...process.env };
for (const name of ['DOCKER_HOST', 'DOCKER_CONTEXT', 'DOCKER_TLS', 'DOCKER_TLS_VERIFY', 'DOCKER_CERT_PATH']) delete environment[name];
const report = { passed: false, assertions: 0, cases: [] };
const check = (ok, message) => { report.assertions++; assert.ok(ok, message); };
const preamble = `define('ABSPATH','/var/www/html/');require '/var/www/html/wp-content/plugins/timonconfigs-core/includes/class-tc-downloads.php';$method=new ReflectionMethod(TC_Downloads::class,'clear_output_buffers');`;
const cases = [
  { name: 'No output buffers', setup: '', expected: true, levels: 0 },
  { name: 'Nested removable buffers discard unrelated output', setup: `ob_start();echo 'LOCAL_BUFFER_SENTINEL';ob_start();echo 'LOCAL_INNER_SENTINEL';`, expected: true, levels: 0 },
  { name: 'Non-removable buffer fails within the child timeout', setup: `ob_start(null,0,PHP_OUTPUT_HANDLER_CLEANABLE|PHP_OUTPUT_HANDLER_FLUSHABLE);`, expected: false, levels: 1 },
  { name: 'Removable inner buffer is discarded before a non-removable outer buffer', setup: `ob_start(null,0,PHP_OUTPUT_HANDLER_CLEANABLE|PHP_OUTPUT_HANDLER_FLUSHABLE);ob_start();echo 'LOCAL_INNER_SENTINEL';`, expected: false, levels: 1 },
];

try {
  for (const entry of cases) {
    const php = `${preamble}${entry.setup}$start=microtime(true);$result=$method->invoke(null);fwrite(STDERR,'TC_DOWNLOAD_BUFFER:'.json_encode(array('result'=>$result,'levels'=>ob_get_level(),'elapsed'=>microtime(true)-$start)).PHP_EOL);`;
    // The inner timeout also kills PHP if an old infinite loop outlives its Docker client.
    const result = spawnSync('docker', ['--host=unix:///var/run/docker.sock', 'exec', 'timonconfigs-wordpress', 'timeout', '3', 'php', '-d', 'display_errors=0', '-d', 'log_errors=0', '-d', 'error_reporting=0', '-r', php], { cwd: root, env: environment, encoding: 'utf8', timeout: 8000, maxBuffer: 1024 * 1024 });
    check(!result.error && result.status === 0, `${entry.name}: bounded PHP process completes; ${result.error?.message || result.stderr}`);
    const line = result.stderr.split('\n').find(value => value.startsWith('TC_DOWNLOAD_BUFFER:'));
    check(Boolean(line), `${entry.name}: helper returns a structured result`);
    const actual = JSON.parse(line.slice('TC_DOWNLOAD_BUFFER:'.length));
    check(actual.result === entry.expected && actual.levels === entry.levels, `${entry.name}: safe cleanup result and remaining level`);
    check(result.stdout === '', `${entry.name}: clearing buffers never flushes synthetic buffered content as attachment bytes`);
    report.cases.push({ name: entry.name, ...actual });
  }
  report.passed = true;
} catch (error) {
  report.failure = error.message; process.exitCode = 1;
} finally {
  mkdirSync(path.join(root, 'artifacts'), { recursive: true });
  writeFileSync(path.join(root, 'artifacts/download-buffer.json'), `${JSON.stringify(report, null, 2)}\n`);
  console.log(JSON.stringify(report));
}
