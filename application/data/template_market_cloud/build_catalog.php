<?php
/**
 * 模板市场云端目录构建（RS256 签名，与 ResourceHub 独立）
 *
 * 用法：
 *   php application/data/template_market_cloud/build_catalog.php
 *   php application/data/template_market_cloud/build_catalog.php --source=./catalog.source.json --out=./dist/catalog.json
 *   php application/data/template_market_cloud/build_catalog.php --private-key=./keys/catalog_private.pem
 *
 * 认证：服务端私钥对 {"items":[...]} 做 RS256 签名；CMS 内置公钥验签。
 * 对称加密仅作传输混淆时可选，不承担完整性认证职责。
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$opts = getopt('', ['source::', 'out::', 'private-key::', 'help']);
if (isset($opts['help'])) {
    echo file_get_contents(__FILE__);
    exit(0);
}

$scriptDir = __DIR__;
$sourcePath = isset($opts['source']) ? $opts['source'] : $scriptDir . '/catalog.source.json';
$outPath = isset($opts['out']) ? $opts['out'] : $scriptDir . '/dist/catalog.json';
$privateKeyPath = isset($opts['private-key']) ? $opts['private-key'] : $scriptDir . '/keys/catalog_private.pem';

if (!is_file($sourcePath)) {
    fwrite(STDERR, "Source not found: {$sourcePath}\nCopy catalog.source.example.json to catalog.source.json first.\n");
    exit(1);
}
if (!is_file($privateKeyPath)) {
    fwrite(STDERR, "Private key not found: {$privateKeyPath}\nRun generate_keys.php first.\n");
    exit(1);
}

$privateKey = openssl_pkey_get_private(file_get_contents($privateKeyPath));
if ($privateKey === false) {
    fwrite(STDERR, "Invalid private key: {$privateKeyPath}\n");
    exit(1);
}

$source = json_decode(file_get_contents($sourcePath), true);
if (!is_array($source) || empty($source['items']) || !is_array($source['items'])) {
    fwrite(STDERR, "Invalid source format: items required\n");
    exit(1);
}

$baseUrl = rtrim((string) ($source['base_url'] ?? ''), '/');
$publishRoot = dirname(realpath($sourcePath) ?: $sourcePath);

$items = [];
foreach ($source['items'] as $row) {
    if (!is_array($row)) {
        continue;
    }
    foreach (['id', 'name', 'dir'] as $field) {
        if (empty($row[$field])) {
            fwrite(STDERR, "Item missing field [{$field}]: " . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n");
            exit(1);
        }
    }

    $packageUrl = trim((string) ($row['package_url'] ?? ''));
    $packageHash = trim((string) ($row['package_hash'] ?? ''));

    if ($packageUrl === '' && !empty($row['package_file'])) {
        $rel = ltrim(str_replace('\\', '/', $row['package_file']), '/');
        $localZip = $publishRoot . '/' . $rel;
        if (!is_file($localZip)) {
            fwrite(STDERR, "Package zip not found: {$localZip}\n");
            exit(1);
        }
        $packageHash = 'sha256:' . hash_file('sha256', $localZip);
        $packageUrl = ($baseUrl !== '' ? $baseUrl . '/' : '') . $rel;
    }

    if ($packageUrl === '' || $packageHash === '' || !preg_match('/^sha256:[a-f0-9]{64}$/i', $packageHash)) {
        fwrite(STDERR, "Item requires package_url and package_hash (sha256:...): " . json_encode($row['id'] ?? '', JSON_UNESCAPED_UNICODE) . "\n");
        exit(1);
    }

    $preview = trim((string) ($row['preview'] ?? ''));
    if ($preview !== '' && !preg_match('#^https?://#i', $preview) && $baseUrl !== '') {
        $preview = $baseUrl . '/' . ltrim($preview, '/');
    }

    $items[] = [
        'id' => (string) $row['id'],
        'name' => (string) $row['name'],
        'version' => (string) ($row['version'] ?? '1.0.0'),
        'dir' => (string) $row['dir'],
        'desc' => (string) ($row['desc'] ?? ''),
        'preview' => $preview,
        'tags' => isset($row['tags']) && is_array($row['tags']) ? $row['tags'] : [],
        'package_url' => $packageUrl,
        'package_hash' => strtolower($packageHash),
    ];
}

$signPayload = json_encode(['items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$signature = '';
if (!openssl_sign($signPayload, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
    fwrite(STDERR, "Sign failed\n");
    exit(1);
}

$output = json_encode([
    'items' => $items,
    'sig_alg' => 'RS256',
    'signature' => base64_encode($signature),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$outDir = dirname($outPath);
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}
file_put_contents($outPath, $output);

echo "Built catalog: {$outPath}\n";
echo "Themes: " . count($items) . "\n";
echo "Signed with RS256\n";
