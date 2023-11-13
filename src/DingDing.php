<?php

namespace Yunkeweb\ThinkphpDingdingLog;

use think\contract\LogHandlerInterface;

class DingDing implements LogHandlerInterface
{
    protected array $config = [
        'title' => '项目报警日志',
        'webhook' => '',
        'secret_key' => '',
        'time_format'  => 'c',
        'curl_opt_timeout' => 5,
        'curl_opt_connect_timeout' => 10
    ];

    public function __construct($config = [])
    {
        if (is_array($config)) {
            $this->config = array_merge($this->config, $config);
        }
    }


    public function save(array $log): bool
    {
        $info = [];

        // 日志信息封装
        $time = \DateTime::createFromFormat('0.u00 U', microtime())->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format($this->config['time_format']);
        foreach ($log as $type => $val) {
            foreach ($val as $msg) {
                if (!is_string($msg)) {
                    $msg = var_export($msg, true);
                }
                $info[] = sprintf("> 日志时间: %s\n> \n> 日志级别: %s\n> \n> 日志内容: %s",$time,$type,$msg);
            }
        }

        if ($info) {
            return $this->write($info);
        }

        return true;
    }

    protected function write(array $message):bool
    {
        $text = trim(implode("\n\n",$message));

        $data = [
            'msgtype' => 'markdown',
            'markdown' => [
                'title' => $this->config['title'],
                'text' => $text
            ],
            'at'=>['isAtAll'=>true]
        ];

        $timestamp = time()*1000;

        $sign = $this->genSign($timestamp);

        $this->post($data,$timestamp,$sign);

        return true;
    }

    protected function genSign(int $timestamp): string
    {
        $stringToSign = $timestamp . "\n" . $this->config['secret_key'];
        return base64_encode(hash_hmac('sha256', $stringToSign, $this->config['secret_key'], true));
    }

    protected function post(array $data,$timestamp,$sign)
    {
        $uri = $this->config['webhook']."&timestamp={$timestamp}&sign={$sign}";
        $ch = curl_init();
        // 设置cURL选项
        curl_setopt($ch, CURLOPT_URL,$uri);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        // 设置等待响应时间（单位：秒）
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->config['curl_opt_connect_timeout']); // 连接超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['curl_opt_timeout']); // 总请求超时时间
        // 执行cURL会话并获取响应
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}