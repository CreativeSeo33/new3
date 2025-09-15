<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$srcDir = $root . '/src';
$outDir = $root . '/docs/runtime';
@mkdir($outDir, 0777, true);

// Load routes.json if present; support both associative (name => route) and list formats
$routes = [];
$routesFile = $outDir . '/routes.json';
if (is_file($routesFile)) {
    $raw = json_decode((string)file_get_contents($routesFile), true);
    if (is_array($raw)) {
        // Normalize to list of ['name'=>, 'path'=>, 'methods'=>[], 'controller'=>]
        foreach ($raw as $name => $r) {
            if (is_int($name) && is_array($r)) {
                // Already list-like
                $routes[] = $r;
            } elseif (is_array($r)) {
                $r['name'] = $r['name'] ?? (is_string($name) ? $name : null);
                $routes[] = $r;
            }
        }
    }
}

function parseAiMeta(string $block): array {
    // Strip leading * and spaces
    $clean = preg_replace('/^\s*\/\*\*|\*\/\s*$/', '', $block);
    $lines = preg_split('/\R/u', preg_replace('/^\s*\*\s?/m', '', (string)$clean));
    $meta = [];
    $key = null;
    foreach ($lines as $raw) {
        $line = trim((string)$raw);
        if ($line === '' || stripos($line, 'AI-META v1') === 0) { continue; }
        if (preg_match('/^([A-Za-z0-9_]+):\s*(.*)$/', $line, $m)) {
            $key = $m[1];
            $val = $m[2];
            if ($val === '') {
                $meta[$key] = [];
            } else {
                $meta[$key] = $val;
                $key = null;
            }
            continue;
        }
        if ($key && preg_match('/^-+\s*(.*)$/', $line, $m)) {
            $meta[$key] = $meta[$key] ?? [];
            $meta[$key][] = $m[1];
        }
    }
    return $meta;
}

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS));
$index = [];
$found = 0;

foreach ($it as $file) {
    if ($file->getExtension() !== 'php') continue;
    $code = file_get_contents($file->getPathname());
    if ($code === false) continue;

    // Find AI-META blocks
    if (!preg_match_all('#/\*\*[\s\S]*?AI-META v1[\s\S]*?\*/#m', $code, $blocks, PREG_OFFSET_CAPTURE)) {
        continue;
    }

    // Determine FQCN and also prepare next-token scanner
    $ns = null; $cls = null;
    if (preg_match('/namespace\s+([^;]+);/m', $code, $m)) { $ns = trim($m[1]); }
    if (preg_match('/class\s+([A-Za-z0-9_]+)/m', $code, $m)) { $cls = trim($m[1]); }
    $fqcn = $ns && $cls ? $ns . '\\' . $cls : null;

    foreach ($blocks[0] as $b) {
        $block = $b[0];
        $pos = $b[1];
        $meta = parseAiMeta($block);
        if (!$meta) { continue; }

        // Determine kind by peeking right after the block
        $after = substr($code, $pos + strlen($block), 300);
        $kind = 'class';
        if (preg_match('/^\s*(final\s+)?(class|interface|trait)\s+[A-Za-z0-9_]+/m', (string)$after)) {
            $kind = 'class';
        } elseif (preg_match('/^\s*(public|protected|private)?\s*function\s+[A-Za-z0-9_]+\s*\(/m', (string)$after)) {
            $kind = 'method';
        }

        $item = [
            'file' => str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname()),
            'class' => $fqcn,
            'kind' => $kind,
            'meta' => $meta,
        ];

        // Enrich with detected routes for controllers
        if ($fqcn && !empty($routes) && str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'Controller' . DIRECTORY_SEPARATOR)) {
            $detected = [];
            foreach ($routes as $r) {
                $ctrl = $r['controller'] ?? ($r['defaults']['_controller'] ?? null);
                if (!$ctrl) continue;
                if (str_starts_with((string)$ctrl, $fqcn.'::')) {
                    $detected[] = [
                        'name' => $r['name'] ?? null,
                        'methods' => $r['methods'] ?? ($r['methods'] ?? []),
                        'path' => $r['path'] ?? ($r['path'] ?? null),
                    ];
                }
            }
            if ($detected) { $item['routesDetected'] = $detected; }
        }

        $index[] = $item;
        $found++;
    }
}

$outFile = $outDir . '/ai-index.json';
$ok = @file_put_contents($outFile, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
if ($ok === false) {
    fwrite(STDERR, "Failed to write {$outFile}\n");
    exit(1);
}

fwrite(STDOUT, "AI index written: docs/runtime/ai-index.json (entries: {$found})\n");
exit(0);


