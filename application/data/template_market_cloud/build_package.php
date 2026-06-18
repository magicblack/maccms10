<?php
/**
 * 将本地 template/{dir} 打包为可发布的主题 zip（排除 php 等不安全扩展）
 *
 * 用法：
 *   php application/data/template_market_cloud/build_package.php --dir=ai_cinema_v1
 *   php application/data/template_market_cloud/build_package.php --dir=ai_cinema_v1 --out=./packages/ai-cinema-v1/package.zip
 *
 * 主题包根目录需包含 html/；可选 config.defaults.json 供安装后合并 mctheme 默认值。
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$opts = getopt('', ['dir:', 'out::', 'root::', 'help']);
if (isset($opts['help']) || empty($opts['dir'])) {
    echo "Usage: php build_package.php --dir=TEMPLATE_DIR [--out=path] [--root=project_root]\n";
    exit(isset($opts['help']) ? 0 : 1);
}

$dir = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $opts['dir']);
if ($dir === '') {
    fwrite(STDERR, "Invalid --dir\n");
    exit(1);
}

$projectRoot = isset($opts['root']) ? rtrim($opts['root'], '/\\') : dirname(__DIR__, 3);
$src = $projectRoot . DIRECTORY_SEPARATOR . 'template' . DIRECTORY_SEPARATOR . $dir;
if (!is_dir($src) || !is_dir($src . DIRECTORY_SEPARATOR . 'html')) {
    fwrite(STDERR, "Template not found or missing html/: {$src}\n");
    exit(1);
}

$outPath = isset($opts['out'])
    ? $opts['out']
    : __DIR__ . '/packages/' . $dir . '/package.zip';

$outDir = dirname($outPath);
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "ZipArchive extension required\n");
    exit(1);
}

$denyExt = ['php', 'phtml', 'phar', 'inc'];
$zip = new ZipArchive();
if ($zip->open($outPath, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Cannot create zip: {$outPath}\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$prefix = $dir . '/';
foreach ($iterator as $file) {
    /** @var SplFileInfo $file */
    $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($src) + 1));
    if ($rel === false || $rel === '') {
        continue;
    }
    if ($file->isDir()) {
        $zip->addEmptyDir($prefix . $rel . '/');
        continue;
    }
    $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    if (in_array($ext, $denyExt, true)) {
        continue;
    }
    $zip->addFile($file->getPathname(), $prefix . $rel);
}

$zip->close();

$hash = hash_file('sha256', $outPath);
echo "Package: {$outPath}\n";
echo "SHA256: sha256:{$hash}\n";
echo "Size: " . filesize($outPath) . " bytes\n";
