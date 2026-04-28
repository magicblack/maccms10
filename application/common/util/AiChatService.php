<?php
namespace app\common\util;

use think\Db;

class AiChatService
{
    public function buildPayload($question, $mid, $limit)
    {
        $moduleMap = [1=>'vod',2=>'art',3=>'topic',8=>'actor',9=>'role',11=>'website',12=>'plot'];
        $module = ($mid === 0) ? 'mixed' : (isset($moduleMap[$mid]) ? $moduleMap[$mid] : 'mixed');
        $searchMeta = ['expanded_terms' => [], 'external_resources' => []];

        if ($mid === 0) {
            try {
                $searchMeta = $this->mergeSearchMetaPayloads(
                    AiSearch::buildForSearch('vod', ['wd' => $question]),
                    AiSearch::buildForSearch('art', ['wd' => $question])
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
        return [
            'tag' => $this->buildTag($module),
            'cms_hit' => !empty($cards),
            'cms_count' => count($cards),
            'cards' => $cards,
            'content' => $this->buildContent($question, $cards, $searchMeta),
            'enriched_answer' => $this->buildAiChatEnrichedAnswer($question, $cards, $module, $searchMeta),
            'related_links' => $this->buildRelatedLinks($cards, $searchMeta),
            'suggestions' => $this->buildSuggestions($question, $cards, $module),
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
        $targets = ($mid === 0) ? [1, 2] : [$mid];
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

    private function buildContent($question, array $cards, array $searchMeta)
    {
        if (empty($cards)) {
            $reply = $this->requestNormalChatReply($question);
            if ($reply !== '') {
                return $reply;
            }
            return 'No CMS content found for "'.$question.'". Try shorter keywords or different wording.';
        }
        $lines = [];
        foreach (array_slice($cards, 0, 5) as $idx => $card) {
            $extra = $card['actor'] !== '' ? '('.$card['actor'].')' : '';
            $lines[] = ($idx + 1).'. '.$card['name'].$extra;
        }
        if (!empty($searchMeta['expanded_terms'])) {
            $lines[] = '';
            $lines[] = 'Expanded terms: '.implode(', ', array_slice($searchMeta['expanded_terms'], 0, 4)).'.';
        }
        return implode("\n", $lines);
    }

    private function buildAiChatEnrichedAnswer($question, array $cards, $module, array $searchMeta)
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
        $prompt = "用户问题：{$question}\n"
            ."当前结果标题：".implode('、', $titles)."\n"
            ."模块：{$module}\n"
            ."扩展词：".(empty($expanded) ? '无' : implode('、', $expanded))."\n"
            ."请用中文输出："
            ."1）2-3句对以上结果的补充解读；"
            ."2）1句推荐继续筛选方向（如题材/演员/年份）；"
            ."3）语言简洁，避免编造不存在的剧情细节。";
        return $this->requestNormalChatReply($prompt);
    }

    private function requestNormalChatReply($question)
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
            'temperature' => 0.6,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant for a movie/content website. Reply in concise Chinese.'],
                ['role' => 'user', 'content' => (string)$question],
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
            return '';
        }
        $json = json_decode((string)$respBody, true);
        if (!is_array($json)) {
            return '';
        }
        return !empty($json['choices'][0]['message']['content']) ? trim((string)$json['choices'][0]['message']['content']) : '';
    }

    private function buildRelatedLinks(array $cards, array $searchMeta)
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
                $links[] = ['title' => (string)$item['title'], 'url' => (string)$item['url'], 'source' => 'external'];
            }
        }
        return array_slice($links, 0, 8);
    }

    private function buildSuggestions($question, array $cards, $module)
    {
        $suggestions = [];
        if (!empty($cards[0]['name'])) {
            $suggestions[] = 'Recommend more content like '.$cards[0]['name'];
            $suggestions[] = 'What other works are related to '.$cards[0]['name'].'?';
        }
        $suggestions[] = 'Continue searching: '.$question.' '.$this->buildTag($module);
        return array_values(array_unique(array_filter($suggestions)));
    }

    private function buildTag($module)
    {
        $module = strtolower((string)$module);
        if ($module === 'plot') {
            return lang('index/ai_chat_tag_vod');
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
        ];
        return isset($map[$mid]) ? $map[$mid] : [];
    }
}
