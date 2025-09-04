<?php
namespace app\api\controller;

use think\Request;
use think\Db;

class Manga extends Base
{
    use PublicApi;
    public function __construct()
    {
        parent::__construct();
        $this->check_config();
    }

    public function get_list(Request $request)
    {
        $param = $request->param();
        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => '参数错误: ' . $validate->getError(),
            ]);
        }
        $param['page'] = intval($param['page']) < 1 ? 1 : intval($param['page']);
        $param['limit'] = intval($param['limit']) < 1 ? 20 : intval($param['limit']);

        $where = [];
        $where['manga_status'] = ['eq', 1];

        if (!empty($param['t'])) {
            $where['type_id'] = ['eq', $param['t']];
        }
        if (!empty($param['ids'])) {
            $where['manga_id'] = ['in', $param['ids']];
        }
        if (!empty($param['wd'])) {
            $param['wd'] = trim($param['wd']);
            $where['manga_name'] = ['like', '%' . $param['wd'] . '%'];
        }

        $order = 'manga_time desc';
        if (!empty($param['order'])) {
            $order = $param['order'];
        }

        $data = model('Manga')->listData($where, $order, $param['page'], $param['limit']);
        return json($data);
    }

    public function get_detail(Request $request)
    {
        $param = $request->param();
        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => '参数错误: ' . $validate->getError(),
            ]);
        }
        $where = [];
        $where['manga_status'] = ['eq', 1];

        if (!empty($param['id'])) {
            $where['manga_id'] = ['eq', $param['id']];
        }

        $data = model('Manga')->infoData($where);
        return json($data);
    }
}
