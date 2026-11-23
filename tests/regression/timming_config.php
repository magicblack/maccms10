<?php

use app\common\util\TimmingConfig;

require __DIR__ . '/../../application/common/util/TimmingConfig.php';

function checkTimming($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo 'OK: ' . $message . PHP_EOL;
}

$directory = sys_get_temp_dir() . '/maccms-timming-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) {
    throw new RuntimeException('Cannot create fixture directory');
}
$file = $directory . '/timming.php';

try {
    $task = ['status' => '1', 'runtime' => 0];
    checkTimming(TimmingConfig::addDefaults($file, ['existing' => $task]) === true, 'create initial config');
    $snapshot = include $file;

    $custom = ['file' => 'collect', 'param' => "name=quoted'\\value", 'runtime' => 0];
    checkTimming(TimmingConfig::update($file, function (&$list) use ($custom) {
        $list['xigua'] = $custom;
        return true;
    }) === true, 'save custom task after scheduler took its snapshot');

    checkTimming(TimmingConfig::recordRuntime($file, 'existing', $snapshot['existing'], 123) === true, 'write only the due task runtime');
    $current = include $file;
    checkTimming($current['xigua'] === $custom && $current['existing']['runtime'] === 123, 'preserve new task and its quoted parameters');
    checkTimming(TimmingConfig::recordRuntime($file, 'existing', $snapshot['existing'], 124) === null, 'skip stale claim for a task already advanced');

    TimmingConfig::update($file, function (&$list) {
        unset($list['xigua']);
        return true;
    });
    checkTimming(TimmingConfig::recordRuntime($file, 'xigua', $custom, 125) === null, 'skip a deleted task');
    checkTimming(TimmingConfig::addDefaults($file, ['existing' => $task, 'new_default' => $task]) === true, 'add missing default');
    $current = include $file;
    checkTimming(!isset($current['xigua']) && $current['existing']['runtime'] === 123, 'retain deletion and user settings after adding defaults');

    $broken = "<?php return array ('content_check' => array ()); 'xigua' => array ();";
    file_put_contents($file, $broken);
    $parseError = false;
    try {
        TimmingConfig::addDefaults($file, ['other' => $task]);
    } catch (ParseError $error) {
        $parseError = true;
    }
    checkTimming($parseError && file_get_contents($file) === $broken, 'surface malformed config without overwriting the original');
} finally {
    unlink($file);
    unlink($file . '.lock');
    rmdir($directory);
}
