<?php
namespace app\common\util;

use think\Db;

class AiChatService
{
    public function buildPayload($question, $mid, $limit)
    {
        $responseLang = $this->resolveResponseLanguage($question);
        $moduleMap = [1=>'vod',2=>'art',3=>'topic',8=>'actor',9=>'role',11=>'website',12=>'plot',13=>'manga'];
        $module = ($mid === 0) ? 'mixed' : (isset($moduleMap[$mid]) ? $moduleMap[$mid] : 'mixed');
        $searchMeta = ['expanded_terms' => [], 'external_resources' => []];

        if ($mid === 0) {
            try {
                $searchMeta = $this->mergeSearchMetaPayloads(
                    AiSearch::buildForSearch('vod', ['wd' => $question]),
                    AiSearch::buildForSearch('art', ['wd' => $question]),
                    AiSearch::buildForSearch('manga', ['wd' => $question])
                );
            } catch (\Throwable $e) {
                $searchMeta = ['expanded_terms' => [], 'external_resources' => []];
            }
        } elseif ($module !== 'mixed') {
            try {
                $searchMeta = AiSearch::buildForSearch($module, ['wd' => $question]);
            } catch (\Throwable $e) {
                $searchMeta = ['expanded_terms' => [], 'external_resources' => []];
            }
        }

        $cards = $this->buildCards($question, $mid, $limit, $searchMeta);
        $externalFederated = (new ExternalFederationService())->searchAndStore($question, $module, ['limit' => 6]);
        $aiSearchResults = $this->requestAiSearchResults($question, $module, $cards, $responseLang);
        $aiCfg = config('maccms.ai_search');
        if (!is_array($aiCfg)) {
            $aiCfg = [];
        }
        $catalogLinks = AiCatalogLinkFetcher::fetchAll($question, $aiCfg);
        return [
            'tag' => $this->buildTag($module),
            'cms_hit' => !empty($cards),
            'cms_count' => count($cards),
            'cards' => $cards,
            'content' => $this->buildContent($question, $cards, $searchMeta, $aiSearchResults, $externalFederated, $responseLang),
            'enriched_answer' => $this->buildAiChatEnrichedAnswer($question, $cards, $module, $searchMeta, $responseLang),
            'related_links' => $this->buildRelatedLinks($cards, $searchMeta, $aiSearchResults, $externalFederated, $responseLang, $catalogLinks),
            'ai_results' => $aiSearchResults,
            'external_results' => $externalFederated,
            'external_cards' => $this->buildExternalCards($externalFederated, $aiSearchResults),
            'suggestions' => $this->buildSuggestions($question, $cards, $module, $responseLang),
            'trace_id' => 'chat_' . date('Ymd_His') . '_' . substr(md5($question . microtime(true)), 0, 8),
        ];
    }

    public function emptyPayload()
    {
        return [
            'tag' => '',
            'cms_hit' => false,
            'cms_count' => 0,
            'cards' => [],
            'content' => '',
            'enriched_answer' => '',
            'related_links' => [],
            'ai_results' => [],
            'external_results' => [],
            'external_cards' => [],
            'suggestions' => [],
            'trace_id' => '',
        ];
    }

    private function mergeSearchMetaPayloads(array $a, array $b)
    {
        $terms = [];
        if (!empty($a['expanded_terms']) && is_array($a['expanded_terms'])) {
            $terms = array_merge($terms, $a['expanded_terms']);
        }
        if (!empty($b['expanded_terms']) && is_array($b['expanded_terms'])) {
            $terms = array_merge($terms, $b['expanded_terms']);
        }
        $terms = array_values(array_unique(array_filter(array_map(function ($t) {
            return trim((string)$t);
        }, $terms))));
        $terms = array_slice($terms, 0, 8);

        $external = [];
        if (!empty($a['external_resources']) && is_array($a['external_resources'])) {
            $external = array_merge($external, $a['external_resources']);
        }
        if (!empty($b['external_resources']) && is_array($b['external_resources'])) {
            $external = array_merge($external, $b['external_resources']);
        }
        return ['expanded_terms' => $terms, 'external_resources' => $external];
    }

    private function buildCards($question, $mid, $limit, array $searchMeta)
    {
        $targets = ($mid === 0) ? [1, 2, 13] : [$mid];
        $fetch = max(intval($limit) * 4, 24);
        $combined = [];
        foreach ($targets as $tmid) {
            $rows = $this->buildAiChatRankedRowsForMid($tmid, $question, $fetch, $searchMeta);
            foreach ($rows as $row) {
                $row['_mid'] = $tmid;
                $combined[] = $row;
            }
        }
        usort($combined, function ($a, $b) {
            if ($a['score'] === $b['score']) {
                if ($a['hits'] === $b['hits']) {
                    return $b['id'] <=> $a['id'];
                }
                return $b['hits'] <=> $a['hits'];
            }
            return $b['score'] <=> $a['score'];
        });

        $cards = [];
        foreach (array_slice($combined, 0, intval($limit)) as $row) {
            $cards[] = $this->buildCard(intval($row['_mid']), $row);
        }
        return $cards;
    }

    private function buildAiChatRankedRowsForMid($mid, $question, $fetchLimit, array $searchMeta = [])
    {
        $cfg = $this->getMidConfig($mid);
        if (empty($cfg)) {
            return [];
        }
        $queries = [$question];
        if (!empty($searchMeta['expanded_terms']) && is_array($searchMeta['expanded_terms'])) {
            $queries = array_merge($queries, $searchMeta['expanded_terms']);
        }
        $queries = array_values(array_unique(array_filter(array_map('trim', $queries))));
        $queries = array_slice($queries, 0, 6);

        $fromMeili = $this->buildAiChatRankedRowsByMeilisearch($mid, $queries, $fetchLimit);
        if ($fromMeili !== null) {
            return $this->applySemanticEmbeddingRerank($question, $fromMeili, $mid);
        }

        $bucket = [];
        foreach ($queries as $idx => $query) {
            $weight = max(0.35, 1.0 - ($idx * 0.18));
            $rows = Db::name($cfg['table'])
                ->where($cfg['status'], 1)
                ->where($cfg['where'], 'like', '%' . addcslashes($query, '%_') . '%')
                ->field($cfg['id'].' as id,'.$cfg['name'].' as name,'.$cfg['en'].' as en,'.$cfg['pic'].' as pic,'.$cfg['actor'].' as actor,'.$cfg['hits'].' as hits')
                ->order($cfg['hits'].' desc,'.$cfg['id'].' desc')
                ->limit(max(16, intval($fetchLimit)))
                ->select();
            if (!is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                $id = intval($row['id']);
                $name = strtolower((string)$row['name']);
                $en = strtolower((string)$row['en']);
                $actor = strtolower((string)$row['actor']);
                $q = strtolower((string)$query);
                $score = 0.0;
                if ($name === $q || $en === $q) {
                    $score += 2.4 * $weight;
                }
                if (strpos($name, $q) !== false) {
                    $score += 1.6 * $weight;
                }
                if ($en !== '' && strpos($en, $q) !== false) {
                    $score += 1.1 * $weight;
                }
                if ($actor !== '' && strpos($actor, $q) !== false) {
                    $score += 0.8 * $weight;
                }
                $hits = intval($row['hits']);
                $score += min(log(1 + max(0, $hits)), 10) * 0.06;

                if (!isset($bucket[$id])) {
                    $bucket[$id] = $row;
                    $bucket[$id]['score'] = $score;
                    continue;
                }
                $bucket[$id]['score'] = max(floatval($bucket[$id]['score']), $score);
            }
        }

        $rows = array_values($bucket);
        usort($rows, function ($a, $b) {
            $sa = floatval($a['score']);
            $sb = floatval($b['score']);
            if ($sa === $sb) {
                $ha = intval($a['hits']);
                $hb = intval($b['hits']);
                if ($ha === $hb) {
                    return intval($b['id']) <=> intval($a['id']);
                }
                return $hb <=> $ha;
            }
            return ($sb > $sa) ? 1 : -1;
        });
        $rows = array_slice($rows, 0, max(1, intval($fetchLimit)));
        return $this->applySemanticEmbeddingRerank($question, $rows, $mid);
    }

    private function buildAiChatRankedRowsByMeilisearch($mid, array $queries, $fetchLimit)
    {
        if (!MeilisearchService::enabled() || empty($queries)) {
            return null;
        }
        $kind = '';
        if ((int)$mid === 1) {
            $kind = 'vod';
        } elseif ((int)$mid === 2) {
            $kind = 'art';
        } else {
            return null;
        }
        $fetchLimit = max(1, (int)$fetchLimit);
        $idScore = [];
        foreach ($queries as $idx => $query) {
            $query = trim((string)$query);
            if ($query === '') {
                continue;
            }
            $weight = max(0.35, 1.0 - ($idx * 0.18));
            $sr = MeilisearchService::search(
                $query,
                'kind = "' . $kind . '" AND recycle = 0 AND status = 1',
                max(16, $fetchLimit),
                0
            );
            if (empty($sr['ok']) || empty($sr['hits']) || !is_array($sr['hits'])) {
                continue;
            }
            foreach ($sr['hits'] as $pos => $hit) {
                if (empty($hit['id']) || !is_string($hit['id'])) {
                    continue;
                }
                if (!preg_match('/^' . preg_quote($kind, '/') . '_(\d+)$/', $hit['id'], $m)) {
                    continue;
                }
                $id = (int)$m[1];
                $score = $weight * (1.2 - min(1.0, $pos * 0.04));
                if (!isset($idScore[$id]) || $score > $idScore[$id]) {
                    $idScore[$id] = $score;
                }
            }
        }
        if (empty($idScore)) {
            return null;
        }
        $ids = array_keys($idScore);
        $cfg = $this->getMidConfig($mid);
        if (empty($cfg)) {
            return null;
        }
        $rows = Db::name($cfg['table'])
            ->where($cfg['status'], 1)
            ->where($cfg['id'], 'in', implode(',', $ids))
            ->field($cfg['id'].' as id,'.$cfg['name'].' as name,'.$cfg['en'].' as en,'.$cfg['pic'].' as pic,'.$cfg['actor'].' as actor,'.$cfg['hits'].' as hits')
            ->select();
        if (!is_array($rows)) {
            return null;
        }
        $out = [];
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            if (!isset($idScore[$id])) {
                continue;
            }
            $row['score'] = (float)$idScore[$id] + min(log(1 + max(0, intval($row['hits']))), 10) * 0.06;
            $out[] = $row;
        }
        if (empty($out)) {
            return null;
        }
        usort($out, function ($a, $b) {
            if ($a['score'] === $b['score']) {
                $ha = intval($a['hits']);
                $hb = intval($b['hits']);
                if ($ha === $hb) {
                    return intval($b['id']) <=> intval($a['id']);
                }
                return $hb <=> $ha;
            }
            return ($b['score'] > $a['score']) ? 1 : -1;
        });
        return array_slice($out, 0, $fetchLimit);
    }

    private function applySemanticEmbeddingRerank($question, array $rows, $mid)
    {
        $cfg = config('maccms.ai_search');
        if (!is_array($cfg) || empty($rows)) {
            return $rows;
        }
        if ((string)(isset($cfg['semantic_enabled']) ? $cfg['semantic_enabled'] : '0') !== '1') {
            return $rows;
        }
        $candidateLimit = max(3, intval(isset($cfg['semantic_candidates']) ? $cfg['semantic_candidates'] : 40));
        $weight = floatval(isset($cfg['semantic_weight']) ? $cfg['semantic_weight'] : 0.45);
        if ($weight < 0) {
            $weight = 0.0;
        }
        if ($weight > 1) {
            $weight = 1.0;
        }
        $rows = array_slice($rows, 0, $candidateLimit);
        $inputs = [(string)$question];
        foreach ($rows as $row) {
            $inputs[] = $this->buildAiEmbedSnippet($mid, $row);
        }
        $vectors = $this->requestOpenAiEmbeddings($inputs, $cfg);
        if (!is_array($vectors) || count($vectors) !== count($inputs)) {
            return $rows;
        }
        $qv = $vectors[0];
        foreach ($rows as $idx => $row) {
            $sim = $this->vecCosineSimilarity($qv, $vectors[$idx + 1]);
            $base = floatval($row['score']);
            $rows[$idx]['score'] = ($base * (1.0 - $weight)) + ($sim * $weight * 3.0);
        }
        usort($rows, function ($a, $b) {
            if ($a['score'] === $b['score']) {
                return intval($b['hits']) <=> intval($a['hits']);
            }
            return ($b['score'] > $a['score']) ? 1 : -1;
        });
        return $rows;
    }

    private function requestOpenAiEmbeddings(array $inputs, array $cfg)
    {
        $provider = strtolower(trim((string)(isset($cfg['provider']) ? $cfg['provider'] : '')));
        $apiKey = trim((string)(isset($cfg['api_key']) ? $cfg['api_key'] : ''));
        if ($provider !== 'openai' || $apiKey === '') {
            return null;
        }
        $apiBase = rtrim((string)(isset($cfg['api_base']) ? $cfg['api_base'] : ''), '/');
        if ($apiBase === '') {
            $apiBase = 'https://api.openai.com/v1';
        }
        $model = trim((string)(isset($cfg['embedding_model']) ? $cfg['embedding_model'] : 'text-embedding-3-small'));
        if ($model === '') {
            $model = 'text-embedding-3-small';
        }
        $timeout = max(3, intval(isset($cfg['timeout']) ? $cfg['timeout'] : 12));
        $post = ['model' => $model, 'input' => $inputs];
        $headers = ['Content-Type: application/json', 'Authorization: Bearer '.$apiKey];
        $respBody = HttpClient::curlPostWithTimeout(
            $apiBase.'/embeddings',
            json_encode($post, JSON_UNESCAPED_UNICODE),
            $headers,
            $timeout
        );
        if ($respBody === false || $respBody === '') {
            return null;
        }
        $json = json_decode((string)$respBody, true);
        if (!is_array($json) || empty($json['data']) || !is_array($json['data'])) {
            return null;
        }
        usort($json['data'], function ($a, $b) {
            return intval(isset($a['index']) ? $a['index'] : 0) <=> intval(isset($b['index']) ? $b['index'] : 0);
        });
        $vectors = [];
        foreach ($json['data'] as $item) {
            if (empty($item['embedding']) || !is_array($item['embedding'])) {
                return null;
            }
            $vectors[] = $item['embedding'];
        }
        return $vectors;
    }

    private function vecCosineSimilarity(array $a, array $b)
    {
        $n = min(count($a), count($b));
        if ($n < 1) {
            return 0.0;
        }
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $va = floatval($a[$i]);
            $vb = floatval($b[$i]);
            $dot += $va * $vb;
            $na += $va * $va;
            $nb += $vb * $vb;
        }
        if ($na <= 0.0 || $nb <= 0.0) {
            return 0.0;
        }
        return $dot / (sqrt($na) * sqrt($nb));
    }

    private function buildAiEmbedSnippet($mid, array $row)
    {
        $parts = [
            isset($row['name']) ? (string)$row['name'] : '',
            isset($row['en']) ? (string)$row['en'] : '',
            isset($row['actor']) ? (string)$row['actor'] : '',
            isset($row['hits']) ? 'hits:'.intval($row['hits']) : '',
            'mid:'.intval($mid),
        ];
        return implode(' | ', array_filter($parts));
    }

    private function buildCard($mid, array $row)
    {
        $detailUrl = '';
        if ($mid === 1 || $mid === 12) {
            $detailUrl = mac_url_vod_detail(['vod_id'=>$row['id'],'vod_name'=>$row['name'],'vod_en'=>'','type_id'=>0,'type'=>['type_en'=>''],'type_1'=>['type_id'=>0,'type_en'=>''],'vod_time'=>time()]);
        } elseif ($mid === 13) {
            $detailUrl = mac_url_manga_detail(['manga_id'=>$row['id'],'manga_name'=>$row['name'],'manga_en'=>isset($row['en']) ? $row['en'] : '']);
        } elseif ($mid === 2) {
            $detailUrl = mac_url_art_detail(['art_id'=>$row['id'],'art_name'=>$row['name'],'art_en'=>'','type_id'=>0,'type'=>['type_en'=>''],'type_1'=>['type_id'=>0,'type_en'=>''],'art_time'=>time()]);
        } elseif ($mid === 3) {
            $detailUrl = mac_url_topic_detail(['topic_id'=>$row['id'],'topic_en'=>isset($row['en']) ? $row['en'] : '']);
        } elseif ($mid === 8) {
            $detailUrl = mac_url_actor_detail(['actor_id'=>$row['id'],'actor_en'=>isset($row['en']) ? $row['en'] : '']);
        } elseif ($mid === 9) {
            $detailUrl = mac_url_role_detail(['role_id'=>$row['id'],'role_en'=>isset($row['en']) ? $row['en'] : '']);
        } elseif ($mid === 11) {
            $detailUrl = mac_url_website_detail(['website_id'=>$row['id'],'website_en'=>isset($row['en']) ? $row['en'] : '']);
        }
        return [
            'id' => intval($row['id']),
            'mid' => intval($mid),
            'name' => isset($row['name']) ? (string)$row['name'] : '',
            'actor' => isset($row['actor']) ? trim((string)$row['actor']) : '',
            'img' => mac_url_img(isset($row['pic']) ? (string)$row['pic'] : ''),
            'url' => (string)$detailUrl,
        ];
    }

    private function buildContent($question, array $cards, array $searchMeta, array $aiSearchResults = [], array $externalFederated = [], $responseLang = 'zh')
    {
        $overview = $this->requestKeywordOverviewReply($question, $responseLang);
        $resources = $this->buildResourcesNarrativeBlock($cards, $searchMeta, $aiSearchResults, $externalFederated, $responseLang);
        $parts = [];
        if (trim($overview) !== '') {
            $parts[] = trim($overview);
        }
        if (trim($resources) !== '') {
            $parts[] = trim($resources);
        }
        return implode("\n\n", $parts);
    }

    /**
     * After the keyword overview: on-site matches (if any) plus external/AI rows as title + description text.
     */
    private function buildResourcesNarrativeBlock(array $cards, array $searchMeta, array $aiSearchResults, array $externalFederated, $responseLang)
    {
        $chunks = [];
        if (!empty($cards)) {
            $lines = [];
            foreach (array_slice($cards, 0, 5) as $idx => $card) {
                $extra = $card['actor'] !== '' ? '('.$card['actor'].')' : '';
                $lines[] = ($idx + 1).'. '.$card['name'].$extra;
            }
            if (!empty($searchMeta['expanded_terms']) && is_array($searchMeta['expanded_terms'])) {
                $lines[] = '';
                if ($responseLang === 'zh') {
                    $lines[] = '扩展检索词：'.implode('、', array_slice($searchMeta['expanded_terms'], 0, 4)).'。';
                } else {
                    $lines[] = 'Expanded terms: '.implode(', ', array_slice($searchMeta['expanded_terms'], 0, 4)).'.';
                }
            }
            $hdr = $responseLang === 'zh' ? '站内相关内容：' : 'Related on this site:';
            $chunks[] = $hdr."\n".implode("\n", $lines);
        }
        $merged = $this->mergeExternalStyleRows($externalFederated, $aiSearchResults);
        $extText = $this->buildExternalOnlyDescriptionContent($merged);
        if ($extText !== '') {
            $hdr2 = $responseLang === 'zh' ? '更多参考与说明：' : 'More references and descriptions:';
            $chunks[] = $hdr2."\n\n".$extText;
        }
        return implode("\n\n", array_filter($chunks));
    }

    private function mergeExternalStyleRows(array $externalFederated, array $aiSearchResults)
    {
        $out = [];
        $seen = [];
        foreach ([$externalFederated, $aiSearchResults] as $list) {
            if (empty($list) || !is_array($list)) {
                continue;
            }
            foreach ($list as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $u = isset($row['url']) ? strtolower(trim((string)$row['url'])) : '';
                if ($u === '') {
                    $t = isset($row['title']) ? strtolower(trim((string)$row['title'])) : '';
                    if ($t === '') {
                        continue;
                    }
                    $k = 't:'.$t;
                } else {
                    $k = 'u:'.$u;
                }
                if (isset($seen[$k])) {
                    continue;
                }
                $seen[$k] = 1;
                $out[] = $row;
            }
        }
        return $out;
    }

    /**
     * When there are no CMS cards, present only external-style rows as plain descriptions
     * (title + optional snippet). No wording about the local database or “not found”.
     *
     * @param array $rows Items with keys title, snippet (optional)
     */
    private function buildExternalOnlyDescriptionContent(array $rows)
    {
        if (empty($rows) || !is_array($rows)) {
            return '';
        }
        $lines = [];
        foreach (array_slice($rows, 0, 5) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $title = isset($item['title']) ? trim((string)$item['title']) : '';
            if ($title === '') {
                continue;
            }
            $snippet = isset($item['snippet']) ? trim(strip_tags((string)$item['snippet'])) : '';
            if (mb_strlen($snippet, 'UTF-8') > 220) {
                $snippet = mb_substr($snippet, 0, 217, 'UTF-8') . '…';
            }
            if ($snippet !== '') {
                $lines[] = $title . "\n" . $snippet;
            } else {
                $lines[] = $title;
            }
        }
        return implode("\n\n", $lines);
    }

    private function buildAiChatEnrichedAnswer($question, array $cards, $module, array $searchMeta, $responseLang = 'zh')
    {
        if (empty($cards)) {
            return '';
        }
        $titles = [];
        foreach (array_slice($cards, 0, 5) as $card) {
            $titles[] = $card['name'];
        }
        $expanded = [];
        if (!empty($searchMeta['expanded_terms']) && is_array($searchMeta['expanded_terms'])) {
            $expanded = array_slice($searchMeta['expanded_terms'], 0, 4);
        }
        if ($responseLang === 'zh') {
            $prompt = "用户问题：{$question}\n"
                ."当前结果标题：".implode('、', $titles)."\n"
                ."模块：{$module}\n"
                ."扩展词：".(empty($expanded) ? '无' : implode('、', $expanded))."\n"
                ."请用中文输出："
                ."1）2-3句对以上结果的补充解读；"
                ."2）1句推荐继续筛选方向（如题材/演员/年份）；"
                ."3）语言简洁，避免编造不存在的剧情细节。";
        } else {
            $prompt = "User query: {$question}\n"
                ."Current result titles: ".implode(', ', $titles)."\n"
                ."Module: {$module}\n"
                ."Expanded terms: ".(empty($expanded) ? 'none' : implode(', ', $expanded))."\n"
                ."Please respond in ".$this->languageLabel($responseLang)." with:\n"
                ."1) 2-3 concise sentences with extra interpretation;\n"
                ."2) 1 sentence suggesting next filtering direction (genre/cast/year);\n"
                ."3) avoid fabricating unavailable plot details.";
        }
        return $this->requestNormalChatReply($prompt, $responseLang);
    }

    /**
     * Short explanation of what the keyword/topic usually means (comes first in the reply body).
     */
    private function requestKeywordOverviewReply($keyword, $responseLang = 'zh')
    {
        $keyword = trim((string)$keyword);
        if ($keyword === '') {
            return '';
        }
        if ($responseLang === 'zh') {
            $system = '你是影视与文娱内容的助理，只用简体中文作答。';
            $user = "用户输入的关键词或短语：「{$keyword}」\n"
                ."请用 2～4 句简短说明：它通常指什么、常见于什么语境（类型或含义层面即可）。\n"
                ."要求：语气自然；不要提到数据库、站内、站外、是否搜到结果或链接；不要编造具体未经验证的剧情细节；总字数约 120 字以内。";
        } else {
            $system = 'You help visitors of an entertainment/media site. Reply only in '.$this->languageLabel($responseLang).'.';
            $user = "Keyword or phrase: \"{$keyword}\"\n"
                ."Write 2–4 short sentences: what it usually refers to and in what context (genre/meaning level is enough).\n"
                ."Rules: natural tone; do not mention databases, on-site/off-site search, whether anything was found, or links; "
                .'do not invent specific unverified plot facts; about 120 words or fewer.';
        }
        return $this->requestOpenAiChat(
            [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            0.45
        );
    }

    private function requestNormalChatReply($question, $responseLang = 'zh')
    {
        return $this->requestOpenAiChat(
            [
                ['role' => 'system', 'content' => 'You are a helpful assistant for a movie/content website. Reply in concise '.$this->languageLabel($responseLang).'.'],
                ['role' => 'user', 'content' => (string)$question],
            ],
            0.6
        );
    }

    /**
     * @param array $messages OpenAI chat messages (system + user, etc.)
     */
    private function requestOpenAiChat(array $messages, $temperature = 0.55)
    {
        $cfg = config('maccms.ai_search');
        if (!is_array($cfg)) {
            return '';
        }
        $provider = strtolower(trim((string)(isset($cfg['provider']) ? $cfg['provider'] : '')));
        $apiKey = trim((string)(isset($cfg['api_key']) ? $cfg['api_key'] : ''));
        if ($provider !== 'openai' || $apiKey === '') {
            return '';
        }
        $apiBase = rtrim((string)(isset($cfg['api_base']) ? $cfg['api_base'] : ''), '/');
        if ($apiBase === '') {
            $apiBase = 'https://api.openai.com/v1';
        }
        $model = trim((string)(isset($cfg['model']) ? $cfg['model'] : 'gpt-4o-mini'));
        if ($model === '') {
            $model = 'gpt-4o-mini';
        }
        $timeout = max(3, intval(isset($cfg['timeout']) ? $cfg['timeout'] : 12));
        $post = [
            'model' => $model,
            'temperature' => $temperature,
            'messages' => $messages,
        ];
        $headers = ['Content-Type: application/json', 'Authorization: Bearer '.$apiKey];
        $respBody = HttpClient::curlPostWithTimeout(
            $apiBase.'/chat/completions',
            json_encode($post, JSON_UNESCAPED_UNICODE),
            $headers,
            $timeout
        );
        if ($respBody === false || $respBody === '') {
            return '';
        }
        $json = json_decode((string)$respBody, true);
        if (!is_array($json)) {
            return '';
        }
        return !empty($json['choices'][0]['message']['content']) ? trim((string)$json['choices'][0]['message']['content']) : '';
    }

    /**
     * Legacy AiSearch "Search \"keyword\" on domain" Google site: links — omit from related links.
     */
    private function isLegacyGoogleSiteSearchExternalLink(array $item)
    {
        $title = isset($item['title']) ? trim((string)$item['title']) : '';
        $url = isset($item['url']) ? trim((string)$item['url']) : '';
        if ($title !== '' && preg_match('/^search\s+".*"\s+on\s+\S+/iu', $title)) {
            return true;
        }
        if ($url !== '' && stripos($url, 'google.') !== false && strpos($url, '/search') !== false
            && (stripos($url, 'site%3a') !== false || stripos($url, 'site%3A') !== false)) {
            return true;
        }
        return false;
    }

    /**
     * Ensure every external related link has a short description for the UI.
     */
    private function enrichExternalSnippet(array $item)
    {
        $s = isset($item['snippet']) ? trim(strip_tags((string)$item['snippet'])) : '';
        if ($s !== '') {
            return $this->truncatePlainText($s, 420);
        }
        $url = isset($item['url']) ? trim((string)$item['url']) : '';
        $host = ($url !== '') ? parse_url($url, PHP_URL_HOST) : '';
        $host = ($host !== null && $host !== '') ? (string)$host : '';
        $prov = isset($item['provider']) ? trim((string)$item['provider']) : '';
        if ($host !== '' && $prov !== '') {
            return $this->truncatePlainText($prov . ' · ' . $host, 420);
        }
        if ($host !== '') {
            return $host;
        }
        return '';
    }

    private function truncatePlainText($text, $max)
    {
        $text = (string)$text;
        if ($text === '' || mb_strlen($text, 'UTF-8') <= $max) {
            return $text;
        }
        return mb_substr($text, 0, max(1, $max - 1), 'UTF-8') . '…';
    }

    private function buildRelatedLinks(array $cards, array $searchMeta, array $aiSearchResults = [], array $externalFederated = [], $responseLang = 'zh', array $catalogLinks = [])
    {
        $links = [];
        foreach (array_slice($cards, 0, 5) as $card) {
            if (empty($card['url'])) {
                continue;
            }
            $links[] = ['title' => $card['name'], 'url' => $card['url'], 'source' => 'site'];
        }
        if (!empty($searchMeta['external_resources']) && is_array($searchMeta['external_resources'])) {
            foreach ($searchMeta['external_resources'] as $item) {
                if (empty($item['url']) || empty($item['title'])) {
                    continue;
                }
                if ($this->isLegacyGoogleSiteSearchExternalLink($item)) {
                    continue;
                }
                $links[] = ['title' => (string)$item['title'], 'url' => (string)$item['url'], 'source' => 'external'];
            }
        }
        if (!empty($aiSearchResults)) {
            foreach ($aiSearchResults as $item) {
                if (empty($item['url']) || empty($item['title'])) {
                    continue;
                }
                $links[] = [
                    'title' => (string)$item['title'],
                    'url' => (string)$item['url'],
                    'source' => 'external',
                    'img' => isset($item['img']) ? (string)$item['img'] : '',
                    'snippet' => $this->enrichExternalSnippet($item),
                    'provider' => isset($item['provider']) ? (string)$item['provider'] : 'openai',
                    'source_type' => isset($item['source_type']) ? (string)$item['source_type'] : '',
                    'resource_links' => $this->normalizeResourceLinks(isset($item['resource_links']) && is_array($item['resource_links']) ? $item['resource_links'] : [], $responseLang),
                ];
            }
        }
        if (!empty($externalFederated)) {
            foreach ($externalFederated as $item) {
                if (empty($item['url']) || empty($item['title'])) {
                    continue;
                }
                $links[] = [
                    'title' => (string)$item['title'],
                    'url' => (string)$item['url'],
                    'source' => 'external',
                    'img' => isset($item['img']) ? (string)$item['img'] : '',
                    'snippet' => $this->enrichExternalSnippet($item),
                    'provider' => isset($item['provider']) ? (string)$item['provider'] : '',
                    'source_type' => isset($item['source_type']) ? (string)$item['source_type'] : '',
                    'resource_links' => $this->normalizeResourceLinks(isset($item['resource_links']) && is_array($item['resource_links']) ? $item['resource_links'] : [], $responseLang),
                ];
            }
        }
        foreach ($catalogLinks as $item) {
            if (!is_array($item) || empty($item['url']) || empty($item['title'])) {
                continue;
            }
            $links[] = [
                'title' => (string)$item['title'],
                'url' => (string)$item['url'],
                'source' => 'external',
                'img' => '',
                'snippet' => $this->enrichExternalSnippet($item),
                'provider' => isset($item['provider']) ? (string)$item['provider'] : '',
                'source_type' => 'catalog',
                'resource_links' => [],
            ];
        }

        $dedup = [];
        $out = [];
        foreach ($links as $item) {
            $key = strtolower(trim((string)$item['url']));
            if ($key === '' || isset($dedup[$key])) {
                continue;
            }
            $dedup[$key] = 1;
            $out[] = $item;
            if (count($out) >= 12) {
                break;
            }
        }
        return $out;
    }

    private function normalizeResourceLinks(array $links, $responseLang = 'zh')
    {
        $out = [];
        foreach ($links as $lnk) {
            if (!is_array($lnk) || empty($lnk['url'])) {
                continue;
            }
            $title = isset($lnk['title']) ? trim((string)$lnk['title']) : '';
            $titleLower = strtolower($title);
            if ($responseLang === 'zh') {
                if ($titleLower === 'detail') {
                    $title = '详情';
                } elseif ($titleLower === 'link') {
                    $title = '链接';
                } elseif ($titleLower === 'mobile') {
                    $title = '移动端';
                } elseif ($titleLower === 'share') {
                    $title = '分享页';
                }
            } else {
                if ($title === '详情') {
                    $title = 'Detail';
                } elseif ($title === '链接') {
                    $title = 'Link';
                }
            }
            $out[] = ['title' => $title, 'url' => (string)$lnk['url']];
            if (count($out) >= 4) {
                break;
            }
        }
        return $out;
    }

    private function buildExternalCards(array $externalFederated, array $aiSearchResults)
    {
        $raw = array_merge($externalFederated, $aiSearchResults);
        $cards = [];
        foreach ($raw as $item) {
            $title = isset($item['title']) ? trim((string)$item['title']) : '';
            $url = isset($item['url']) ? trim((string)$item['url']) : '';
            if ($title === '' || $url === '') {
                continue;
            }
            $cards[] = [
                'name' => $title,
                'url' => $url,
                'img' => isset($item['img']) ? (string)$item['img'] : '',
                'actor' => '',
                'snippet' => isset($item['snippet']) ? (string)$item['snippet'] : '',
            ];
        }
        $dedup = [];
        $out = [];
        foreach ($cards as $card) {
            $k = strtolower(trim((string)$card['url']));
            if ($k === '' || isset($dedup[$k])) {
                continue;
            }
            $dedup[$k] = 1;
            $out[] = $card;
            if (count($out) >= 8) {
                break;
            }
        }
        return $out;
    }

    private function buildSuggestions($question, array $cards, $module, $responseLang = 'zh')
    {
        $suggestions = [];
        if (!empty($cards[0]['name'])) {
            if ($responseLang === 'zh') {
                $suggestions[] = '推荐更多类似《'.$cards[0]['name'].'》的内容';
                $suggestions[] = '《'.$cards[0]['name'].'》还有哪些相关作品？';
            } else {
                $suggestions[] = 'Recommend more content like '.$cards[0]['name'];
                $suggestions[] = 'What other works are related to '.$cards[0]['name'].'?';
            }
        }
        if ($responseLang === 'zh') {
            $suggestions[] = '继续搜索：'.$question.' '.$this->buildTag($module);
        } else {
            $suggestions[] = 'Continue searching: '.$question.' '.$this->buildTag($module);
        }
        return array_values(array_unique(array_filter($suggestions)));
    }

    private function requestAiSearchResults($question, $module, array $cards, $responseLang = 'zh')
    {
        $cfg = config('maccms.ai_search');
        if (!is_array($cfg)) {
            return [];
        }
        $provider = strtolower(trim((string)(isset($cfg['provider']) ? $cfg['provider'] : '')));
        $apiKey = trim((string)(isset($cfg['api_key']) ? $cfg['api_key'] : ''));
        if ($provider !== 'openai' || $apiKey === '') {
            return [];
        }

        $maxLinks = max(1, min(6, intval(isset($cfg['external_max_links']) ? $cfg['external_max_links'] : 4)));
        $apiBase = rtrim((string)(isset($cfg['api_base']) ? $cfg['api_base'] : ''), '/');
        if ($apiBase === '') {
            $apiBase = 'https://api.openai.com/v1';
        }
        $model = trim((string)(isset($cfg['model']) ? $cfg['model'] : 'gpt-4o-mini'));
        if ($model === '') {
            $model = 'gpt-4o-mini';
        }
        $timeout = max(3, intval(isset($cfg['timeout']) ? $cfg['timeout'] : 12));

        $seedTitles = [];
        foreach (array_slice($cards, 0, 3) as $card) {
            if (!empty($card['name'])) {
                $seedTitles[] = (string)$card['name'];
            }
        }
        $domainHint = trim((string)(isset($cfg['external_domains']) ? $cfg['external_domains'] : ''));
        $userPrompt = "User query: {$question}\n"
            ."CMS module: {$module}\n"
            ."Top CMS seed titles: ".(empty($seedTitles) ? 'N/A' : implode(', ', $seedTitles))."\n"
            ."Preferred domains: ".($domainHint === '' ? 'N/A' : $domainHint)."\n"
            ."Output language for title/snippet: ".$this->languageLabel($responseLang)."\n"
            ."Task:\n"
            ."1) Return up to {$maxLinks} high-quality references most relevant to the query.\n"
            ."2) Prioritize authoritative entertainment sources (official pages, Wikipedia, IMDb, major media).\n"
            ."3) Prefer links that help users decide what to watch/read (synopsis, cast, reviews, release context).\n"
            ."4) Keep results diverse; avoid near-duplicate pages from same path.\n"
            ."5) If Preferred domains are provided, prefer them when quality is comparable.\n"
            ."6) Do not invent URLs. Only return likely real pages.\n"
            ."7) Snippet should be concise and actionable (<= 140 chars).";

        $post = [
            'model' => $model,
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Return strict JSON only. Schema: {"results":[{"title":"...","url":"https://...","snippet":"...","image":"https://...","source_type":"official|wiki|database|news|review","resource_links":[{"title":"...","url":"https://..."}]}]}. '
                        .'Rules: '
                        .'(a) Must return valid absolute http/https URLs only. '
                        .'(b) No markdown, no explanations outside JSON. '
                        .'(c) No duplicate URLs. '
                        .'(d) Prefer high-trust sources and query relevance. '
                        .'(e) Keep snippet short, factual, and useful.',
                ],
                [
                    'role' => 'user',
                    'content' => $userPrompt,
                ],
            ],
        ];
        $headers = ['Content-Type: application/json', 'Authorization: Bearer '.$apiKey];
        $respBody = HttpClient::curlPostWithTimeout(
            $apiBase.'/chat/completions',
            json_encode($post, JSON_UNESCAPED_UNICODE),
            $headers,
            $timeout
        );
        if ($respBody === false || $respBody === '') {
            return [];
        }
        $json = json_decode((string)$respBody, true);
        $content = isset($json['choices'][0]['message']['content']) ? (string)$json['choices'][0]['message']['content'] : '';
        if ($content === '') {
            return [];
        }
        $parsed = json_decode($content, true);
        if (!is_array($parsed) || empty($parsed['results']) || !is_array($parsed['results'])) {
            return [];
        }

        $out = [];
        foreach ($parsed['results'] as $item) {
            $title = isset($item['title']) ? trim((string)$item['title']) : '';
            $url = isset($item['url']) ? trim((string)$item['url']) : '';
            $snippet = isset($item['snippet']) ? trim((string)$item['snippet']) : '';
            $img = isset($item['image']) ? trim((string)$item['image']) : '';
            if ($title === '' || $url === '') {
                continue;
            }
            if (!$this->isValidHttpUrl($url)) {
                continue;
            }
            if ($img !== '' && !$this->isValidHttpUrl($img)) {
                $img = '';
            }
            $resourceLinks = [];
            if (!empty($item['resource_links']) && is_array($item['resource_links'])) {
                foreach ($item['resource_links'] as $lnk) {
                    if (empty($lnk['url']) || empty($lnk['title'])) {
                        continue;
                    }
                    $u = trim((string)$lnk['url']);
                    if (!$this->isValidHttpUrl($u)) {
                        continue;
                    }
                    $resourceLinks[] = ['title' => trim((string)$lnk['title']), 'url' => $u];
                    if (count($resourceLinks) >= 4) {
                        break;
                    }
                }
            }
            $out[] = [
                'title' => $title,
                'url' => $url,
                'snippet' => $snippet,
                'img' => $img,
                'source' => 'external',
                'provider' => 'openai',
                'source_type' => isset($item['source_type']) ? (string)$item['source_type'] : '',
                'resource_links' => $resourceLinks,
            ];
            if (count($out) >= $maxLinks) {
                break;
            }
        }
        return $out;
    }

    private function resolveResponseLanguage($question)
    {
        $cfg = config('maccms.ai_search');
        $lang = 'auto';
        if (is_array($cfg) && isset($cfg['response_language'])) {
            $lang = strtolower(trim((string)$cfg['response_language']));
        }
        $allow = ['auto', 'zh', 'en', 'ja', 'ko', 'fr', 'es', 'de', 'pt'];
        if (!in_array($lang, $allow, true)) {
            $lang = 'auto';
        }
        if ($lang !== 'auto') {
            return $lang;
        }
        return preg_match('/[\x{4e00}-\x{9fff}]/u', (string)$question) ? 'zh' : 'en';
    }

    private function languageLabel($code)
    {
        $map = [
            'zh' => 'Chinese',
            'en' => 'English',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'fr' => 'French',
            'es' => 'Spanish',
            'de' => 'German',
            'pt' => 'Portuguese',
        ];
        return isset($map[$code]) ? $map[$code] : 'English';
    }

    private function isValidHttpUrl($url)
    {
        $url = trim((string)$url);
        if ($url === '') {
            return false;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        return $scheme === 'http' || $scheme === 'https';
    }

    private function buildTag($module)
    {
        $module = strtolower((string)$module);
        if ($module === 'plot') {
            return lang('index/ai_chat_tag_vod');
        }
        if ($module === 'manga') {
            return lang('manga');
        }
        if (!in_array($module, ['mixed', 'vod', 'art', 'topic', 'actor', 'role', 'website'], true)) {
            $module = 'default';
        }
        return lang('index/ai_chat_tag_'.$module);
    }

    private function getMidConfig($mid)
    {
        $map = [
            1 => ['table'=>'Vod','id'=>'vod_id','name'=>'vod_name','en'=>'vod_en','pic'=>'vod_pic','actor'=>'vod_actor','status'=>'vod_status','where'=>'vod_name|vod_sub|vod_actor|vod_tag|vod_blurb','hits'=>'vod_hits'],
            2 => ['table'=>'Art','id'=>'art_id','name'=>'art_name','en'=>'art_en','pic'=>'art_pic','actor'=>'art_from','status'=>'art_status','where'=>'art_name|art_sub|art_tag|art_blurb','hits'=>'art_hits'],
            3 => ['table'=>'Topic','id'=>'topic_id','name'=>'topic_name','en'=>'topic_en','pic'=>'topic_pic','actor'=>'topic_remarks','status'=>'topic_status','where'=>'topic_name|topic_sub|topic_tag|topic_blurb','hits'=>'topic_hits'],
            8 => ['table'=>'Actor','id'=>'actor_id','name'=>'actor_name','en'=>'actor_en','pic'=>'actor_pic','actor'=>'actor_alias','status'=>'actor_status','where'=>'actor_name|actor_alias|actor_tag|actor_works','hits'=>'actor_hits'],
            9 => ['table'=>'Role','id'=>'role_id','name'=>'role_name','en'=>'role_en','pic'=>'role_pic','actor'=>'role_actor','status'=>'role_status','where'=>'role_name|role_actor|role_remarks','hits'=>'role_hits'],
            11 => ['table'=>'Website','id'=>'website_id','name'=>'website_name','en'=>'website_en','pic'=>'website_pic','actor'=>'website_remarks','status'=>'website_status','where'=>'website_name|website_sub|website_tag|website_blurb','hits'=>'website_hits'],
            12 => ['table'=>'Vod','id'=>'vod_id','name'=>'vod_name','en'=>'vod_en','pic'=>'vod_pic','actor'=>'vod_actor','status'=>'vod_status','where'=>'vod_plot_name|vod_plot_detail|vod_name','hits'=>'vod_hits'],
            13 => ['table'=>'Manga','id'=>'manga_id','name'=>'manga_name','en'=>'manga_en','pic'=>'manga_pic','actor'=>'manga_author','status'=>'manga_status','where'=>'manga_name|manga_sub|manga_tag|manga_blurb|manga_author','hits'=>'manga_hits'],
        ];
        return isset($map[$mid]) ? $map[$mid] : [];
    }
}
