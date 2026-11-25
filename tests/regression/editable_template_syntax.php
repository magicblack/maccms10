<?php
use app\common\util\EditableTemplateSyntax;
require __DIR__ . '/../../application/common/util/EditableTemplateSyntax.php';

$valid = [
    '<style>.banner{color:red}</style><script>function show(){return 123;}</script>',
    '<h1>{$obj.vod_name}</h1>',
    '{if condition="$obj.vod_status eq 1"}{$obj.vod_name}{else}暂不可用{/if}',
    '{volist name="list" id="vo"}{$vo.name}{/volist}',
    '{foreach name="list" item="vo"}{$vo.name}{/foreach}',
    '{notempty name="obj.vod_name"}{$obj.vod_name}{/notempty}',
    '{maccms:vod num="8" order="desc" by="time"}{$vo.vod_name}{/maccms:vod}',
    '{maccms:comment num="5" paging="no"}{$vo.comment_content}{/maccms:comment}',
];
$invalid = [
    '{custom value="Example"}',
    '{if}',
    '{if condition="$obj.ok"}{else}{else}{/if}',
    '{if condition="$obj.ok"}{/volist}',
    '{if condition="$obj.ok"}',
    '{foreach name="list"}{$vo.name}{/foreach}',
    '{include file="$obj.template" /}',
    '{define name="title" value="Example" /}',
    '{$obj.name|trim}',
    '{$GLOBALS.site}',
    '{:trim($obj.name)}',
    '<?xml version="1.0"?>',
    '{maccms:vod num="8" id="this"}{/maccms:vod}',
    '{taglib name="Example" /}',
];
foreach ($valid as $i => $content) {
    if (!EditableTemplateSyntax::accepts($content)) {
        throw new RuntimeException('Unexpected rejection of fixture ' . ($i + 1));
    }
}
foreach ($invalid as $i => $content) {
    if (EditableTemplateSyntax::accepts($content)) {
        throw new RuntimeException('Unexpected acceptance of fixture ' . ($i + 1));
    }
}
echo 'PASS: ' . (count($valid) + count($invalid)) . " editable template checks\n";
