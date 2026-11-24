<?php
use app\common\util\HttpClient;
require __DIR__ . '/../../application/common/util/HttpClient.php';
$allow = 'http://media.lan:8080, https://other.lan';
$cases = [
    [HttpClient::configuredCollectOrigin('http://media.lan:8080/api?q=1', $allow), 'http://media.lan:8080'],
    [HttpClient::configuredCollectOrigin('HTTPS://OTHER.LAN/api', $allow), 'https://other.lan:443'],
    [HttpClient::configuredCollectOrigin('http://media.lan/api', $allow), ''],
    [HttpClient::configuredCollectOrigin('https://media.lan:8080/api', $allow), ''],
    [HttpClient::configuredCollectOrigin('http://media.lan.equivalent.test:8080/api', $allow), ''],
    [HttpClient::configuredCollectOrigin('http://media.lan:8080/api', false), ''],
    [HttpClient::isPrivateCollectIp('10.12.0.1'), true],
    [HttpClient::isPrivateCollectIp('172.20.0.1'), true],
    [HttpClient::isPrivateCollectIp('192.168.0.1'), true],
    [HttpClient::isPrivateCollectIp('127.0.0.1'), false],
    [HttpClient::isPrivateCollectIp('169.254.0.1'), false],
    [HttpClient::isPrivateCollectIp('100.64.0.1'), false],
    [HttpClient::isPrivateCollectIp('::1'), false],
];
foreach ($cases as $i => list($actual, $expected)) {
    if ($actual !== $expected) {
        throw new RuntimeException('Collection policy case ' . ($i + 1) . ' failed');
    }
}
echo 'PASS: ' . count($cases) . " collection configuration checks\n";
