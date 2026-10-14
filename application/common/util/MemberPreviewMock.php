<?php
namespace app\common\util;

/**
 * 主题设计预览：模拟会员登录态（仅 td_preview + 有效 td_pt 时生效，不写 Cookie）。
 */
class MemberPreviewMock
{
    const MODES = array('guest', 'member', 'vip');

    /**
     * @return string guest|member|vip
     */
    public static function requestMode()
    {
        if (!ThemePreviewAuth::isActive()) {
            return 'guest';
        }
        $mode = 'member';
        if (function_exists('request')) {
            $req = request();
            if (is_object($req)) {
                $raw = strtolower(trim((string) $req->param('td_member_mock')));
                if (in_array($raw, self::MODES, true)) {
                    $mode = $raw;
                }
            }
        }
        return $mode;
    }

    /**
     * @param string $mode guest|member|vip
     * @return array|null null = 不覆盖
     */
    public static function mockUser($mode)
    {
        $mode = strtolower(trim((string) $mode));
        if (!in_array($mode, self::MODES, true) || $mode === 'guest') {
            return null;
        }
        $group_list = array();
        if (function_exists('model')) {
            $group_list = model('Group')->getCache();
        }
        if (empty($group_list) || !is_array($group_list)) {
            $group_list = array(
                2 => array('group_name' => '普通会员'),
                3 => array('group_name' => 'VIP'),
            );
        }
        $gid = ($mode === 'vip') ? 3 : 2;
        if (!isset($group_list[$gid])) {
            $gid = 2;
        }
        $group = isset($group_list[$gid]) ? $group_list[$gid] : array('group_name' => '会员');
        $end = ($mode === 'vip') ? date('Y-m-d H:i:s', time() + 86400 * 30) : '';
        return array(
            'user_id' => 900000001,
            'user_name' => ($mode === 'vip') ? '预览VIP会员' : '预览会员',
            'user_portrait' => '',
            'group_id' => (string) $gid,
            'group' => $group,
            'user_points' => 100,
            'user_end_time' => $end,
            'points' => 100,
            'vip_nav' => ($mode === 'vip') ? 1 : 0,
        );
    }

    /**
     * 预览模式下覆盖 $GLOBALS['user']。
     *
     * @param array $user
     * @return array
     */
    public static function applyToUser(array $user)
    {
        $mock = self::mockUser(self::requestMode());
        if ($mock === null) {
            return $user;
        }
        return array_merge($user, $mock);
    }
}
