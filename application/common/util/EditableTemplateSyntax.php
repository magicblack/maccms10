<?php

namespace app\common\util;

class EditableTemplateSyntax
{
    const VARIABLE = '[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z0-9_]+)*';

    private static $tags = [
        'if' => ['condition'],
        'elseif' => ['condition'],
        'else' => [],
        'volist' => ['name', 'id', 'key', 'offset', 'length', 'mod'],
        'foreach' => ['name', 'item', 'key'],
        'empty' => ['name'],
        'notempty' => ['name'],
        'present' => ['name'],
        'notpresent' => ['name'],
        'maccms:vod' => ['type', 'num', 'order', 'by', 'id', 'key', 'level', 'start', 'paging'],
        'maccms:art' => ['type', 'num', 'order', 'by', 'id', 'key', 'level', 'start', 'paging'],
        'maccms:type' => ['ids', 'type', 'num', 'order', 'by', 'id', 'key', 'parent'],
        'maccms:comment' => ['num', 'order', 'by', 'id', 'key', 'rid', 'mid', 'paging'],
    ];

    public static function accepts($source)
    {
        if (!is_string($source) || strpos($source, '<?') !== false) {
            return false;
        }
        // Scan every directive opener, including inside HTML comments and script/style blocks.
        $directives = 'php|volist|iterate|foreach|if|elseif|else|switch|case|default|compare|eq|equal|notequal|neq|gt|lt|egt|elt|heq|nheq|range|in|notin|between|notbetween|empty|notempty|present|notpresent|defined|notdefined|load|import|css|js|assign|define|for|url|function|block|include|extend|layout|taglib|literal';
        $result = preg_match_all('/\{(?=\s*(?:[$:~+\-\/]|(?:' . $directives . ')\b|maccms:|cx:|[A-Za-z_]\w*(?=\s+[A-Za-z_][\w-]*\s*=)))/i', $source, $offsets, PREG_OFFSET_CAPTURE);
        if ($result === false) {
            return false;
        }
        $stack = [];
        foreach ($offsets[0] as $match) {
            $start = $match[1] + 1;
            $end = strpos($source, '}', $start);
            if ($end === false) {
                return false;
            }
            $tag = trim(substr($source, $start, $end - $start));
            if (!self::acceptTag($tag, $stack)) {
                return false;
            }
        }
        return $stack === [];
    }

    private static function acceptTag($tag, array &$stack)
    {
        if (substr($tag, 0, 1) === '$') {
            return self::isDisplayVariable(substr($tag, 1));
        }
        if (preg_match('/\A\/([a-z]+(?::[a-z]+)?)\z/D', $tag, $close)) {
            $open = array_pop($stack);
            return $open !== null && $open[0] === $close[1];
        }
        if (!preg_match('/\A([a-z]+(?::[a-z]+)?)(.*)\z/sD', $tag, $parts) || !isset(self::$tags[$parts[1]])) {
            return false;
        }
        $name = $parts[1];
        $attributes = [];
        $rest = $parts[2];
        while ($rest !== '') {
            if (!preg_match('/\A\s+([a-z]+)\s*=\s*(["\x27])([^{}<>\\\\\r\n]*?)\2(.*)\z/sD', $rest, $attribute)
                || isset($attributes[$attribute[1]]) || !in_array($attribute[1], self::$tags[$name], true)
                || !self::acceptValue($attribute[1], $attribute[3])) {
                return false;
            }
            $attributes[$attribute[1]] = $attribute[3];
            $rest = $attribute[4];
        }
        if ($name === 'else' || $name === 'elseif') {
            $index = count($stack) - 1;
            if ($index < 0 || $stack[$index][0] !== 'if' || $stack[$index][1]
                || ($name === 'elseif' && !isset($attributes['condition']))) {
                return false;
            }
            $stack[$index][1] = ($name === 'else');
            return true;
        }
        if ($name === 'if' && !isset($attributes['condition'])) {
            return false;
        }
        if (in_array($name, ['volist', 'foreach', 'empty', 'notempty', 'present', 'notpresent'], true) && !isset($attributes['name'])) {
            return false;
        }
        if (($name === 'volist' && !isset($attributes['id'])) || ($name === 'foreach' && !isset($attributes['item']))) {
            return false;
        }
        $stack[] = [$name, false];
        return true;
    }

    private static function acceptValue($name, $value)
    {
        if ($name === 'condition') {
            return preg_match('/\A\$(' . self::VARIABLE . ')(?:\s+(?:eq|neq|gt|egt|lt|elt)\s+(?:[0-9]+|true|false))?\z/D', $value, $match)
                && self::isDisplayVariable($match[1]);
        }
        if ($name === 'name') {
            return self::isDisplayVariable($value);
        }
        if (in_array($name, ['id', 'item', 'key'], true)) {
            return preg_match('/\A[A-Za-z][A-Za-z0-9_]*\z/D', $value) && !in_array(strtolower($value), ['this', 'globals', 'think'], true);
        }
        return (bool)preg_match('/\A[A-Za-z0-9_, -]+\z/D', $value);
    }

    private static function isDisplayVariable($value)
    {
        if (!preg_match('/\A' . self::VARIABLE . '\z/D', $value)) {
            return false;
        }
        $root = explode('.', $value, 2)[0];
        return $root[0] !== '_' && !in_array(strtolower($root), ['globals', 'this', 'think', 'request', 'session', 'config', 'env', 'cookie', 'server'], true);
    }
}
