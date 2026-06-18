<?php
/**
 * 生成模板市场 RSA 密钥对（云端私钥签名、客户端公钥验签）
 *
 * 用法：php application/data/template_market_cloud/generate_keys.php
 *
 * 输出：
 *   keys/catalog_private.pem  — 仅保存在发布服务器，勿提交仓库
 *   catalog_public.pem        — 提交仓库，与客户端验签公钥一致
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

if (!function_exists('openssl_pkey_new')) {
    fwrite(STDERR, "OpenSSL extension required\n");
    exit(1);
}

$dir = __DIR__;
$keyDir = $dir . '/keys';
if (!is_dir($keyDir)) {
    mkdir($keyDir, 0755, true);
}

$privatePath = $keyDir . '/catalog_private.pem';
$publicPath = $dir . '/catalog_public.pem';

if (is_file($privatePath)) {
    fwrite(STDERR, "Private key already exists: {$privatePath}\nRefusing to overwrite.\n");
    exit(1);
}

$key = openssl_pkey_new([
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
]);
if ($key === false) {
    fwrite(STDERR, "Key generation failed\n");
    exit(1);
}

openssl_pkey_export($key, $privatePem);
$details = openssl_pkey_get_details($key);
$publicPem = $details['key'] ?? '';

file_put_contents($privatePath, $privatePem);
chmod($privatePath, 0600);
file_put_contents($publicPath, $publicPem);

echo "Private key: {$privatePath}\n";
echo "Public key:  {$publicPath}\n";
echo "Deploy catalog_public.pem with CMS; keep private key only on catalog build server.\n";
