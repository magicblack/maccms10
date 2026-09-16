<?php
namespace app\admin\controller;
use think\Db;

class VodPlayer extends Base
{
    var $_pre;
    public function __construct()
    {
        parent::__construct();
        $this->_pre = 'vodplayer';
    }

    public function index()
    {
        $list = config($this->_pre);
        $this->assign('list',$list);
        $this->assign('title',lang('admin/vodplayer/title'));
        return $this->fetch('admin@vodplayer/index');
    }

    public function info()
    {
        $param = input();
        $list = config($this->_pre);
        if (Request()->isPost()) {
            $validate = \think\Loader::validate('Token');
            if(!$validate->check($param)){
                return $this->ajaxErrorWithFreshToken($validate->getError());
            }
            unset($param['__token__']);
            unset($param['flag']);
            $code = $param['code'];
            unset($param['code']);
            if(is_numeric($param['from'])){
                $param['from'] .='_';
            }
            if (strpos($param['from'], '.') !== false || strpos($param['from'], '/') !== false || strpos($param['from'], '\\') !== false) {
                $this->error(lang('param_err'));
                return;
            }
            //默认语言以条目自身字段为准，content_lang 只保存非默认语言的译文（见 mac_config_lang_overlay 注释）
            if (!isset($param['content_lang']) || !is_array($param['content_lang'])) {
                $param['content_lang'] = [];
            }
            unset($param['content_lang'][mac_content_lang_default()]);
            $list[$param['from']] = $param;
            $sort=[];
            foreach ($list as $k=>&$v){
                $sort[] = $v['sort'];
            }
            array_multisort($sort, SORT_DESC, SORT_FLAG_CASE , $list);

            $res = mac_arr2file( APP_PATH .'extra/'.$this->_pre.'.php', $list);
            if($res===false){
                return $this->ajaxErrorWithFreshToken(lang('write_err_config'));
            }

            $res = fwrite(fopen('./static/player/' . $param['from'].'.js','wb'),$code);
            if($res===false){
                return $this->ajaxErrorWithFreshToken(lang('wirte_err_codefile'));
            }
            cache('cache_data','1');
            return $this->success(lang('save_ok'));
        }

        $info = $list[$param['id']] ?? [];
        if(!empty($info)){
            $code = file_get_contents('./static/player/' . $param['id'].'.js');
            $info['code'] = $code;
        }

        //线路配置不落库，译文就近存在条目自身的 content_lang[lang_code] 里（见 mac_config_lang_overlay）
        $content_lang_default = mac_content_lang_default();
        $content_lang_enabled = array_values(array_unique(array_merge([$content_lang_default], mac_content_lang_allow_list())));
        $content_lang_data = (!empty($info['content_lang']) && is_array($info['content_lang'])) ? $info['content_lang'] : [];
        $this->assign('info',$info);
        $this->assign('content_lang_default', $content_lang_default);
        $this->assign('content_lang_enabled', $content_lang_enabled);
        $this->assign('content_lang_multi', count($content_lang_enabled) > 1);
        $this->assign('content_lang_data', $content_lang_data);
        $this->assign('content_translate_available', mac_content_translate_available());

        $this->assign('title',lang('admin/vodplayer/title'));
        return $this->fetch('admin@vodplayer/info');
    }

    /**
     * 调用已安装的翻译插件（若有）翻译单个字段；没有插件注册时返回空字符串，前端据此禁用按钮。
     */
    public function contentLangTranslate()
    {
        $text = (string)input('post.text', '');
        $from = (string)input('post.from', mac_content_lang_default());
        $to = (string)input('post.to', '');
        if ($text === '' || $to === '') {
            return $this->error(lang('param_err'));
        }
        if (!mac_content_translate_rate_limit($this->_admin['admin_id'])) {
            return $this->error(lang('frequently'));
        }
        $result = mac_content_translate($text, $from, $to, ['content_type' => 'vodplayer']);
        if ($result === '') {
            return $this->error(lang('admin/vod/content_lang_translate_err'));
        }
        return json(['code' => 1, 'msg' => 'ok', 'data' => $result]);
    }

    public function del()
    {
        $param = input();
        $list = config($this->_pre);
        unset($list[$param['ids']]);
        $res = mac_arr2file(APP_PATH. 'extra/'.$this->_pre.'.php', $list);
        if($res===false){
            return $this->error(lang('del_err'));
        }
        cache('cache_data','1');
        return $this->success(lang('del_ok'));
    }

    public function field()
    {
        $param = input();
        $ids = $param['ids'];
        $col = $param['col'];
        $val = $param['val'];

        if(!empty($ids) && in_array($col,['ps','status'])){
            $list = config($this->_pre);
            $ids = explode(',',$ids);
            foreach($list as $k=>&$v){
                if(in_array($k,$ids)){
                    $v[$col] = $val;
                }
            }
            $res = mac_arr2file(APP_PATH. 'extra/'.$this->_pre.'.php', $list);
            if($res===false){
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }
        return $this->error(lang('param_err'));
    }

    public function export()
    {
        $param = input();
        $list = config($this->_pre);
        $info = $list[$param['id']];
        if(!empty($info)){
            $code = file_get_contents('./static/player/' . $param['id'].'.js');
            $info['code'] = $code;
        }

        header("Content-type: application/octet-stream");
        if(strpos($_SERVER['HTTP_USER_AGENT'], "MSIE")) {
            header("Content-Disposition: attachment; filename=mac_" . urlencode($info['from']) . '.txt');
        }
        else{
            header("Content-Disposition: attachment; filename=mac_" . $info['from'] . '.txt');
        }
        echo base64_encode(json_encode($info));
    }

    public function import()
    {
        if (request()->isPost()) {
            $param = input();
            $validate = \think\Loader::validate('Token');
            if(!$validate->check($param)){
                return $this->ajaxErrorWithFreshToken($validate->getError());
            }
            unset($param['__token__']);
            $file = $this->request->file('file');
            $info = $file->rule('uniqid')->validate(['size' => 10240000, 'ext' => 'txt']);
            if ($info) {
                $data = json_decode(base64_decode(file_get_contents($info->getpathName())), true);
                @unlink($info->getpathName());
                if ($data) {
                    if (empty($data['status']) || empty($data['from']) || empty($data['sort'])) {
                        return $this->ajaxErrorWithFreshToken(lang('format_err'));
                    }
                    if (strpos($data['from'], '.') !== false || strpos($data['from'], '/') !== false || strpos($data['from'], '\\') !== false) {
                        $this->error(lang('param_err'));
                        return;
                    }
                    $code = $data['code'];
                    unset($data['code']);

                    $list = config($this->_pre);
                    $list[$data['from']] = $data;
                    $res = mac_arr2file(APP_PATH . 'extra/' . $this->_pre . '.php', $list);
                    if ($res === false) {
                        return $this->ajaxErrorWithFreshToken(lang('write_err_config'));
                    }

                    $res = fwrite(fopen('./static/player/' . $data['from'] . '.js', 'wb'), $code);
                    if ($res === false) {
                        return $this->ajaxErrorWithFreshToken(lang('wirte_err_codefile'));
                    }
                }
                return $this->success(lang('import_ok'));
            } else {
                return $this->ajaxErrorWithFreshToken($file->getError());
            }
        }
        else{
            return $this->fetch('admin@vodplayer/import');
        }
    }

}
