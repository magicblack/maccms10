<?php
namespace think\template { class TagLib {} }
namespace {
    function model($name)
    {
        return new class {
            public function listCacheData($attributes)
            {
                $GLOBALS['captured'] = $attributes;
                return ['list' => [], 'total' => 30, 'limit' => 5, 'page' => 2, 'pageurl' => '/comment', 'half' => 3];
            }
        };
    }

    function mac_page_param(...$args) { $GLOBALS['pagination'] = $args; }

    function checkComment($condition, $message)
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
        echo "OK: $message\n";
    }

    function runCommentTag($tag, $context = [])
    {
        $compiler = new \app\common\taglib\Maccms();
        $template = $compiler->tagComment($tag, '');
        $phpEnd = strpos($template, '?>');
        if ($phpEnd === false) {
            throw new \RuntimeException('Missing compiled PHP terminator');
        }
        $file = tempnam(sys_get_temp_dir(), 'mac-comment-');
        try {
            file_put_contents($file, substr($template, 0, $phpEnd + 2));
            extract($context, EXTR_SKIP);
            include $file;
            return $GLOBALS['captured'];
        } finally {
            unlink($file);
        }
    }

    require isset($argv[1]) ? $argv[1] : __DIR__ . '/../../application/common/taglib/Maccms.php';
    $tag = ['paging' => 'no', 'num' => '5', 'rid' => '17', 'mid' => '2'];
    $loaded = runCommentTag($tag);
    checkComment(is_array($loaded) && $loaded['num'] === '5' && $loaded['rid'] === '17', 'compiled num and rid reach the model');
    $loaded = runCommentTag($tag, ['comment_mid' => 1, 'comment_rid' => 99]);
    checkComment($loaded['rid'] === '17' && $loaded['mid'] === '2', 'explicit IDs win over the page context');
    $loaded = runCommentTag(['paging' => 'no'], ['comment_mid' => 1, 'comment_rid' => 99]);
    checkComment($loaded['rid'] === 99 && $loaded['mid'] === 1 && !isset($loaded['num']), 'omitted IDs use context and omitted page size uses the model default');
    $loaded = runCommentTag(['paging' => 'no']);
    checkComment(!isset($loaded['rid'], $loaded['mid'], $loaded['num']), 'public list retains the unfiltered defaults');
    $loaded = runCommentTag(['paging' => 'yes', 'num' => '5', 'pageurl' => "test'\\path"]);
    checkComment($loaded['pageurl'] === "test'\\path" && $GLOBALS['pagination'] === [30, 5, 2, '/comment', 3], 'quoted attributes and pagination are preserved');
}
