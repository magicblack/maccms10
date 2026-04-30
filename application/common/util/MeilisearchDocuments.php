<?php

namespace app\common\util;

/**
 * 将 vod / art / manga 行转为 Meilisearch 文档。
 */
class MeilisearchDocuments
{
    private static function plain($html, $max = 4000)
    {
        $t = strip_tags((string)$html);
        $t = preg_replace('/\s+/u', ' ', $t);
        $t = trim($t);
        if (mb_strlen($t, 'UTF-8') > $max) {
            $t = mb_substr($t, 0, $max, 'UTF-8');
        }
        return $t;
    }

    /**
     * 生成拼音检索字段（全拼 + 首字母），用于拼音搜索和模糊匹配。
     *
     * @return array{py:string,initials:string}
     */
    private static function pinyinFields($text)
    {
        $text = trim((string)$text);
        if ($text === '') {
            return ['py' => '', 'initials' => ''];
        }
        try {
            $py = strtolower((string)Pinyin::get($text, 'all', ''));
            $initials = strtolower((string)Pinyin::get($text, 'first', ''));
        } catch (\Throwable $e) {
            $py = '';
            $initials = '';
        }
        return ['py' => $py, 'initials' => $initials];
    }

    /**
     * 生成繁简体检索字段，支持繁简互通搜索。
     *
     * @return array{t2s:string,s2t:string}
     */
    private static function zhFields($text)
    {
        $text = trim((string)$text);
        if ($text === '') {
            return ['t2s' => '', 's2t' => ''];
        }
        return [
            't2s' => OpenccConverter::t2s($text),
            's2t' => OpenccConverter::s2t($text),
        ];
    }

    public static function fromVodRow(array $row)
    {
        $id = (int)($row['vod_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $recycle = !empty($row['vod_recycle_time']) ? 1 : 0;
        $status = (int)($row['vod_status'] ?? 0);
        $body = self::plain($row['vod_content'] ?? '', 6000);
        $blurb = self::plain($row['vod_blurb'] ?? '', 500);
        $titlePy = self::pinyinFields($row['vod_name'] ?? '');
        $subPy = self::pinyinFields($row['vod_sub'] ?? '');
        $extraPy = self::pinyinFields(trim((string)($row['vod_actor'] ?? '') . ' ' . (string)($row['vod_director'] ?? '')));
        $tagsPy = self::pinyinFields($row['vod_tag'] ?? '');
        $titleZh = self::zhFields($row['vod_name'] ?? '');
        $subZh = self::zhFields($row['vod_sub'] ?? '');
        $extraZh = self::zhFields(trim((string)($row['vod_actor'] ?? '') . ' ' . (string)($row['vod_director'] ?? '')));
        $tagsZh = self::zhFields($row['vod_tag'] ?? '');
        return [
            'id' => 'vod_' . $id,
            'kind' => 'vod',
            'title' => (string)($row['vod_name'] ?? ''),
            'subtitle' => (string)($row['vod_sub'] ?? ''),
            'en' => (string)($row['vod_en'] ?? ''),
            'extra' => trim((string)($row['vod_actor'] ?? '') . ' ' . (string)($row['vod_director'] ?? '')),
            'tags' => (string)($row['vod_tag'] ?? ''),
            'class_text' => (string)($row['vod_class'] ?? ''),
            'title_py' => $titlePy['py'],
            'title_initials' => $titlePy['initials'],
            'subtitle_py' => $subPy['py'],
            'subtitle_initials' => $subPy['initials'],
            'extra_py' => $extraPy['py'],
            'extra_initials' => $extraPy['initials'],
            'tags_py' => $tagsPy['py'],
            'tags_initials' => $tagsPy['initials'],
            'title_t2s' => $titleZh['t2s'],
            'title_s2t' => $titleZh['s2t'],
            'subtitle_t2s' => $subZh['t2s'],
            'subtitle_s2t' => $subZh['s2t'],
            'extra_t2s' => $extraZh['t2s'],
            'extra_s2t' => $extraZh['s2t'],
            'tags_t2s' => $tagsZh['t2s'],
            'tags_s2t' => $tagsZh['s2t'],
            'blurb' => $blurb,
            'body' => $body,
            'type_id' => (int)($row['type_id'] ?? 0),
            'type_id_1' => (int)($row['type_id_1'] ?? 0),
            'recycle' => $recycle,
            'status' => $status,
            'level' => (int)($row['vod_level'] ?? 0),
            'group_id' => (int)($row['group_id'] ?? 0),
            'isend' => (int)($row['vod_isend'] ?? 0),
            'plot' => (int)($row['vod_plot'] ?? 0),
            'year' => (string)($row['vod_year'] ?? ''),
            'area' => (string)($row['vod_area'] ?? ''),
            'lang' => (string)($row['vod_lang'] ?? ''),
            'state' => (string)($row['vod_state'] ?? ''),
            'version' => (string)($row['vod_version'] ?? ''),
            'hits_month' => (int)($row['vod_hits_month'] ?? 0),
            'ts' => (int)($row['vod_time'] ?? 0),
        ];
    }

    public static function fromArtRow(array $row)
    {
        $id = (int)($row['art_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $recycle = !empty($row['art_recycle_time']) ? 1 : 0;
        $status = (int)($row['art_status'] ?? 0);
        $content = str_replace('$$$', "\n", (string)($row['art_content'] ?? ''));
        $body = self::plain($content, 6000);
        $blurb = self::plain($row['art_blurb'] ?? '', 500);
        $titlePy = self::pinyinFields($row['art_name'] ?? '');
        $subPy = self::pinyinFields($row['art_sub'] ?? '');
        $extraPy = self::pinyinFields($row['art_author'] ?? '');
        $tagsPy = self::pinyinFields($row['art_tag'] ?? '');
        $titleZh = self::zhFields($row['art_name'] ?? '');
        $subZh = self::zhFields($row['art_sub'] ?? '');
        $extraZh = self::zhFields($row['art_author'] ?? '');
        $tagsZh = self::zhFields($row['art_tag'] ?? '');
        return [
            'id' => 'art_' . $id,
            'kind' => 'art',
            'title' => (string)($row['art_name'] ?? ''),
            'subtitle' => (string)($row['art_sub'] ?? ''),
            'en' => (string)($row['art_en'] ?? ''),
            'extra' => (string)($row['art_author'] ?? ''),
            'tags' => (string)($row['art_tag'] ?? ''),
            'class_text' => (string)($row['art_class'] ?? ''),
            'title_py' => $titlePy['py'],
            'title_initials' => $titlePy['initials'],
            'subtitle_py' => $subPy['py'],
            'subtitle_initials' => $subPy['initials'],
            'extra_py' => $extraPy['py'],
            'extra_initials' => $extraPy['initials'],
            'tags_py' => $tagsPy['py'],
            'tags_initials' => $tagsPy['initials'],
            'title_t2s' => $titleZh['t2s'],
            'title_s2t' => $titleZh['s2t'],
            'subtitle_t2s' => $subZh['t2s'],
            'subtitle_s2t' => $subZh['s2t'],
            'extra_t2s' => $extraZh['t2s'],
            'extra_s2t' => $extraZh['s2t'],
            'tags_t2s' => $tagsZh['t2s'],
            'tags_s2t' => $tagsZh['s2t'],
            'blurb' => $blurb,
            'body' => $body,
            'type_id' => (int)($row['type_id'] ?? 0),
            'type_id_1' => (int)($row['type_id_1'] ?? 0),
            'recycle' => $recycle,
            'status' => $status,
            'level' => (int)($row['art_level'] ?? 0),
            'year' => '',
            'area' => '',
            'lang' => '',
            'state' => '',
            'version' => '',
            'hits_month' => (int)($row['art_hits_month'] ?? 0),
            'ts' => (int)($row['art_time'] ?? 0),
        ];
    }

    public static function fromMangaRow(array $row)
    {
        $id = (int)($row['manga_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $recycle = !empty($row['manga_recycle_time']) ? 1 : 0;
        $status = (int)($row['manga_status'] ?? 0);
        $content = str_replace('$$$', "\n", (string)($row['manga_content'] ?? ''));
        $body = self::plain($content, 6000);
        $blurb = self::plain($row['manga_blurb'] ?? '', 500);
        $titlePy = self::pinyinFields($row['manga_name'] ?? '');
        $subPy = self::pinyinFields($row['manga_sub'] ?? '');
        $extraPy = self::pinyinFields($row['manga_author'] ?? '');
        $tagsPy = self::pinyinFields($row['manga_tag'] ?? '');
        $titleZh = self::zhFields($row['manga_name'] ?? '');
        $subZh = self::zhFields($row['manga_sub'] ?? '');
        $extraZh = self::zhFields($row['manga_author'] ?? '');
        $tagsZh = self::zhFields($row['manga_tag'] ?? '');
        return [
            'id' => 'manga_' . $id,
            'kind' => 'manga',
            'title' => (string)($row['manga_name'] ?? ''),
            'subtitle' => (string)($row['manga_sub'] ?? ''),
            'en' => (string)($row['manga_en'] ?? ''),
            'extra' => (string)($row['manga_author'] ?? ''),
            'tags' => (string)($row['manga_tag'] ?? ''),
            'class_text' => (string)($row['manga_class'] ?? ''),
            'title_py' => $titlePy['py'],
            'title_initials' => $titlePy['initials'],
            'subtitle_py' => $subPy['py'],
            'subtitle_initials' => $subPy['initials'],
            'extra_py' => $extraPy['py'],
            'extra_initials' => $extraPy['initials'],
            'tags_py' => $tagsPy['py'],
            'tags_initials' => $tagsPy['initials'],
            'title_t2s' => $titleZh['t2s'],
            'title_s2t' => $titleZh['s2t'],
            'subtitle_t2s' => $subZh['t2s'],
            'subtitle_s2t' => $subZh['s2t'],
            'extra_t2s' => $extraZh['t2s'],
            'extra_s2t' => $extraZh['s2t'],
            'tags_t2s' => $tagsZh['t2s'],
            'tags_s2t' => $tagsZh['s2t'],
            'blurb' => $blurb,
            'body' => $body,
            'type_id' => (int)($row['type_id'] ?? 0),
            'type_id_1' => (int)($row['type_id_1'] ?? 0),
            'recycle' => $recycle,
            'status' => $status,
            'level' => (int)($row['manga_level'] ?? 0),
            'year' => '',
            'area' => '',
            'lang' => '',
            'state' => '',
            'version' => '',
            'hits_month' => (int)($row['manga_hits_month'] ?? 0),
            'ts' => (int)($row['manga_time'] ?? 0),
        ];
    }
}
