<?php
namespace app\common\model {
    class Base {
        public function refreshListTypeRelation($result) { return $result; }
    }
}
namespace think {
    class Db {
        public static function name($name) { return new self; }
        public function field($value) { return $this; }
        public function where($value) { return $this; }
        public function orderRaw($expression, array $bind = [])
        {
            $GLOBALS['queryOrder'] = [$expression, $bind];
            return $this;
        }
        public function limit($value) { return $this; }
        public function select() { return []; }
    }
    class Cache {
        public static function get($key) { $GLOBALS['cacheKey'] = $key; }
    }
}
namespace app\common\util {
    class MeilisearchService {
        public static function enabled() { return $GLOBALS['searchMode'] !== 'off'; }
    }
    class MeilisearchListBridge {
        public static function applyForActor($where, $wd, $name, $page, $num, $start, $order)
        {
            return ['where' => $where, 'order' => $GLOBALS['searchMode'] === 'hits' ? 'actor_id DESC' : $order, 'total' => 0];
        }
    }
}
namespace {
    function lang($key) { return $key; }
    function mac_param_url() { return []; }
    function model($name) {
        return new class {
            public function getCache($key) { return []; }
        };
    }
    function checkActorOrder($actual, $expected, $message)
    {
        if ($actual !== $expected) {
            throw new \RuntimeException($message);
        }
        echo "OK: $message\n";
    }

    require isset($argv[1]) ? $argv[1] : __DIR__ . '/../../application/common/model/Actor.php';
    $actor = new \app\common\model\Actor();
    $GLOBALS['config']['app'] = ['cache_flag' => 'test', 'cache_time' => 0, 'cache_core' => 0];
    $searchMode = 'off';
    $tag = array_fill_keys(explode(' ', 'order by type ids paging pageurl level wd name area letter sex starsign blood start num half timeadd timehits time hitsmonth hitsweek hitsday hits not cachetime typenot'), '');
    $tag['by'] = 'in';
    $tag['name'] = "O'Reilly,示例";
    $actor->listCacheData($tag);
    $expected = ['FIND_IN_SET(actor_name, :actor_order_names)', ['actor_order_names' => [$tag['name'], \PDO::PARAM_STR]]];
    checkActorOrder($queryOrder, $expected, 'ordered names reach the query only as a bound string');
    $firstCacheKey = $cacheKey;
    $tag['name'] = "示例,O'Reilly";
    $actor->listCacheData($tag);
    checkActorOrder($cacheKey === $firstCacheKey, false, 'different name order uses a different cache key');
    $expected[1]['actor_order_names'][0] = $tag['name'];

    $searchMode = 'empty';
    $actor->listCacheData($tag);
    checkActorOrder($queryOrder, $expected, 'retained search order retains its binding');
    $searchMode = 'hits';
    $actor->listCacheData($tag);
    checkActorOrder($queryOrder, ['actor_id DESC', []], 'replaced search order drops unused binding');

    $searchMode = 'off';
    $tag['by'] = 'time';
    $actor->listCacheData($tag);
    checkActorOrder($queryOrder, ['actor_time desc', []], 'ordinary list sorting remains unchanged');
    $tag['by'] = 'rnd';
    $actor->listCacheData($tag);
    checkActorOrder($queryOrder, [['[rand]' => '[rand]'], []], 'random list sorting remains unchanged');
}
