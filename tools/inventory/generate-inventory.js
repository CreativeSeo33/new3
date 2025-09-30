#!/usr/bin/env node
/*
  Inventory generator for server endpoints (Symfony Router + API Platform OpenAPI)
  and client HTTP calls (Vue/TS in assets/**).

  Outputs:
  - docs/inventory/server_endpoints.json
  - docs/inventory/client_calls.json
  - docs/inventory/mapping_server_client.json
  - docs/inventory/server_endpoints.md
  - docs/inventory/client_calls.md
  - docs/inventory/coverage.md
  - docs/inventory/mapping_server_client.csv
*/

const fs = require('fs');
const path = require('path');

// ---------- Helpers ----------
function readTextAuto(filePath) {
  const buf = fs.readFileSync(filePath);
  if (buf.length >= 2 && buf[0] === 0xFF && buf[1] === 0xFE) {
    // UTF-16LE BOM
    return buf.toString('utf16le');
  }
  if (buf.length >= 3 && buf[0] === 0xEF && buf[1] === 0xBB && buf[2] === 0xBF) {
    // UTF-8 BOM
    return buf.slice(3).toString('utf8');
  }
  // Heuristic: many NUL bytes -> UTF-16LE
  const nulCount = Math.min(buf.length, 2000);
  let zeros = 0;
  for (let i = 0; i < nulCount; i++) if (buf[i] === 0) zeros++;
  if (zeros > nulCount * 0.3) {
    return buf.toString('utf16le');
  }
  return buf.toString('utf8');
}

function readJsonSafe(filePath) {
  try {
    let raw = readTextAuto(filePath);
    raw = raw.replace(/^\uFEFF/, '');
    return JSON.parse(raw);
  } catch (e) {
    try { console.error(`[json-read-error] ${filePath}: ${e && e.message ? e.message : e}`); } catch {}
    return null;
  }
}

function ensureDir(dirPath) {
  if (!fs.existsSync(dirPath)) {
    fs.mkdirSync(dirPath, { recursive: true });
  }
}

function isPlainObject(v) {
  return v && typeof v === 'object' && !Array.isArray(v);
}

function uniqBy(arr, keyFn) {
  const map = new Map();
  for (const item of arr) {
    const key = keyFn(item);
    if (!map.has(key)) map.set(key, item);
  }
  return Array.from(map.values());
}

function sanitizeMd(str) {
  return String(str ?? '').replace(/\r?\n/g, ' ').replace(/\|/g, '/');
}

function toCsvField(s) {
  const v = String(s ?? '');
  if (v.includes(',') || v.includes('"') || v.includes('\n')) {
    return '"' + v.replace(/"/g, '""') + '"';
  }
  return v;
}

function writeJson(filePath, data) {
  fs.writeFileSync(filePath, JSON.stringify(data, null, 2), 'utf8');
}

function writeText(filePath, data) {
  fs.writeFileSync(filePath, data, 'utf8');
}

function listFilesRecursive(dir, filterFn) {
  const out = [];
  if (!fs.existsSync(dir)) return out;
  const stack = [dir];
  while (stack.length) {
    const cur = stack.pop();
    const entries = fs.readdirSync(cur, { withFileTypes: true });
    for (const e of entries) {
      const full = path.join(cur, e.name);
      if (e.isDirectory()) {
        // Skip heavy/irrelevant dirs
        if (/node_modules|vendor|public|build|dist/.test(full)) continue;
        stack.push(full);
      } else {
        if (!filterFn || filterFn(full)) out.push(full);
      }
    }
  }
  return out;
}

function getLineNumberFromIndex(content, index) {
  // lines start at 1
  let line = 1;
  let i = 0;
  while (true) {
    const pos = content.indexOf('\n', i);
    if (pos === -1 || pos >= index) break;
    line += 1;
    i = pos + 1;
  }
  return line;
}

function compilePathRegex(pathTemplate) {
  // Convert Symfony-style path "/api/orders/{id}" to a matching regex
  const escaped = pathTemplate
    .replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    .replace(/\\\{[^}]+\\\}/g, '[^/]+');
  // optional trailing slash
  return new RegExp('^' + escaped + '/?$', 'i');
}

function guessSourceFromRoute({ route_name, path, hasOpenApiMatch }) {
  const rn = String(route_name || '').toLowerCase();
  const p = String(path || '');

  if (hasOpenApiMatch || rn.startsWith('api_')) return 'api_platform';
  if (/webhook/.test(rn) || /\/webhook(\/|$)/.test(p)) return 'webhook';
  if (/^\/_/.test(p) || rn.startsWith('debug_') || rn.includes('profiler') || rn.includes('wdt')) return 'internal|debug';
  return 'custom_controller';
}

function extractSchemaRefFromContent(content) {
  if (!content || typeof content !== 'object') return null;
  const mimeOrder = [
    'application/ld+json',
    'application/json',
    'text/plain',
    'text/html',
    'multipart/form-data',
  ];
  for (const mime of mimeOrder) {
    const item = content[mime];
    if (item && item.schema) {
      if (typeof item.schema.$ref === 'string') return item.schema.$ref;
      // handle array schema { type: 'array', items: { $ref: ... } }
      if (item.schema.items && item.schema.items.$ref) return item.schema.items.$ref;
      return null;
    }
  }
  // fallback: first available
  const first = Object.values(content)[0];
  if (first && first.schema) {
    return first.schema.$ref || null;
  }
  return null;
}

// ---------- Load inputs ----------
// Resolve project root relative to this script to avoid cwd issues
const projectRoot = path.resolve(__dirname, '../../');
const routerJsonPath = path.join(projectRoot, 'var', 'router.json');
const openapiJsonPath = path.join(projectRoot, 'var', 'openapi.json');
const openapiYamlPath = path.join(projectRoot, 'var', 'openapi.yaml');

const routerJson = readJsonSafe(routerJsonPath);
if (!routerJson) {
  const exists = fs.existsSync(routerJsonPath);
  console.error(`Router JSON load failed. exists=${exists} path=${routerJsonPath}`);
}
let openapi = readJsonSafe(openapiJsonPath);
if (!openapi && fs.existsSync(openapiYamlPath)) {
  // Optional: try to read YAML as plain text to check non-emptiness, but skip parsing without js-yaml
  try {
    const yraw = fs.readFileSync(openapiYamlPath, 'utf8');
    if (yraw && yraw.trim().length > 0) {
      console.warn('[warn] openapi.yaml detected but YAML parsing is not enabled; continuing without OpenAPI enrichment.');
    }
  } catch {}
}

if (!routerJson) {
  console.error('Missing or invalid var/router.json. Generate with: bin/console debug:router --format=json > var/router.json');
  process.exit(2);
}

// OpenAPI is optional; if missing or invalid, continue with router-only mode
if (!openapi) {
  console.warn('[warn] Missing or invalid OpenAPI (var/openapi.json). Proceeding without OpenAPI enrichment.');
}

// ---------- Parse OpenAPI ----------
const openapiPaths = new Map(); // key: METHOD PATH, value: openapi op data
const openapiIndexByPath = new Map(); // key: path, value: methods map

if (openapi && isPlainObject(openapi.paths)) {
  for (const [opPath, pathItem] of Object.entries(openapi.paths)) {
    const methods = {};
    for (const method of ['get', 'post', 'put', 'patch', 'delete', 'options', 'head']) {
      const op = pathItem[method];
      if (!op) continue;
      const upper = method.toUpperCase();
      const parameters = [];
      if (Array.isArray(pathItem.parameters)) parameters.push(...pathItem.parameters);
      if (Array.isArray(op.parameters)) parameters.push(...op.parameters);

      const pathParams = parameters.filter(p => p.in === 'path').map(p => p.name);
      const queryParams = parameters.filter(p => p.in === 'query').map(p => p.name);
      let requestBodySchemaRef = null;
      if (op.requestBody && op.requestBody.content) {
        requestBodySchemaRef = extractSchemaRefFromContent(op.requestBody.content);
      }
      let responseSchemaRef = null;
      if (op.responses && isPlainObject(op.responses)) {
        const preferred = ['200', '201', '202', '204'];
        for (const code of preferred) {
          if (op.responses[code] && op.responses[code].content) {
            responseSchemaRef = extractSchemaRefFromContent(op.responses[code].content);
            if (responseSchemaRef) break;
          }
        }
        if (!responseSchemaRef) {
          // fallback to first
          const firstResp = Object.values(op.responses)[0];
          if (firstResp && firstResp.content) {
            responseSchemaRef = extractSchemaRefFromContent(firstResp.content);
          }
        }
      }

      const entry = {
        method: upper,
        path: opPath,
        openapi_operation_id: op.operationId || null,
        api_resource: (Array.isArray(op.tags) && op.tags.length ? op.tags[0] : null),
        api_operation_name: op['x-operation-name'] || null,
        path_params: pathParams,
        query_params: queryParams,
        request_body_schema_ref: requestBodySchemaRef || null,
        response_schema_ref: responseSchemaRef || null,
      };
      openapiPaths.set(`${upper} ${opPath}`, entry);
      if (!openapiIndexByPath.has(opPath)) openapiIndexByPath.set(opPath, new Set());
      openapiIndexByPath.get(opPath).add(upper);
    }
  }
}

// ---------- Parse Router ----------
function normalizeRouterJson(json) {
  // Prefer format: { routeName: { path, methods, controller, ... }, ... }
  if (isPlainObject(json)) {
    // Heuristic: detect if keys look like routes
    const keys = Object.keys(json);
    const sample = json[keys[0]];
    if (sample && (sample.path || sample.controller || sample.methods || sample.method)) {
      return json;
    }
  }
  // Alternative formats can be added here if needed
  return {};
}

const routerIndex = normalizeRouterJson(routerJson);

const serverEndpoints = [];
for (const [route_name, data] of Object.entries(routerIndex)) {
  const routePath = data.path || data.pathRegex || null;
  if (!routePath) continue;
  let methods = Array.isArray(data.methods) && data.methods.length ? data.methods : null;
  if (!methods && typeof data.method === 'string' && data.method.length > 0) {
    methods = data.method.split('|').map(s => s.trim()).filter(Boolean);
  }
  if (!methods || methods.length === 0) methods = ['GET'];
  const controllerStr = data.controller || (data.defaults && data.defaults._controller) || null;
  let controller_class = null;
  let controller_action = null;
  if (typeof controllerStr === 'string') {
    if (controllerStr.includes('::')) {
      const [cls, act] = controllerStr.split('::');
      controller_class = cls || null;
      controller_action = act || null;
    } else {
      controller_class = controllerStr;
      controller_action = '__invoke';
    }
  }

  for (const m of methods) {
    const upper = String(m).toUpperCase();
    const oa = openapiPaths.get(`${upper} ${routePath}`) || null;
    const hasOpenApiMatch = !!oa;
    const source = guessSourceFromRoute({ route_name, path: routePath, hasOpenApiMatch });
    const entry = {
      method: upper,
      path: routePath,
      route_name,
      source,
      controller_class,
      controller_action,
      openapi_operation_id: oa ? oa.openapi_operation_id : null,
      api_resource: oa ? oa.api_resource : null,
      api_operation_name: oa ? oa.api_operation_name : null,
      path_params: oa ? oa.path_params : [],
      query_params: oa ? oa.query_params : [],
      request_body_schema_ref: oa ? oa.request_body_schema_ref : null,
      response_schema_ref: oa ? oa.response_schema_ref : null,
      missing_in_openapi: !hasOpenApiMatch && String(routePath).startsWith('/api') ? true : false,
    };
    serverEndpoints.push(entry);
  }
}

// Deduplicate by (method, path, route_name)
const serverEndpointsDedup = uniqBy(serverEndpoints, e => `${e.method} ${e.path} ${e.route_name}`)
  .sort((a, b) => {
    if (a.source !== b.source) return a.source.localeCompare(b.source);
    if (a.path !== b.path) return a.path.localeCompare(b.path);
    return a.method.localeCompare(b.method);
  });

// Build fast index for mapping
const serverIndex = serverEndpointsDedup.map(e => ({
  ...e,
  _pathRegex: compilePathRegex(e.path)
}));

// ---------- Scan Client Calls ----------
const clientCalls = [];

function pushClientCall(call) {
  // Normalize minimal fields
  clientCalls.push({
    file: call.file,
    line: call.line,
    client_lib: call.client_lib,
    client_method: call.client_method,
    url_template: call.url_template,
    headers_detected: Array.from(new Set(call.headers_detected || [])),
    body_type: call.body_type || 'none',
    via: call.via || 'unknown',
  });
}

const assetRoots = [
  path.join(projectRoot, 'assets', 'admin'),
  path.join(projectRoot, 'assets', 'catalog'),
  path.join(projectRoot, 'assets', 'controllers'),
];

const codeFiles = assetRoots.flatMap(dir => listFilesRecursive(dir, (f) => /\.(ts|js|vue)$/.test(f)));

for (const file of codeFiles) {
  let content = '';
  try { content = fs.readFileSync(file, 'utf8'); } catch { continue; }

  // --- Detect fetch() calls ---
  {
    const fetchRe = /fetch\s*\(\s*([`'\"])([\s\S]*?)\1\s*(,\s*\{[\s\S]*?\})?\s*\)/g;
    let m;
    while ((m = fetchRe.exec(content))) {
      const fullIndex = m.index;
      const line = getLineNumberFromIndex(content, fullIndex);
      const url = (m[2] || '').trim();
      const optionsStr = m[3] || '';
      let method = 'GET';
      let headers = [];
      let bodyType = 'none';
      if (optionsStr) {
        const methodM = optionsStr.match(/method\s*:\s*([`'\"])(GET|POST|PUT|PATCH|DELETE|OPTIONS|HEAD)\1/i);
        if (methodM) method = methodM[2].toUpperCase();
        const hdrs = [];
        if (/Authorization/i.test(optionsStr)) hdrs.push('Authorization');
        if (/Content-Type/i.test(optionsStr)) hdrs.push('Content-Type');
        if (/X-CSRF-Token/i.test(optionsStr)) hdrs.push('X-CSRF-Token');
        if (/X-Requested-With/i.test(optionsStr)) hdrs.push('X-Requested-With');
        if (/Accept/i.test(optionsStr)) hdrs.push('Accept');
        headers = hdrs;
        if (/FormData\s*\(/.test(optionsStr)) bodyType = 'multipart';
        else if (/JSON\.stringify\s*\(/.test(optionsStr)) bodyType = 'json';
        else if (/body\s*:/.test(optionsStr)) bodyType = 'json';
      }

      pushClientCall({
        file: path.relative(projectRoot, file),
        line,
        client_lib: 'fetch',
        client_method: method,
        url_template: url,
        headers_detected: headers,
        body_type: bodyType,
        via: undefined,
      });
    }
  }

  // --- Detect axios.method(...) direct calls ---
  {
    const axiosRe = /axios\.(get|post|put|patch|delete)\s*\(\s*([`'\"])([\s\S]*?)\2/gi;
    let m;
    while ((m = axiosRe.exec(content))) {
      const line = getLineNumberFromIndex(content, m.index);
      const method = m[1].toUpperCase();
      const url = (m[3] || '').trim();
      pushClientCall({
        file: path.relative(projectRoot, file),
        line,
        client_lib: 'axios',
        client_method: method,
        url_template: url,
        headers_detected: [],
        body_type: ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method) ? 'json' : 'none',
        via: undefined,
      });
    }
  }

  // --- Detect @shared/api/http imports and calls ---
  {
    const importRe = /import\s+\{([^}]+)\}\s+from\s+['\"]@shared\/api\/http['\"]/;
    const importM = content.match(importRe);
    if (importM) {
      const imported = importM[1]
        .split(',')
        .map(s => s.trim())
        .filter(Boolean)
        .map(s => {
          // handle alias: get as httpGet
          const mm = s.match(/(\w+)\s+as\s+(\w+)/i);
          if (mm) return { name: mm[1], alias: mm[2] };
          return { name: s, alias: s };
        });
      const aliasMap = new Map(imported.map(x => [x.alias, x.name]));
      const callNames = Array.from(aliasMap.keys());
      if (callNames.length) {
        const namesRe = new RegExp(`\\b(${callNames.join('|')})\\s*\\(\\s*([` + "'\"" + `])([\\s\\S]*?)\\2`, 'g');
        let m;
        while ((m = namesRe.exec(content))) {
          const alias = m[1];
          const orig = aliasMap.get(alias);
          if (!orig) continue;
          const url = (m[3] || '').trim();
          let method = 'GET';
          if (orig === 'get') method = 'GET';
          else if (orig === 'post') method = 'POST';
          else if (orig === 'patch') method = 'PATCH';
          else if (orig === 'del' || orig === 'delWithStatus') method = 'DELETE';
          else if (orig === 'http') {
            // Try to infer from options following...
            // We search ahead up to next ')' for method:
            const tail = content.slice(m.index, m.index + 300);
            const mm = tail.match(/method\s*:\s*([`'\"])(GET|POST|PUT|PATCH|DELETE|OPTIONS|HEAD)\1/i);
            if (mm) method = mm[2].toUpperCase();
          }
          const line = getLineNumberFromIndex(content, m.index);
          pushClientCall({
            file: path.relative(projectRoot, file),
            line,
            client_lib: orig === 'http' ? 'fetch' : 'fetch',
            client_method: method,
            url_template: url,
            headers_detected: [],
            body_type: ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method) ? 'json' : 'none',
            via: undefined,
          });
        }
      }
    }
  }

  // --- Detect Admin Repositories resourcePath (derive endpoints) ---
  if (file.includes(path.sep + 'assets' + path.sep + 'admin' + path.sep + 'repositories' + path.sep)) {
    // super('/products')
    const superRe = /super\s*\(\s*([`'\"])(\/[\w\-\/{}]+)\1\s*\)/g;
    let m;
    while ((m = superRe.exec(content))) {
      const resourcePath = m[2];
      const line = getLineNumberFromIndex(content, m.index);
      // Derive standard CRUD endpoints used by BaseRepository
      const baseFile = path.relative(projectRoot, file);
      const urlTemplates = [
        { method: 'GET', url: `${resourcePath}` },
        { method: 'GET', url: `${resourcePath}/{id}` },
        { method: 'POST', url: `${resourcePath}` },
        { method: 'PUT', url: `${resourcePath}/{id}` },
        { method: 'PATCH', url: `${resourcePath}/{id}` },
        { method: 'DELETE', url: `${resourcePath}/{id}` },
      ];
      for (const ut of urlTemplates) {
        pushClientCall({
          file: baseFile,
          line,
          client_lib: 'axios',
          client_method: ut.method,
          url_template: ut.url,
          headers_detected: ['Authorization', 'Content-Type', 'Accept'],
          body_type: ['POST', 'PUT', 'PATCH', 'DELETE'].includes(ut.method) ? 'json' : 'none',
          via: undefined,
        });
      }
    }
  }
}

// Classify client calls via
function classifyClientVia(url) {
  const u = String(url || '');
  if (/^https?:\/\//i.test(u)) {
    // drop origin
    try {
      const p = new URL(u);
      return p.pathname.startsWith('/api') ? 'api_platform_path' : 'custom_ajax';
    } catch { /* ignore */ }
  }
  if (u.startsWith('/api/')) return 'api_platform_path';
  if (/^\/(admin|internal|webhook)\b/.test(u)) return 'custom_ajax';
  return 'unknown';
}

for (const c of clientCalls) {
  c.via = classifyClientVia(c.url_template);
}

// ---------- Map client -> server ----------
function normalizeClientPath(url) {
  if (!url) return '';
  let p = String(url);
  // Remove template expressions like ${...}
  p = p.replace(/\$\{[^}]+\}/g, '{param}');
  // Remove query string
  p = p.split('?')[0];
  // Remove origin
  try {
    if (/^https?:\/\//i.test(p)) {
      const u = new URL(p);
      p = u.pathname;
    }
  } catch { /* ignore */ }
  return p;
}

function matchClientToServer(client) {
  const pathOnly = normalizeClientPath(client.url_template);
  const method = (client.client_method || 'GET').toUpperCase();
  for (const s of serverIndex) {
    if (s.method !== method) continue;
    if (s._pathRegex.test(pathOnly)) {
      return { matched: true, matched_route_name: s.route_name, matched_path: s.path, matched_method: s.method };
    }
  }
  // Fallback: try ignoring method
  for (const s of serverIndex) {
    if (s._pathRegex.test(pathOnly)) {
      return { matched: true, matched_route_name: s.route_name, matched_path: s.path, matched_method: s.method };
    }
  }
  return { matched: false };
}

const clientCallsMapped = clientCalls.map(c => ({ ...c, ...matchClientToServer(c) }));

// Aggregate per server endpoint
const mappingServerClient = [];
for (const s of serverEndpointsDedup) {
  const sRegex = compilePathRegex(s.path);
  const refs = clientCallsMapped.filter(c => (c.matched && c.matched_path === s.path && c.matched_method === s.method) || (!c.matched && sRegex.test(normalizeClientPath(c.url_template)) && (c.client_method || 'GET').toUpperCase() === s.method));
  const isCalled = refs.length > 0;
  mappingServerClient.push({
    server_endpoint: {
      method: s.method,
      path: s.path,
      route_name: s.route_name,
      source: s.source,
    },
    is_called_by_client: isCalled,
    client_refs: refs.map(r => ({ file: r.file, line: r.line, client_method: r.client_method, url_template: r.url_template }))
  });
}

// Orphans and unused
const orphanClientCalls = clientCallsMapped.filter(c => !c.matched);
const unusedServerEndpoints = mappingServerClient.filter(m => !m.is_called_by_client).map(m => m.server_endpoint);

// ---------- Outputs ----------
const outDir = path.join(projectRoot, 'docs', 'inventory');
ensureDir(outDir);

writeJson(path.join(outDir, 'server_endpoints.json'), serverEndpointsDedup);
writeJson(path.join(outDir, 'client_calls.json'), clientCallsMapped);
writeJson(path.join(outDir, 'mapping_server_client.json'), mappingServerClient);

// Markdown: server_endpoints.md
{
  const groups = new Map();
  for (const e of serverEndpointsDedup) {
    const g = e.source;
    if (!groups.has(g)) groups.set(g, []);
    groups.get(g).push(e);
  }
  let md = '### Server endpoints\n\n';
  for (const [group, items] of Array.from(groups.entries()).sort((a, b) => a[0].localeCompare(b[0]))) {
    md += `**Source: ${group}**\n\n`;
    md += '| Method | Path | Route | Controller | OpenAPI opId | Params |\n';
    md += '|---|---|---|---|---|---|\n';
    for (const e of items.sort((a, b) => (a.path.localeCompare(b.path) || a.method.localeCompare(b.method)))) {
      const ctrl = [e.controller_class, e.controller_action].filter(Boolean).join('::');
      const params = [...(e.path_params || []), ...(e.query_params || []).map(p => `?${p}`)].join(' ');
      md += `| ${e.method} | ${e.path} | ${e.route_name} | ${sanitizeMd(ctrl)} | ${sanitizeMd(e.openapi_operation_id)} | ${sanitizeMd(params)} |\n`;
    }
    md += '\n';
  }
  writeText(path.join(outDir, 'server_endpoints.md'), md);
}

// Markdown: client_calls.md
{
  let md = '### Client HTTP calls\n\n';
  md += '| File:Line | Lib | Method | URL | Headers | Body | Via | Matched | Route |\n';
  md += '|---|---|---|---|---|---|---|---|---|\n';
  for (const c of clientCallsMapped.sort((a, b) => a.file.localeCompare(b.file) || a.line - b.line)) {
    const headers = (c.headers_detected || []).join(',');
    md += `| ${c.file}:${c.line} | ${c.client_lib} | ${c.client_method} | ${sanitizeMd(c.url_template)} | ${sanitizeMd(headers)} | ${c.body_type} | ${c.via} | ${c.matched ? 'true' : 'false'} | ${sanitizeMd(c.matched_route_name || '')} |\n`;
  }
  writeText(path.join(outDir, 'client_calls.md'), md);
}

// Markdown: coverage.md
{
  const totalEndpoints = serverEndpointsDedup.length;
  const bySource = serverEndpointsDedup.reduce((acc, e) => { acc[e.source] = (acc[e.source] || 0) + 1; return acc; }, {});
  const totalClientCalls = clientCallsMapped.length;
  const coveredSet = new Set(mappingServerClient.filter(m => m.is_called_by_client).map(m => `${m.server_endpoint.method} ${m.server_endpoint.path}`));
  const coveredCount = coveredSet.size;
  const coveragePct = totalEndpoints ? Math.round((coveredCount / totalEndpoints) * 100) : 0;
  let md = '### Coverage summary\n\n';
  md += `- **total_server_endpoints**: ${totalEndpoints}\n`;
  for (const [k, v] of Object.entries(bySource).sort()) {
    md += `- **${k}**: ${v}\n`;
  }
  md += `- **total_client_calls**: ${totalClientCalls}\n`;
  md += `- **covered_endpoints**: ${coveredCount} (${coveragePct}%)\n\n`;
  md += '**orphan_client_calls**\n\n';
  if (orphanClientCalls.length === 0) {
    md += '- none\n\n';
  } else {
    for (const c of orphanClientCalls) {
      md += `- ${c.file}:${c.line} ${c.client_method} ${sanitizeMd(c.url_template)}\n`;
    }
    md += '\n';
  }
  md += '**unused_server_endpoints**\n\n';
  const unusedRows = unusedServerEndpoints
    .sort((a, b) => a.path.localeCompare(b.path) || a.method.localeCompare(b.method));
  if (unusedRows.length === 0) {
    md += '- none\n';
  } else {
    for (const u of unusedRows) {
      md += `- ${u.method} ${u.path} (${u.route_name})\n`;
    }
  }
  writeText(path.join(outDir, 'coverage.md'), md);
}

// CSV mapping
{
  let csv = 'method,path,route_name,source,is_called_by_client,client_refs\n';
  for (const m of mappingServerClient) {
    const refs = (m.client_refs || []).map(r => `${r.file}:${r.line}`).join(';');
    csv += [
      toCsvField(m.server_endpoint.method),
      toCsvField(m.server_endpoint.path),
      toCsvField(m.server_endpoint.route_name),
      toCsvField(m.server_endpoint.source),
      toCsvField(m.is_called_by_client ? 'true' : 'false'),
      toCsvField(refs),
    ].join(',') + '\n';
  }
  writeText(path.join(outDir, 'mapping_server_client.csv'), csv);
}

console.log('Inventory generated into docs/inventory');


