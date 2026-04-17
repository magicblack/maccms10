<?php

namespace app\api\controller;

use think\Db;
use think\Request;

/**
 * Auth 认证与权限控制器
 *
 * 提供当前用户状态和资源级权限判断接口，
 * 用于前端全站 AJAX 化时统一做登录态、VIP态、可播/可读/可下载判断。
 */
class Auth extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取当前登录用户基础信息
     * GET /api.php/auth/me
     *
     * 用途：前端全局状态、头像、昵称、会员到期等。
     * 前端调用时机：页面首屏初始化时调用一次；登录成功、退出登录后各再调一次。
     * 未登录也返回 code=1，但 info.is_login=0（不返回 HTML 错页）。
     *
     * @param Request $request
     * @return \think\response\Json
     */
    public function me(Request $request)
    {
        $check = model('User')->checkLogin();

        if ($check['code'] > 1) {
            // 未登录，返回稳定结构
            return json([
                'code' => 1,
                'msg'  => 'ok',
                'info' => [
                    'is_login'        => 0,
                    'user_id'         => 0,
                    'user_name'       => '',
                    'nick_name'       => '',
                    'group_id'        => 0,
                    'group_name'      => '',
                    'points'          => 0,
                    'user_portrait'   => '',
                    'vip_expire_time' => 0,
                ],
            ]);
        }

        $user = $check['info'];
        $groupName = '';
        if (!empty($user['group'])) {
            $groupName = $user['group']['group_name'] ?? '';
        }

        // 获取主要 group_id（取第一个）
        $groupIds = explode(',', $user['group_id']);
        $primaryGroupId = intval($groupIds[0]);

        return json([
            'code' => 1,
            'msg'  => 'ok',
            'info' => [
                'is_login'        => 1,
                'user_id'         => intval($user['user_id']),
                'user_name'       => $user['user_name'] ?? '',
                'nick_name'       => $user['user_nick_name'] ?? '',
                'group_id'        => $primaryGroupId,
                'group_name'      => $groupName,
                'points'          => intval($user['user_points'] ?? 0),
                'user_portrait'   => mac_get_user_portrait($user['user_id']),
                'vip_expire_time' => intval($user['user_end_time'] ?? 0),
            ],
        ]);
    }

    /**
     * 按"用户 + 资源"返回可操作权限位
     * GET /api.php/auth/permission
     *
     * 用途：用于按钮显示和点击拦截。
     *
     * 入参：
     *   mid    - 资源模块（1=视频 / 2=文章 / 6=漫画）
     *   id     - 资源 id（vod_id / art_id / manga_id）
     *   action - 可选，play|read|download|comment|favorite（不传则返回全部权限位）
     *
     * @param Request $request
     * @return \think\response\Json
     */
    public function permission(Request $request)
    {
        $param = $request->param();
        $mid = intval($param['mid'] ?? 0);
        $id  = intval($param['id'] ?? 0);
        $action = trim($param['action'] ?? '');

        // 基础参数验证
        if ($mid < 1 || $id < 1) {
            return json([
                'code' => 1001,
                'msg'  => '参数错误: mid 和 id 必须',
            ]);
        }

        // 允许的 action 值
        $allowedActions = ['play', 'read', 'download', 'comment', 'favorite'];
        if (!empty($action) && !in_array($action, $allowedActions)) {
            return json([
                'code' => 1001,
                'msg'  => '参数错误: action 值无效',
            ]);
        }

        // 1. 读取当前登录用户
        $check = model('User')->checkLogin();
        $isLogin = ($check['code'] == 1) ? 1 : 0;
        $user = $isLogin ? $check['info'] : null;

        // 判断是否为 VIP（group_id >= 3 且未过期）
        $isVip = 0;
        $userPoints = 0;
        $userGroupIds = [];
        if ($isLogin && $user) {
            $userPoints = intval($user['user_points'] ?? 0);
            $userGroupIds = array_map('intval', explode(',', $user['group_id']));
            if (max($userGroupIds) >= 3 && intval($user['user_end_time'] ?? 0) > time()) {
                $isVip = 1;
            }
        }

        // 2. 按 mid + id 查询资源基础信息
        $resource = null;
        $resourcePoints = 0;
        $resourceStatus = 0;
        $denyReason = '';

        switch ($mid) {
            case 1: // 视频
                $resource = Db::name('vod')->field('vod_id,vod_status,vod_points_play,vod_points_down,vod_pwd_play,vod_pwd_down,type_id')->where('vod_id', $id)->find();
                if ($resource) {
                    $resourceStatus = intval($resource['vod_status'] ?? 0);
                    $resourcePoints = intval($resource['vod_points_play'] ?? 0);
                }
                break;
            case 2: // 文章
                $resource = Db::name('art')->field('art_id,art_status,art_points,art_points_detail,type_id')->where('art_id', $id)->find();
                if ($resource) {
                    $resourceStatus = intval($resource['art_status'] ?? 0);
                    // 优先 art_points_detail（详情阅读积分），回退 art_points
                    $detailPoints = intval($resource['art_points_detail'] ?? 0);
                    $resourcePoints = $detailPoints > 0 ? $detailPoints : intval($resource['art_points'] ?? 0);
                }
                break;
            case 6: // 漫画
                $resource = Db::name('manga')->field('manga_id,manga_status,manga_points,type_id')->where('manga_id', $id)->find();
                if ($resource) {
                    $resourceStatus = intval($resource['manga_status'] ?? 0);
                    $resourcePoints = intval($resource['manga_points'] ?? 0);
                }
                break;
            default:
                return json([
                    'code' => 1001,
                    'msg'  => '参数错误: mid 不支持',
                ]);
        }

        // 资源不存在
        if (empty($resource)) {
            return json([
                'code' => 1,
                'msg'  => 'ok',
                'info' => [
                    'is_login'     => $isLogin,
                    'is_vip'       => $isVip,
                    'resource'     => ['mid' => $mid, 'id' => $id],
                    'can_play'     => 0,
                    'can_read'     => 0,
                    'can_download' => 0,
                    'can_comment'  => 0,
                    'can_favorite' => 0,
                    'deny_reason'  => 'RESOURCE_NOT_FOUND',
                ],
            ]);
        }

        // 资源下线
        if ($resourceStatus != 1) {
            return json([
                'code' => 1,
                'msg'  => 'ok',
                'info' => [
                    'is_login'     => $isLogin,
                    'is_vip'       => $isVip,
                    'resource'     => ['mid' => $mid, 'id' => $id],
                    'can_play'     => 0,
                    'can_read'     => 0,
                    'can_download' => 0,
                    'can_comment'  => 0,
                    'can_favorite' => 0,
                    'deny_reason'  => 'RESOURCE_OFFLINE',
                ],
            ]);
        }

        // 3. 结合用户会员组/VIP有效期/积分，计算 can_* 权限位

        // 是否需要 VIP（检查分类的 type_is_vip_exclusive）
        $typeId = intval($resource['type_id'] ?? 0);
        $typeIsVipExclusive = 0;
        if ($typeId > 0) {
            $typeInfo = Db::name('type')->field('type_id,type_extend')->where('type_id', $typeId)->find();
            if ($typeInfo && !empty($typeInfo['type_extend'])) {
                $typeExtend = json_decode($typeInfo['type_extend'], true);
                if (is_array($typeExtend)) {
                    $typeIsVipExclusive = intval($typeExtend['type_is_vip_exclusive'] ?? 0);
                }
            }
        }

        $canPlay     = 1;
        $canRead     = 1;
        $canDownload = 1;
        $canComment  = 1;
        $canFavorite = 1;

        // 未登录限制
        if (!$isLogin) {
            $canComment  = 0;
            $canFavorite = 0;
            $denyReason  = 'NOT_LOGIN';

            // 需要积分的资源未登录不可用
            if ($resourcePoints > 0) {
                $canPlay     = 0;
                $canRead     = 0;
                $canDownload = 0;
            }

            // VIP 专属资源未登录不可用
            if ($typeIsVipExclusive) {
                $canPlay     = 0;
                $canRead     = 0;
                $canDownload = 0;
                $denyReason  = 'VIP_REQUIRED';
            }
        } else {
            // 已登录

            // VIP 专属资源检查
            if ($typeIsVipExclusive && !$isVip) {
                $canPlay     = 0;
                $canRead     = 0;
                $canDownload = 0;
                $denyReason  = 'VIP_REQUIRED';
            }

            // 积分检查（VIP 用户免积分）
            if ($resourcePoints > 0 && !$isVip) {
                if ($userPoints < $resourcePoints) {
                    $canPlay     = 0;
                    $canRead     = 0;
                    $denyReason  = 'POINTS_NOT_ENOUGH';
                }
            }

            // 下载积分检查（视频模块有单独的 vod_points_down）
            if ($mid == 1 && !$isVip) {
                $downPoints = intval($resource['vod_points_down'] ?? 0);
                if ($downPoints > 0 && $userPoints < $downPoints) {
                    $canDownload = 0;
                    if (empty($denyReason)) {
                        $denyReason = 'POINTS_NOT_ENOUGH';
                    }
                }
            }

            // 用户组权限检查（通过 popedom）
            if ($typeId > 0) {
                $groupIdStr = $user['group_id'] ?? '1';
                // popedom 2=播放/阅读, 3=下载
                if (!model('User')->popedom($typeId, 2, $groupIdStr)) {
                    $canPlay = 0;
                    $canRead = 0;
                    if (empty($denyReason)) {
                        $denyReason = 'GROUP_PERMISSION_DENIED';
                    }
                }
                if (!model('User')->popedom($typeId, 3, $groupIdStr)) {
                    $canDownload = 0;
                    if (empty($denyReason)) {
                        $denyReason = 'GROUP_PERMISSION_DENIED';
                    }
                }
            }
        }

        // 如果全部可用，清空 deny_reason
        if ($canPlay && $canRead && $canDownload && $canComment && $canFavorite) {
            $denyReason = '';
        }

        // 构建结果
        $result = [
            'is_login'     => $isLogin,
            'is_vip'       => $isVip,
            'resource'     => ['mid' => $mid, 'id' => $id],
            'can_play'     => $canPlay,
            'can_read'     => $canRead,
            'can_download' => $canDownload,
            'can_comment'  => $canComment,
            'can_favorite' => $canFavorite,
            'deny_reason'  => $denyReason,
        ];

        // 如果指定了 action，只返回对应权限位
        if (!empty($action)) {
            $actionMap = [
                'play'     => 'can_play',
                'read'     => 'can_read',
                'download' => 'can_download',
                'comment'  => 'can_comment',
                'favorite' => 'can_favorite',
            ];
            $key = $actionMap[$action] ?? '';
            if ($key) {
                $filtered = [
                    'is_login'     => $isLogin,
                    'is_vip'       => $isVip,
                    'resource'     => ['mid' => $mid, 'id' => $id],
                    $key           => $result[$key],
                    'deny_reason'  => $result[$key] ? '' : $denyReason,
                ];
                $result = $filtered;
            }
        }

        return json([
            'code' => 1,
            'msg'  => 'ok',
            'info' => $result,
        ]);
    }
}
