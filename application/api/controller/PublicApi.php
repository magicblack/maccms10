<?php

namespace app\api\controller;

trait PublicApi
{
    public function check_config()
    {
        if ($GLOBALS['config']['api']['publicapi']['status'] != 1) {
            echo 'closed';
            die;
        }

        if ($GLOBALS['config']['api']['publicapi']['charge'] == 1) {
            $h = $_SERVER['REMOTE_ADDR'];
            if (!$h) {
                echo lang('api/auth_err');
                exit;
            } else {
                $auth = $GLOBALS['config']['api']['publicapi']['auth'];
                $this->checkDomainAuth($auth);
            }
        }
    }

    private function checkDomainAuth($auth)
    {
        $ip = mac_get_client_ip();
        $auth_list = ['127.0.0.1'];
        if (!empty($auth)) {
            foreach (explode('#', $auth) as $domain) {
                $domain = trim($domain);
                $auth_list[] = $domain;
                if (!mac_string_is_ip($domain)) {
                    $auth_list[] = gethostbyname($domain);
                }
            }
            $auth_list = array_unique($auth_list);
            $auth_list = array_filter($auth_list);
        }
        if (!in_array($ip, $auth_list)) {
            echo lang('api/auth_err');
            exit;
        }
    }

    protected function format_sql_string($str)
    {
        $str = preg_replace('/\b(SELECT|INSERT|UPDATE|DELETE|DROP|UNION|WHERE|FROM|JOIN|INTO|VALUES|SET|AND|OR|NOT|EXISTS|HAVING|GROUP BY|ORDER BY|LIMIT|OFFSET)\b/i', '', $str);
        $str = preg_replace('/[^\w\s\-\.]/', '', $str);
        $str = trim(preg_replace('/\s+/', ' ', $str));
        return $str;
    }
}