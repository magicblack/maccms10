<?php

namespace app\common\util;

class TimmingConfig
{
    /**
     * Return true after saving, null when the callback skips the write,
     * or false when the file cannot be read or replaced.
     */
    public static function update($file, callable $change)
    {
        return self::withLock($file, function () use ($file, $change) {
            $list = [];
            $mode = 0644;
            if (is_file($file)) {
                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate($file, true);
                }
                $list = include $file;
                $mode = fileperms($file);
                if (!is_array($list) || $mode === false) {
                    return false;
                }
            }
            if (!$change($list)) {
                return null;
            }
            $content = "<?php\nreturn " . var_export($list, true) . ';';
            return self::replace($file, $content, $mode);
        });
    }

    private static function withLock($file, callable $operation)
    {
        $lock = @fopen($file . '.lock', 'c');
        if ($lock === false) {
            return false;
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                return false;
            }
            clearstatcache(true, $file);
            return $operation();
        } finally {
            fclose($lock);
        }
    }

    private static function replace($file, $content, $mode)
    {
        $temporary = @tempnam(dirname($file), '.timming-');
        try {
            if ($temporary === false || realpath(dirname($temporary)) !== realpath(dirname($file))) {
                return false;
            }
            if (@file_put_contents($temporary, $content) !== strlen($content)
                || !@chmod($temporary, $mode & 0777)
                || !@rename($temporary, $file)) {
                return false;
            }
            $temporary = null;
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($file, true);
            }
            return true;
        } finally {
            if ($temporary) {
                @unlink($temporary);
            }
        }
    }

    public static function recordRuntime($file, $name, array $expected, $runtime)
    {
        return self::update($file, function (&$list) use ($name, $expected, $runtime) {
            if (!isset($list[$name]) || $list[$name] !== $expected) {
                return false;
            }
            $list[$name]['runtime'] = $runtime;
            return true;
        });
    }

    public static function addDefaults($file, array $defaults)
    {
        return self::update($file, function (&$list) use ($defaults) {
            $changed = false;
            foreach ($defaults as $name => $task) {
                if (!isset($list[$name])) {
                    $list[$name] = $task;
                    $changed = true;
                }
            }
            return $changed;
        });
    }
}
