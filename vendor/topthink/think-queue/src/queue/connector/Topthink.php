<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2015 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\queue\connector;

use think\exception\HttpException;
use think\queue\Connector;
use think\Request;
use think\queue\job\Topthink as TopthinkJob;
use think\Response;

class Topthink extends Connector
{
    protected $options = [
        'token'       => '',
        'project_id'  => '',
        'protocol'    => 'https',
        'host'        => 'qns.topthink.com',
        'port'        => 443,
        'api_version' => 1,
        'max_retries' => 3,
        'default'     => 'default'
    ];

    /** @var  Request */
    protected $request;

    protected $url;

    protected $curl = null;

    protected $last_status;

    protected $headers = [];

    public function __construct($options)
    {
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }

        $this->url = "{$this->options['protocol']}://{$this->options['host']}:{$this->options['port']}/v{$this->options['api_version']}/";

        $this->headers['Authorization'] = "Bearer {$this->options['token']}";

        $this->request = Request::instance();
    }

    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw(0, $queue, $this->createPayload($job, $data));
    }

    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->pushRaw($delay, $queue, $this->createPayload($job, $data));
    }

    public function release($queue, $job, $delay)
    {
        return $this->pushRaw($delay, $queue, $job->payload, $job->attempts);
    }

    public function marshal()
    {
        $job = new TopthinkJob($this, $this->marshalPushedJob(), $this->request->header('topthink-message-queue'));
        if ($this->request->header('topthink-message-status') == 'success') {
            $job->fire();
        } else {
            $job->failed();
        }
        return new Response('OK');
    }

    public function pushRaw($delay, $queue, $payload, $attempts = 0)
    {
        $queue_name = $this->getQueue($queue);
        $queue      = rawurlencode($queue_name);
        $url        = "project/{$this->options['project_id']}/queue/{$queue}/message";
        $message    = [
            'payload'  => $payload,
            'attempts' => $attempts,
            'delay'    => $delay
        ];

        return $this->apiCall('POST', $url, $message)->id;
    }

    public function deleteMessage($queue, $id)
    {
        $queue = rawurlencode($queue);
        $url   = "project/{$this->options['project_id']}/queue/{$queue}/message/{$id}";
        return $this->apiCall('DELETE', $url);
    }

    protected function apiCall($type, $url, $params = [])
    {
        $url = "{$this->url}$url";

        if ($this->curl == null) {
            $this->curl = curl_init();
        }

        switch ($type = strtoupper($type)) {
            case 'DELETE':
                curl_setopt($this->curl, CURLOPT_URL, $url);
                curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $type);
                curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($params));
                break;
            case 'PUT':
                curl_setopt($this->curl, CURLOPT_URL, $url);
                curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $type);
                curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($params));
                break;
            case 'POST':
                curl_setopt($this->curl, CURLOPT_URL, $url);
                curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $type);
                curl_setopt($this->curl, CURLOPT_POST, true);
                curl_setopt($this->curl, CURLOPT_POSTFIELDS, $params);
                break;
            case 'GET':
                curl_setopt($this->curl, CURLOPT_POSTFIELDS, null);
                curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $type);
                curl_setopt($this->curl, CURLOPT_HTTPGET, true);
                $url .= '?' . http_build_query($params);
                curl_setopt($this->curl, CURLOPT_URL, $url);
                break;
        }

        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);

        $headers = [];
        foreach ($this->headers as $k => $v) {
            if ($k == 'Connection') {
                $v = 'Close';
            }
            $headers[] = "$k: $v";
        }

        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, 10);

        return $this->callWithRetries();
    }

    protected function callWithRetries()
    {
        for ($retry = 0; $retry < $this->options['max_retries']; $retry++) {
            $out = curl_exec($this->curl);
            if ($out === false) {
                $this->reportHttpError(0, curl_error($this->curl));
            }
            $this->last_status = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);

            if ($this->last_status >= 200 && $this->last_status < 300) {
                return self::jsonDecode($out);
            } elseif ($this->last_status >= 500) {
                self::waitRandomInterval($retry);
            } else {
                $this->reportHttpError($this->last_status, $out);
            }
        }
        $this->reportHttpError($this->last_status, "Service unavailable");
        return;
    }

    protected static function jsonDecode($response)
    {
        $data = json_decode($response);

        $json_error = json_last_error();
        if ($json_error != JSON_ERROR_NONE) {
            throw new \RuntimeException($json_error);
        }

        return $data;
    }

    protected static function waitRandomInterval($retry)
    {
        $max_delay = pow(4, $retry) * 100 * 1000;
        usleep(rand(0, $max_delay));
    }

    protected function reportHttpError($status, $text)
    {
        throw new HttpException($status, "http error: {$status} | {$text}");
    }

    /**
     * Marshal out the pushed job and payload.
     *
     * @return object
     */
    protected function marshalPushedJob()
    {
        return (object) [
            'id'       => $this->request->header('topthink-message-id'),
            'payload'  => $this->request->getContent(),
            'attempts' => $this->request->header('topthink-message-attempts')
        ];
    }

    public function __destruct()
    {
        if ($this->curl != null) {
            curl_close($this->curl);
            $this->curl = null;
        }
    }

    public function pop($queue = null)
    {
        throw new \RuntimeException('pop queues not support for this type');
    }
}
