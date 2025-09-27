<?php
declare(strict_types=1);

// Simple, production-safe API smoke tester for Laravel backend
// Usage:
//   php backend/scripts/smoke_test.php tests/smoke/api_smoke.local.json
//   php backend/scripts/smoke_test.php tests/smoke/api_smoke.production.json
//
// Config schema (JSON):
// {
//   "base_url": "https://api.example.com/api",
//   "timeout": 15,
//   "headers": {"Accept": "application/json"},
//   "auth": {"bearer_env": "SMOKE_BEARER_TOKEN"},
//   "checks": [
//     {
//       "name": "Organizations index",
//       "method": "GET",
//       "path": "/organizations",
//       "expect_status": 200,
//       "expect_json": true,
//       "extract": [
//         {"var": "first_org_id", "path": "0.id"},
//         {"var": "first_org_name", "path": "0.org_name|0.nom_pharmacie|0.nom_parapharmacie|0.nom_clinique|0.nom_labo|0.nom_centre|0.name"}
//       ]
//     },
//     {
//       "name": "Organization show",
//       "method": "GET",
//       "path": "/organizations/{first_org_id}",
//       "expect_status": 200,
//       "expect_json": true,
//       "skip_if_missing": ["first_org_id"]
//     }
//   ]
// }

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(2);
}

if ($argc < 2) {
    fwrite(STDERR, "Usage: php backend/scripts/smoke_test.php <config.json>\n");
    exit(2);
}

$configPath = $argv[1];
if (!is_file($configPath)) {
    fwrite(STDERR, "Config file not found: {$configPath}\n");
    exit(2);
}

$configJson = file_get_contents($configPath);
if ($configJson === false) {
    fwrite(STDERR, "Unable to read config: {$configPath}\n");
    exit(2);
}

$config = json_decode($configJson, true);
if (!is_array($config)) {
    fwrite(STDERR, "Invalid JSON in config: {$configPath}\n");
    exit(2);
}

// Support either single environment (legacy) or multiple environments
$environments = [];
if (isset($config['environments']) && is_array($config['environments']) && count($config['environments']) > 0) {
    foreach ($config['environments'] as $env) {
        if (!is_array($env)) continue;
        $envBase = rtrim((string)($env['base_url'] ?? ''), '/');
        if ($envBase === '') continue;
        $environments[] = [
            'name' => (string)($env['name'] ?? $envBase),
            'base_url' => $envBase,
            'timeout' => (int)($env['timeout'] ?? ($config['timeout'] ?? 15)),
            'headers' => (array)($env['headers'] ?? ($config['headers'] ?? ['Accept' => 'application/json'])),
            'auth' => (array)($env['auth'] ?? ($config['auth'] ?? [])),
        ];
    }
} else {
    $baseUrl = rtrim((string)($config['base_url'] ?? ''), '/');
    if ($baseUrl === '') {
        fwrite(STDERR, "Missing base_url in config.\n");
        exit(2);
    }
    $environments[] = [
        'name' => (string)($config['name'] ?? $baseUrl),
        'base_url' => $baseUrl,
        'timeout' => (int)($config['timeout'] ?? 15),
        'headers' => (array)($config['headers'] ?? ['Accept' => 'application/json']),
        'auth' => (array)($config['auth'] ?? []),
    ];
}

$vars = [];
$results = [];
$allPassed = true;

function slugify(string $name): string {
    $name = trim(mb_strtolower($name, 'UTF-8'));
    // Transliterate accents
    $trans = [
        'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a',
        'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
        'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
        'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
        'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c','ñ'=>'n'
    ];
    $name = strtr($name, $trans);
    // Remove invalid characters
    $name = preg_replace('/[^a-z0-9\s-]/', '', $name) ?? '';
    // Replace whitespace with hyphens
    $name = preg_replace('/\s+/', '-', $name) ?? '';
    // Collapse multiple hyphens
    $name = preg_replace('/-+/', '-', $name) ?? '';
    // Trim hyphens
    $name = trim($name, '-');
    return $name;
}

function extractPathValue($data, string $path)
{
    // Support alternative paths separated by |
    foreach (explode('|', $path) as $alt) {
        $segments = $alt === '' ? [] : explode('.', $alt);
        $cur = $data;
        $ok = true;
        foreach ($segments as $seg) {
            if (is_array($cur)) {
                if (ctype_digit($seg)) {
                    $idx = (int)$seg;
                    if (!array_key_exists($idx, $cur)) { $ok = false; break; }
                    $cur = $cur[$idx];
                } else {
                    if (!array_key_exists($seg, $cur)) { $ok = false; break; }
                    $cur = $cur[$seg];
                }
            } elseif (is_object($cur)) {
                if (ctype_digit($seg)) {
                    $idx = (int)$seg;
                    if (!property_exists($cur, (string)$idx)) { $ok = false; break; }
                    $cur = $cur->$idx;
                } else {
                    if (!property_exists($cur, $seg)) { $ok = false; break; }
                    $cur = $cur->$seg;
                }
            } else {
                $ok = false; break;
            }
        }
        if ($ok) return $cur;
    }
    return null;
}

function httpRequest(string $method, string $url, array $headers, ?array $body, int $timeout, bool $insecure = false): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    if ($insecure) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }

    $headerLines = [];
    foreach ($headers as $k => $v) {
        $headerLines[] = $k . ': ' . $v;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);

    if ($body !== null) {
        $json = json_encode($body);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    }

    $start = microtime(true);
    $resp = curl_exec($ch);
    $elapsed = (microtime(true) - $start) * 1000.0;

    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['status' => 0, 'headers' => [], 'body' => '', 'time_ms' => $elapsed, 'error' => $err];
    }

    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $rawHeaders = substr($resp, 0, $headerSize);
    $body = substr($resp, $headerSize);
    curl_close($ch);

    return ['status' => $statusCode, 'headers' => $rawHeaders, 'body' => $body, 'time_ms' => $elapsed, 'error' => null];
}

$checks = $config['checks'] ?? [];
if (!is_array($checks) || empty($checks)) {
    fwrite(STDERR, "No checks defined in config.\n");
    exit(2);
}

echo "=== API Smoke Test ===\n";
$startedAt = date('c');

$overallPassed = true;

foreach ($environments as $envIndex => $envCfg) {
    $baseUrl = $envCfg['base_url'];
    $timeout = (int)$envCfg['timeout'];
    $defaultHeaders = (array)$envCfg['headers'];
    $auth = (array)$envCfg['auth'];
    $bearerEnv = isset($auth['bearer_env']) ? (string)$auth['bearer_env'] : null;
    $bearerValue = $bearerEnv ? getenv($bearerEnv) : null;
    $insecure = (bool)($envCfg['insecure'] ?? false);

    echo "\n--- Environment: " . ($envCfg['name'] ?: $baseUrl) . " ---\n";
    echo "Base URL: {$baseUrl}\n";
    $vars = [];
    $results = [];
    $allPassed = true;

    foreach ($checks as $index => $check) {
        if (!is_array($check)) continue;
        $name = (string)($check['name'] ?? ("Check #" . ($index + 1)));
        $method = strtoupper((string)($check['method'] ?? 'GET'));
        $path = (string)($check['path'] ?? '/');
        $expectStatus = (int)($check['expect_status'] ?? 200);
        $expectJson = (bool)($check['expect_json'] ?? false);
        $expectKeys = isset($check['expect_keys']) && is_array($check['expect_keys']) ? $check['expect_keys'] : [];
        $expectBodyType = isset($check['expect_body_type']) ? (string)$check['expect_body_type'] : '';
        $expectArrayItemKeys = isset($check['expect_array_item_keys']) && is_array($check['expect_array_item_keys']) ? $check['expect_array_item_keys'] : [];
        $jsonPathExists = isset($check['json_path_exists']) && is_array($check['json_path_exists']) ? $check['json_path_exists'] : [];
        $expectPathsArray = isset($check['expect_paths_array']) && is_array($check['expect_paths_array']) ? $check['expect_paths_array'] : [];
        $skipIfMissing = isset($check['skip_if_missing']) && is_array($check['skip_if_missing']) ? $check['skip_if_missing'] : [];
        $allowStatuses = isset($check['allow_status']) && is_array($check['allow_status']) ? $check['allow_status'] : [];
        $expectContains = isset($check['expect_contains']) && is_array($check['expect_contains']) ? $check['expect_contains'] : [];
        $onlyEnv = isset($check['only_env']) && is_array($check['only_env']) ? $check['only_env'] : [];
        $skipEnv = isset($check['skip_env']) && is_array($check['skip_env']) ? $check['skip_env'] : [];

        // Prepare ops (derive variables before building URL)
        if (!empty($check['prepare']) && is_array($check['prepare'])) {
            foreach ($check['prepare'] as $op) {
                if (!is_array($op)) continue;
                $operation = (string)($op['op'] ?? '');
                if ($operation === 'slug') {
                    $from = (string)($op['from'] ?? '');
                    $to = (string)($op['to'] ?? '');
                    if ($from !== '' && $to !== '' && isset($vars[$from]) && is_string($vars[$from]) && $vars[$from] !== '') {
                        $vars[$to] = slugify((string)$vars[$from]);
                    }
                }
            }
        }

        // Skip rule: environment filters
        $envName = (string)($envCfg['name'] ?? $baseUrl);
        if (!empty($onlyEnv) && !in_array($envName, $onlyEnv, true)) {
            echo "- SKIP: {$name} (not for environment: {$envName})\n";
            $results[] = ['name' => $name, 'status' => 'SKIPPED'];
            continue;
        }
        if (!empty($skipEnv) && in_array($envName, $skipEnv, true)) {
            echo "- SKIP: {$name} (skipped for environment: {$envName})\n";
            $results[] = ['name' => $name, 'status' => 'SKIPPED'];
            continue;
        }

        // Skip rule: if any required variable is missing
        $shouldSkip = false;
        foreach ($skipIfMissing as $v) {
            if (!array_key_exists($v, $vars) || $vars[$v] === null || $vars[$v] === '') { $shouldSkip = true; break; }
        }
        if ($shouldSkip) {
            echo "- SKIP: {$name} (missing vars: " . implode(',', $skipIfMissing) . ")\n";
            $results[] = ['name' => $name, 'status' => 'SKIPPED'];
            continue;
        }

        // Substitute placeholders {var}
        $urlPath = $path;
        if (preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $path, $m)) {
            foreach ($m[1] as $varName) {
                $val = isset($vars[$varName]) ? (string)$vars[$varName] : '';
                $urlPath = str_replace('{' . $varName . '}', rawurlencode($val), $urlPath);
            }
        }
        $url = $baseUrl . $urlPath;

        // Headers (merge default + per-check headers)
        $headers = $defaultHeaders;
        if (isset($check['headers']) && is_array($check['headers'])) {
            $headers = array_merge($headers, $check['headers']);
        }
        // Authorization
        $useBearer = $bearerValue;
        if (isset($check['auth']) && is_array($check['auth'])) {
            $be = (string)($check['auth']['bearer_env'] ?? '');
            if ($be !== '') {
                $useBearer = getenv($be) ?: $useBearer;
            }
        }
        if ($useBearer) {
            $headers['Authorization'] = 'Bearer ' . $useBearer;
        }

        // Body (only if method allows it). By default we do not send mutating requests in production configs.
        $body = null;
        if (isset($check['body']) && is_array($check['body']) && in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
            $headers['Content-Type'] = 'application/json';
            $body = $check['body'];
        }

        echo "- RUN : {$name} [{$method} {$urlPath}]... ";
        $resp = httpRequest($method, $url, $headers, $body, $timeout, $insecure);
        $status = (int)$resp['status'];
        $ok = ($status === $expectStatus) || in_array($status, $allowStatuses, true);

        $decoded = null;
        if ($expectJson && $resp['body'] !== '') {
            $decoded = json_decode($resp['body'], true);
            if (!is_array($decoded)) { $ok = false; }
        }

        // Validate expected keys (top-level only)
        if ($ok && $expectJson && !empty($expectKeys) && is_array($decoded)) {
            foreach ($expectKeys as $k) {
                if (!array_key_exists($k, $decoded)) { $ok = false; break; }
            }
        }

        // Body type expectation: 'array' or 'object'
        if ($ok && $expectJson && $expectBodyType !== '') {
            if ($expectBodyType === 'array' && !is_array($decoded)) {
                $ok = false;
            } elseif ($expectBodyType === 'object') {
                $isAssoc = is_array($decoded) && array_keys($decoded) !== range(0, count($decoded) - 1);
                if (!$isAssoc) $ok = false;
            }
        }

        // For array responses, ensure first item's keys exist
        if ($ok && $expectJson && !empty($expectArrayItemKeys)) {
            if (!is_array($decoded)) {
                $ok = false;
            } else {
                if (count($decoded) > 0) {
                    $first = $decoded[0];
                    if (!is_array($first)) { $ok = false; }
                    else {
                        foreach ($expectArrayItemKeys as $k) {
                            if (!array_key_exists($k, $first)) { $ok = false; break; }
                        }
                    }
                }
            }
        }

        // JSON path existence checks
        if ($ok && $expectJson && !empty($jsonPathExists)) {
            foreach ($jsonPathExists as $p) {
                $val = extractPathValue($decoded, $p);
                if ($val === null) { $ok = false; break; }
            }
        }

        // Expect certain JSON paths to be arrays (if present) - accept JSON-string arrays
        if ($ok && $expectJson && !empty($expectPathsArray)) {
            foreach ($expectPathsArray as $p) {
                $val = extractPathValue($decoded, $p);
                if ($val === null) { continue; }
                if (is_array($val)) { continue; }
                if (is_string($val)) {
                    $parsed = json_decode($val, true);
                    if (is_array($parsed)) { continue; }
                }
                $ok = false; break;
            }
        }

        // Extract variables for next checks
        if ($ok && isset($check['extract']) && is_array($check['extract']) && $decoded !== null) {
            foreach ($check['extract'] as $ex) {
                if (!is_array($ex)) continue;
                $varName = (string)($ex['var'] ?? '');
                $pathExpr = (string)($ex['path'] ?? '');
                if ($varName === '' || $pathExpr === '') continue;
                $val = extractPathValue($decoded, $pathExpr);
                if (is_string($val) || is_numeric($val)) {
                    $vars[$varName] = (string)$val;
                } elseif ($val !== null) {
                    $vars[$varName] = json_encode($val);
                }
            }
        }

        // HTML substring assertions (when not JSON)
        if ($ok && !$expectJson && !empty($expectContains)) {
            $bodyStr = (string)$resp['body'];
            foreach ($expectContains as $needle) {
                if ($needle === '') continue;
                if (mb_stripos($bodyStr, $needle) === false) { $ok = false; break; }
            }
        }

        $statusLabel = $ok ? 'PASS' : 'FAIL';
        if (!$ok) { $allPassed = false; }
        echo $statusLabel . ' (' . $status . ', ' . number_format((float)$resp['time_ms'], 1) . " ms)\n";

        if (!$ok) {
            $snippet = substr($resp['body'] ?? '', 0, 300);
            echo "  Expected: {$expectStatus}" . (!empty($allowStatuses) ? (" or [" . implode(',', $allowStatuses) . "]") : '') . "\n";
            echo "  Got     : {$status}\n";
            if ($resp['error']) echo "  Error   : {$resp['error']}\n";
            if ($snippet) echo "  Body    : " . str_replace(["\n","\r"], [' ', ' '], $snippet) . "\n";
        }

        $results[] = ['name' => $name, 'status' => $statusLabel, 'http' => $status, 'ms' => $resp['time_ms']];
    }

    $passedCount = count(array_filter($results, fn($r) => $r['status'] === 'PASS'));
    $failedCount = count(array_filter($results, fn($r) => $r['status'] === 'FAIL'));
    $skippedCount = count(array_filter($results, fn($r) => $r['status'] === 'SKIPPED'));
    $totalCount = count($results);

    echo "\n=== Environment Summary: " . ($envCfg['name'] ?: $baseUrl) . " ===\n";
    echo "Total   : {$totalCount}\n";
    echo "Passed  : {$passedCount}\n";
    echo "Failed  : {$failedCount}\n";
    echo "Skipped : {$skippedCount}\n";

    if (!$allPassed) { $overallPassed = false; }
}

echo "\n=== Overall Summary ({$startedAt}) ===\n";
echo "Completed environments: " . count($environments) . "\n";

exit($overallPassed ? 0 : 1);
