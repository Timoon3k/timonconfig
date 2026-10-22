/** Read-only public URL checks. No payment routes, login, or site writes. */
const { spawnSync } = require('node:child_process');
const { mkdir, readFile, writeFile } = require('node:fs/promises');
const path = require('node:path');

const ROUTES = [
  '/', '/configs/', '/configs/?tier=free', '/games/', '/pricing/',
  '/vip-monthly/', '/vip-lifetime/', '/apex-legends-configs/',
  '/cronus-zen-configs/', '/xim-matrix-configs/',
  '/call-of-duty-configs/', '/fortnite-configs/', '/rainbow-six-siege-configs/',
  '/rust-configs/', '/ea-sports-fc-configs/', '/nba-2k-configs/',
  '/faq/', '/reviews/', '/contact/', '/video-showcase/',
  '/terms-of-service/', '/privacy-policy/', '/refund-policy/',
  '/cookie-policy/', '/lifetime-license-terms/',
];
const artifactDirectory = path.resolve(__dirname, '../artifacts');
const reportPath = path.resolve(__dirname, '../docs/URL_STATUS.md');

async function readJSON(file) {
  try { return JSON.parse(await readFile(file, 'utf8')); }
  catch (error) { if (error.code === 'ENOENT') return null; throw error; }
}

function markdown(value) { return String(value ?? '—').replaceAll('|', '\\|').replaceAll('\n', ' '); }

async function writeStatusReport() {
  const local = await readJSON(path.join(artifactDirectory, 'website-checks.json'));
  const live = await readJSON(path.join(artifactDirectory, 'public-url-status.json'));
  const cms = await readJSON(path.join(artifactDirectory, 'website-cms-checks.json'));
  const lines = [
    '# TimonConfigs URL verification', '',
    'This report records observed checks. Local responses do not establish deployment to the public website. A CONNECT/proxy failure is an environment access failure, not an observed WordPress HTTP response.', '',
    `Local run: ${local?.startedAt || 'not run'}.`,
    `Public run: ${live?.startedAt || 'not run'}.`, '',
    '| Route | Local HTTP | H1 | Canonical count | Robots | Public result |',
    '| --- | ---: | ---: | ---: | --- | --- |',
  ];
  for (const route of ROUTES) {
    const item = local?.routes?.find(row => row.path === route);
    const remote = live?.routes?.find(row => row.path === route);
    const publicResult = remote ? (remote.status === 'http' ? `HTTP ${remote.http}` : remote.status.replaceAll('_', ' ')) : 'not run';
    lines.push(`| ${markdown(route)} | ${item?.http ?? 'not run'} | ${item?.h1Count ?? '—'} | ${item?.canonicals?.length ?? '—'} | ${markdown(item?.robots || 'default')} | ${markdown(publicResult)} |`);
  }
  lines.push('', '## Local behavior checks', '');
  if (local) {
    lines.push(`Result: **${local.passed ? 'passed' : 'failed'}**. ${local.viewportChecks?.length || 0} route/viewport checks.`, '');
    for (const [name, value] of Object.entries(local.checks || {})) lines.push(`- ${markdown(name)}: ${markdown(typeof value === 'object' ? JSON.stringify(value) : value)}.`);
    if (local.failures?.length) {
      lines.push('', 'Failures:', '');
      for (const failure of local.failures) lines.push(`- ${markdown(failure)}.`);
    }
    lines.push('', '## Synthetic performance observations', '', 'These are single local browser observations under the current machine load. They are not field Core Web Vitals, a Lighthouse score, or a verified INP measurement.', '', '| Viewport | LCP (ms) | CLS | Observed interaction duration (ms) |', '| --- | ---: | ---: | ---: |');
    for (const sample of local.performance || []) lines.push(`| ${sample.width}px | ${sample.lcpMs ?? 'not observed'} | ${sample.cls ?? 'not observed'} | ${sample.interactionMs ?? 'not observed'} |`);
  } else lines.push('Not run yet.');
  if (cms) {
    lines.push('', '## Additional Gutenberg UI verification', '', `Run: ${cms.startedAt}. Result: **${cms.passed ? 'passed' : 'failed'}**.`, '');
    const editor = cms.checks?.gutenberg;
    if (editor) {
      lines.push(`The real Home editor contained ${editor.homeBlocks?.length || 0} valid storefront sections with section attributes and ${editor.renderedPreviews || 0} rendered server previews. Selecting Hero exposed its Heading control. Editorial REST types were available. Page content was not saved.`, '', `Application editor errors: ${JSON.stringify(editor.editorErrors || [])}.`);
      if (editor.editorEnvironmentFailures?.length) lines.push('', `WordPress admin assets from ${[...new Set(editor.editorEnvironmentFailures.map(failure => failure.host))].join(', ')} were blocked by the environment proxy. These verified external-resource failures are recorded separately from React/application exceptions.`);
    }
    if (cms.failures?.length) for (const failure of cms.failures) lines.push(`- ${markdown(failure)}.`);
  }
  lines.push('', '## Public access and Discord invitation', '');
  if (live) {
    lines.push(`Origin: ${markdown(live.origin)}.`, '', markdown(live.note || 'Public pages were requested with read-only GET requests.'));
    if (live.invite) lines.push('', `Discord invite API: ${markdown(live.invite.status)}${live.invite.http ? ` (HTTP ${live.invite.http})` : ''}. ${markdown(live.invite.note || '')}`);
  } else lines.push('Not run yet.');
  lines.push('', 'Payment transactions, existing checkout products, subscription state, and Discord role changes are outside these automatic checks.', '');
  await mkdir(path.dirname(reportPath), { recursive: true });
  await writeFile(reportPath, lines.join('\n'));
}

function probe(url) {
  const result = spawnSync('curl', ['--location', '--max-redirs', '5', '--max-time', '15', '--silent', '--show-error', '--output', '/dev/null', '--write-out', '%{http_code}\n%{url_effective}\n', url], { encoding: 'utf8', timeout: 18000, maxBuffer: 256 * 1024 });
  const [rawHTTP, effectiveURL] = (result.stdout || '').trim().split('\n');
  const http = Number(rawHTTP) || null;
  const diagnostic = String(result.stderr || result.error?.message || '').slice(0, 500);
  if (result.status !== 0 || result.error) {
    const blocked = /CONNECT tunnel failed|proxy.*(?:403|denied|forbidden)|tunnel.*403/i.test(diagnostic);
    return { status: blocked ? 'blocked_by_environment_proxy' : 'network_error', http: null, effectiveURL: effectiveURL || url, diagnostic };
  }
  return { status: 'http', http, effectiveURL: effectiveURL || url };
}

async function auditPublicURLs() {
  const origin = new URL(process.env.TC_PUBLIC_URL || 'https://timonconfigs.com/');
  if (origin.protocol !== 'https:') throw new Error('Public audit requires an HTTPS origin.');
  if (origin.username || origin.password) throw new Error('Do not put credentials in the public audit URL.');
  const report = { startedAt: new Date().toISOString(), origin: origin.origin, routes: [], note: '', invite: null };
  const home = probe(origin.href);
  report.routes.push({ path: '/', ...home });
  if (home.status !== 'http') {
    report.note = `${home.status === 'blocked_by_environment_proxy' ? 'The environment proxy rejected the HTTPS CONNECT request.' : 'The origin could not be reached.'} Remaining routes were not attempted; their public HTTP status is unknown. No claim of public-site 404 or availability can be made.`;
    for (const route of ROUTES.slice(1)) report.routes.push({ path: route, status: 'not_checked_origin_unreachable', http: null });
  } else {
    for (const route of ROUTES.slice(1)) report.routes.push({ path: route, ...probe(new URL(route, origin).href) });
  }
  const invitation = probe('https://discord.com/api/v10/invites/RyvzSqrF6');
  report.invite = { ...invitation, note: invitation.status !== 'http' ? 'Invitation validity remains unverified because the API request could not complete.' : invitation.http === 200 ? 'The public invitation endpoint responds successfully; no membership or role was changed.' : 'The endpoint did not confirm an active invitation; retain an editable link in WordPress.' };
  await mkdir(artifactDirectory, { recursive: true });
  await writeFile(path.join(artifactDirectory, 'public-url-status.json'), JSON.stringify(report, null, 2));
  await writeStatusReport();
  console.log(JSON.stringify({ origin: report.origin, routes: report.routes.length, home: home.status === 'http' ? home.http : home.status, invite: report.invite.status, report: reportPath }));
}

module.exports = { ROUTES, artifactDirectory, writeStatusReport };
if (require.main === module) auditPublicURLs().catch(error => { console.error(error.message); process.exitCode = 1; });
