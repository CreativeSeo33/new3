#!/usr/bin/env node
/*
  Security audit generator based on existing inventory and optional OpenAPI/security configs.

  Inputs:
  - docs/inventory/server_endpoints.json
  - docs/inventory/client_calls.json
  - docs/inventory/mapping_server_client.json
  - var/openapi.json (optional)
  - var/openapi.yaml (optional, not parsed here)
  - config/packages/security.yaml (optional, parsed heuristically)
  - config/packages/nelmio_cors.yaml (optional, parsed heuristically)

  Outputs:
  - docs/inventory/server_endpoints.enriched.json
  - docs/security/checklist.json
  - docs/security/audit.json
  - docs/security/audit.md
  - docs/security/quickfix.md
*/

const fs = require('fs');
const path = require('path');

function ensureDir(dirPath) {
  if (!fs.existsSync(dirPath)) fs.mkdirSync(dirPath, { recursive: true });
}

function readJsonSafe(p) {
  try { return JSON.parse(fs.readFileSync(p, 'utf8')); } catch { return null; }
}

function writeJson(p, data) {
  fs.writeFileSync(p, JSON.stringify(data, null, 2), 'utf8');
}

function writeText(p, data) {
  fs.writeFileSync(p, data, 'utf8');
}

function sideEffects(method) {
  return ['POST','PUT','PATCH','DELETE'].includes(String(method).toUpperCase());
}

function inferSensitivity(pathStr) {
  const p = pathStr.toLowerCase();
  if (/(admin|users|user|orders|payments|payment|checkout|settings|search|media|pvz)/.test(p)) return 'high';
  if (/(cart|delivery|city|facets|attributes|options)/.test(p)) return 'medium';
  return 'low';
}

function inferAuthForEndpoint(ep, openapiIndexByKey, clientCalls) {
  // Priority: OpenAPI security -> client Authorization header -> cookie/csrf -> unknown
  const key = `${ep.method} ${ep.path}`;
  const op = openapiIndexByKey.get(key) || null;
  if (op && Array.isArray(op.security) && op.security.length) {
    const flat = JSON.stringify(op.security).toLowerCase();
    if (flat.includes('bearer')) return 'jwt';
    if (flat.includes('api_key')) return 'api_key';
  }
  // Client evidence
  const relatedCalls = clientCalls.filter(c => (c.matched && c.matched_path === ep.path && c.matched_method === ep.method));
  const headers = new Set();
  for (const c of relatedCalls) (c.headers_detected||[]).forEach(h => headers.add(String(h)));
  const headerStr = Array.from(headers).join(',').toLowerCase();
  if (headerStr.includes('authorization')) return 'jwt';
  if (headerStr.includes('x-csrf-token')) return 'cookie';

  // Heuristics by path
  if (/^\/api\//.test(ep.path)) return 'unknown';
  return 'public';
}

function parseSecurityYamlHeuristic(content) {
  const accessControls = [];
  const acRegex = /-\s*\{\s*([^}]+)\}/g;
  let m;
  while ((m = acRegex.exec(content))) {
    const body = m[1];
    const kvs = body.split(',').map(s => s.trim());
    const item = {};
    for (const kv of kvs) {
      const mm = kv.match(/(\w+)\s*:\s*(.+)/);
      if (!mm) continue;
      const k = mm[1];
      const v = mm[2].trim();
      item[k] = v;
    }
    if (item.path && item.roles) {
      accessControls.push({ path: item.path, roles: item.roles });
    }
  }

  const fw = [];
  // Extract api firewall block only (common case)
  const fb = content.match(/\n\s*api:\s*\n([\s\S]*?)(?:\n\s*\w+:|$)/);
  if (fb) {
    const block = fb[1];
    const patternM = block.match(/\bpattern:\s*(.+)/);
    const securityM = block.match(/\bsecurity:\s*(true|false)/);
    if (patternM) {
      fw.push({ name: 'api', pattern: patternM[1].trim(), security: securityM ? securityM[1] === 'true' : undefined });
    }
  }
  return { accessControls, firewalls: fw };
}

function parseCorsYamlHeuristic(content) {
  const originRegexM = content.match(/origin_regex:\s*(true|false)/);
  const allowOriginBlock = content.match(/allow_origin:\s*\[(.*?)\]/);
  const allowCredsM = content.match(/allow_credentials:\s*(true|false)/);
  const allow_origin = allowOriginBlock ? allowOriginBlock[1].split(',').map(s => s.trim().replace(/^'|"|\s+|['"]$/g, '')) : [];
  const origin_regex = originRegexM ? originRegexM[1] === 'true' : false;
  const allow_credentials = allowCredsM ? allowCredsM[1] === 'true' : false;
  return { allow_origin, origin_regex, allow_credentials };
}

function loadOpenApiIndex(projectRoot) {
  const openapiPath = path.join(projectRoot, 'var', 'openapi.json');
  const oa = readJsonSafe(openapiPath);
  const map = new Map();
  if (oa && oa.paths) {
    for (const [p, item] of Object.entries(oa.paths)) {
      for (const m of ['get','post','put','patch','delete','head','options']) {
        const op = item[m];
        if (!op) continue;
        const upper = m.toUpperCase();
        map.set(`${upper} ${p}`, { path: p, method: upper, security: op.security || null, operationId: op.operationId || null });
      }
    }
  }
  return map;
}

function enrichEndpoints(serverEndpoints, openapiIndex, clientCalls, securityCfg) {
  function matchAccessControl(pathStr) {
    for (const ac of securityCfg.accessControls || []) {
      try {
        const re = new RegExp(ac.path);
        if (re.test(pathStr)) return ac;
      } catch {}
    }
    return null;
  }
  function matchFirewall(pathStr) {
    for (const f of securityCfg.firewalls || []) {
      try {
        const re = new RegExp(f.pattern);
        if (re.test(pathStr)) return f;
      } catch {}
    }
    return null;
  }

  return serverEndpoints.map(e => {
    let auth = inferAuthForEndpoint(e, openapiIndex, clientCalls);
    // Override by security.yaml if matches
    const ac = matchAccessControl(e.path);
    if (ac) {
      if (/PUBLIC_ACCESS/.test(ac.roles)) auth = 'public';
      else auth = 'cookie';
    } else {
      const fw = matchFirewall(e.path);
      if (fw && fw.security === false) auth = 'public';
    }
    const sensitivity = inferSensitivity(e.path);
    const side_effects = sideEffects(e.method);
    const rate_limited = (/(login|token|refresh|register)/i.test(e.path)) ? 'unknown' : 'unknown';
    const missing_in_openapi = !!e.missing_in_openapi;
    return {
      ...e,
      auth,
      sensitivity,
      side_effects,
      rate_limited,
      missing_in_openapi,
    };
  });
}

function buildChecklist() {
  return [
    {
      check_id: 'AUTH_REQUIRED_NON_SAFE_METHODS',
      title: 'Небезопасные методы требуют аутентификации',
      category: 'Authentication',
      severity_if_fail: 'high',
      how_to_decide: 'operation.security present OR firewall protects path; method in [POST,PUT,PATCH,DELETE]',
      evidence_fields: ['method','path','auth','openapi.security']
    },
    {
      check_id: 'AUTH_PRESENT_FOR_SENSITIVE',
      title: 'Чувствительные эндпоинты не публичны',
      category: 'Authentication',
      severity_if_fail: 'high',
      how_to_decide: 'sensitivity in [high] => auth != public',
      evidence_fields: ['path','sensitivity','auth']
    },
    {
      check_id: 'IDOR_OWNER_CHECK',
      title: 'Проверка владельца/ролей для путей с {id}',
      category: 'Authorization',
      severity_if_fail: 'high',
      how_to_decide: 'path has {id} and not low sensitivity => require voter/IsGranted/security expression',
      evidence_fields: ['path','sensitivity']
    },
    {
      check_id: 'PAGINATION_LIMITS',
      title: 'Коллекции имеют пагинацию и верхние пределы',
      category: 'Resource Limits',
      severity_if_fail: 'medium',
      how_to_decide: 'collection paths expose page/itemsPerPage and have maximum limits (OpenAPI/config)',
      evidence_fields: ['path','openapi.parameters']
    },
    {
      check_id: 'CORS_CREDENTIALS_ORIGINS',
      title: 'CORS не допускает * с credentials: true',
      category: 'CORS',
      severity_if_fail: 'high',
      how_to_decide: 'nelmio_cors.yaml allow_origin != * if allow_credentials: true',
      evidence_fields: ['cors_config']
    },
    {
      check_id: 'CONTENT_TYPE_FOR_JSON',
      title: 'JSON-запросы отправляются с Content-Type: application/json',
      category: 'Client Calls',
      severity_if_fail: 'low',
      how_to_decide: 'client_calls with body require Content-Type header',
      evidence_fields: ['client_calls.headers_detected']
    }
  ];
}

function decideCheck(ep, checklistItem, ctx) {
  const method = ep.method;
  const pathStr = ep.path;
  const sens = ep.sensitivity;
  const auth = ep.auth;
  const openapiIndex = ctx.openapiIndex;
  const clientCalls = ctx.clientCalls;

  switch (checklistItem.check_id) {
    case 'AUTH_REQUIRED_NON_SAFE_METHODS': {
      if (sideEffects(method)) {
        if (auth && auth !== 'public' && auth !== 'unknown') return { status: 'pass', evidence: { auth } };
        const op = openapiIndex.get(`${method} ${pathStr}`);
        if (op && Array.isArray(op.security) && op.security.length) return { status: 'pass', evidence: { openapi_security: op.security } };
        return { status: auth === 'unknown' ? 'unknown' : 'fail', evidence: { auth, method } };
      }
      return { status: 'pass', evidence: { method } };
    }
    case 'AUTH_PRESENT_FOR_SENSITIVE': {
      if (sens === 'high' && (auth === 'public' || auth === 'unknown')) {
        return { status: auth === 'unknown' ? 'unknown' : 'fail', evidence: { sensitivity: sens, auth } };
      }
      return { status: 'pass', evidence: { sensitivity: sens, auth } };
    }
    case 'IDOR_OWNER_CHECK': {
      if (sens !== 'low' && /\{id\}/.test(pathStr)) {
        // Static guess only – without code examples we mark unknown
        return { status: 'unknown', evidence: { path_has_id_param: true, sensitivity: sens } };
      }
      return { status: 'pass', evidence: {} };
    }
    case 'PAGINATION_LIMITS': {
      if (/\{id\}/.test(pathStr)) return { status: 'pass', evidence: { reason: 'item endpoint' } };
      // Try to detect collection by plural nouns / lack of {id}
      const looksCollection = /\/api\//.test(pathStr) && !/\{id\}/.test(pathStr);
      if (looksCollection) {
        const op = openapiIndex.get(`${method} ${pathStr}`);
        if (op && op.security !== undefined) {
          // Use presence of parameters as a proxy (heuristic)
          return { status: 'unknown', evidence: { openapi_present: true } };
        }
        return { status: 'unknown', evidence: { openapi_present: false } };
      }
      return { status: 'pass', evidence: {} };
    }
    case 'CORS_CREDENTIALS_ORIGINS': {
      const cors = ctx.corsConfig || {};
      if (cors.allow_credentials === true) {
        const hasWildcard = (cors.allow_origin || []).some(o => o === '*' || o === '.*');
        if (hasWildcard) return { status: 'fail', evidence: { allow_origin: cors.allow_origin, allow_credentials: true } };
        return { status: 'pass', evidence: { allow_origin: cors.allow_origin, allow_credentials: true } };
      }
      // credentials not enabled => safe
      return { status: 'pass', evidence: { allow_credentials: false } };
    }
    case 'CONTENT_TYPE_FOR_JSON': {
      const calls = clientCalls.filter(c => (c.matched_path === pathStr && c.matched_method === method && ['POST','PUT','PATCH','DELETE'].includes(c.client_method)));
      const bad = calls.filter(c => c.body_type !== 'none' && !(c.headers_detected||[]).some(h => /content-type/i.test(h)));
      if (bad.length > 0) return { status: 'fail', evidence: { refs: bad.map(b => `${b.file}:${b.line}`) } };
      return { status: 'pass', evidence: {} };
    }
  }
  return { status: 'unknown', evidence: {} };
}

function severityWithSensitivity(baseSeverity, sensitivity) {
  if (!baseSeverity) return 'low';
  if (sensitivity === 'high') {
    if (baseSeverity === 'medium') return 'high';
    if (baseSeverity === 'low') return 'medium';
  }
  return baseSeverity;
}

function recommendForCheck(checkId, ep) {
  switch (checkId) {
    case 'AUTH_REQUIRED_NON_SAFE_METHODS':
      return 'Добавить security (OpenAPI) и/или защиту firewall; для API Platform — security/securityPostDenormalize.';
    case 'AUTH_PRESENT_FOR_SENSITIVE':
      return 'Сделать эндпоинт требующим аутентификацию (bearer/jwt или cookie-сессия).';
    case 'IDOR_OWNER_CHECK':
      return 'Добавить voter/#[IsGranted] или выражение security: object.owner == user.';
    case 'PAGINATION_LIMITS':
      return 'Включить pagination и максимальные лимиты (API Platform defaults и maximumItemsPerPage).';
    case 'CORS_CREDENTIALS_ORIGINS':
      return 'Настроить nelmio_cors: allow_credentials: true с явным allow_origin (не *).';
    case 'CONTENT_TYPE_FOR_JSON':
      return 'Добавить Content-Type: application/json для запросов с телом.';
  }
  return null;
}

function overallStatus(checks) {
  const hasFailHigh = checks.some(c => c.status === 'fail' && c.severity === 'high');
  if (hasFailHigh) return 'fail';
  const hasUnknownHigh = checks.some(c => c.status === 'unknown' && c.severity === 'high');
  if (hasUnknownHigh) return 'attention';
  return 'pass';
}

function main() {
  const projectRoot = path.resolve(__dirname, '../../');
  const invDir = path.join(projectRoot, 'docs', 'inventory');
  const secDir = path.join(projectRoot, 'docs', 'security');
  ensureDir(secDir);

  const serverEndpoints = readJsonSafe(path.join(invDir, 'server_endpoints.json')) || [];
  const clientCalls = readJsonSafe(path.join(invDir, 'client_calls.json')) || [];

  const openapiIndex = loadOpenApiIndex(projectRoot);
  // configs
  const secYamlPath = path.join(projectRoot, 'config', 'packages', 'security.yaml');
  const corsYamlPath = path.join(projectRoot, 'config', 'packages', 'nelmio_cors.yaml');
  const securityYaml = fs.existsSync(secYamlPath) ? fs.readFileSync(secYamlPath, 'utf8') : '';
  const corsYaml = fs.existsSync(corsYamlPath) ? fs.readFileSync(corsYamlPath, 'utf8') : '';
  const securityCfg = securityYaml ? parseSecurityYamlHeuristic(securityYaml) : { accessControls: [], firewalls: [] };
  const corsCfg = corsYaml ? parseCorsYamlHeuristic(corsYaml) : { allow_origin: [], origin_regex: false, allow_credentials: false };

  // Enrich
  const enriched = enrichEndpoints(serverEndpoints, openapiIndex, clientCalls, securityCfg);
  writeJson(path.join(invDir, 'server_endpoints.enriched.json'), enriched);

  // Checklist
  const checklist = buildChecklist();
  writeJson(path.join(secDir, 'checklist.json'), checklist);

  // Run audit
  const audit = [];
  for (const ep of enriched) {
    const checks = [];
    for (const item of checklist) {
      const r = decideCheck(ep, item, { openapiIndex, clientCalls, corsConfig: corsCfg, securityConfig: securityCfg });
      const severity = severityWithSensitivity(item.severity_if_fail, ep.sensitivity);
      checks.push({
        check_id: item.check_id,
        status: r.status,
        severity,
        evidence: r.evidence,
        recommendation: r.status === 'pass' ? null : recommendForCheck(item.check_id, ep),
      });
    }
    const overall = overallStatus(checks);
    audit.push({
      endpoint: {
        method: ep.method,
        path: ep.path,
        route_name: ep.route_name,
        auth: ep.auth,
        sensitivity: ep.sensitivity,
        source: ep.source,
      },
      checks,
      overall,
    });
  }
  writeJson(path.join(secDir, 'audit.json'), audit);

  // Audit MD summary
  const total = audit.length;
  const failHigh = audit.filter(a => a.checks.some(c => c.status === 'fail' && c.severity === 'high'));
  const unknownHigh = audit.filter(a => a.checks.some(c => c.status === 'unknown' && c.severity === 'high'));
  const lines = [];
  lines.push('### API Security Audit');
  lines.push('');
  lines.push(`- Endpoints: ${total}`);
  lines.push(`- High FAIL: ${failHigh.length}`);
  lines.push(`- High UNKNOWN: ${unknownHigh.length}`);
  lines.push('');
  lines.push('### High priority');
  lines.push('');
  const topList = [...failHigh, ...unknownHigh].slice(0, 100);
  for (const a of topList) {
    const issues = a.checks.filter(c => (c.status !== 'pass' && c.severity === 'high'));
    const what = issues.map(c => `${c.check_id} (${c.status})`).join(', ');
    lines.push(`- ${a.endpoint.method} ${a.endpoint.path} — ${what}`);
  }
  lines.push('');
  lines.push('### Findings by category');
  const byCat = {};
  for (const a of audit) {
    for (const c of a.checks) {
      if (c.status === 'pass') continue;
      const cat = c.check_id.split('_')[0];
      (byCat[cat] ||= []).push({ ep: a.endpoint, c });
    }
  }
  for (const [cat, list] of Object.entries(byCat)) {
    lines.push('');
    lines.push(`- ${cat}`);
    for (const it of list.slice(0, 200)) {
      lines.push(`  - ${it.ep.method} ${it.ep.path}: ${it.c.check_id} → ${it.c.status}`);
    }
  }
  writeText(path.join(secDir, 'audit.md'), lines.join('\n'));

  // Extra report: public + unsafe methods
  const publicUnsafe = enriched.filter(e => e.side_effects && e.auth === 'public');
  const pmd = [];
  pmd.push('### Public endpoints with unsafe methods');
  pmd.push('');
  for (const e of publicUnsafe) pmd.push(`- ${e.method} ${e.path} (${e.route_name})`);
  writeText(path.join(secDir, 'public_unsafe.md'), pmd.join('\n'));

  // Extra report: api_platform endpoints only
  const apiPlatformOnly = enriched.filter(e => e.source === 'api_platform');
  const amd = [];
  amd.push('### API Platform endpoints');
  amd.push('');
  for (const e of apiPlatformOnly) amd.push(`- ${e.method} ${e.path} [${e.auth}]`);
  writeText(path.join(secDir, 'api_platform.md'), amd.join('\n'));

  // Quick fixes
  const qf = [];
  qf.push('### Quick Fixes');
  qf.push('');
  qf.push('- API Platform: добавить security/securityPostDenormalize, ограничить paginationItemsPerPage/maximumItemsPerPage.');
  qf.push('- Контроллеры: #[IsGranted(\'ROLE_USER\')] и voter на владение; явно указывать methods в #[Route].');
  qf.push('- CORS: явный allow_origin и allow_credentials: true, без * при куки.');
  qf.push('- Cookies: Secure, HttpOnly, SameSite=Lax|Strict.');
  qf.push('- Клиент: Content-Type: application/json для запросов с телом, Authorization: Bearer для защищённых.');
  writeText(path.join(secDir, 'quickfix.md'), qf.join('\n'));

  console.log('Security audit generated into docs/security and inventory enrichment updated.');
}

main();


