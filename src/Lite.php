<?php

namespace PhalApi\AliyunSms;

require_once __DIR__.'/lib/TokenGetterForAlicom.php';
require_once __DIR__.'/lib/TokenForAlicom.php';

use Aliyun\Api\Sms\Request\V20170525\QuerySendDetailsRequest;
use Aliyun\Api\Sms\Request\V20170525\SendBatchSmsRequest;
use Aliyun\Api\Sms\Request\V20170525\SendSmsRequest;
use Aliyun\Core\Config;
use Aliyun\Core\DefaultAcsClient;
use Aliyun\Core\Profile\DefaultProfile;
use AliyunMNS\Exception\MnsException;
use AliyunMNS\Requests\BatchReceiveMessageRequest;

Config::load();

class Lite
{
    protected $config;

    protected $client;

    protected $tokenGetter;

    public function __construct($config = null)
    {
        $di = \PhalApi\DI();
        $this->config = $config;
        if (null === $this->config) {
            $this->config = $di->config->get('app.AliyunSms');
        }
        $accessKeyId = $this->config['accessKeyId'];
        $accessKeySecret = $this->config['accessKeySecret'];
        $endPointName = $this->config['endpoint'];
        try {
            $product = 'Dysmsapi';
            $domain = 'dysmsapi.aliyuncs.com';
            $region = 'cn-hangzhou';
            $profile = DefaultProfile::getProfile($region, $accessKeyId, $accessKeySecret);
            DefaultProfile::addEndpoint($endPointName, $region, $product, $domain);
            $acsClient = new DefaultAcsClient($profile);
            $this->client = $acsClient;
        } catch (ClientException $e) {
            $di->logger->error(__NAMESPACE__, __FUNCTION__, ['ClientException' => $e->getErrorMessage()]);
        }
    }

    public function getClient()
    {
        return $this->client;
    }

    public function getConfig()
    {
        return $this->config;
    }

    /**
     * 发送短信
     *
     * @return stdClass
     */
    public function sendSms($phone_no, $sign, $tpl_code, $tpl_param_array, $out_id = null, $extend_code = null)
    {
        $di = \PhalApi\DI();
        try {
            $request = new SendSmsRequest();
            $request->setProtocol('https');
            $request->setPhoneNumbers($phone_no);
            $request->setSignName($sign);
            $request->setTemplateCode($tpl_code);
            if (!empty($tpl_param_array)) {
                $request->setTemplateParam(json_encode($tpl_param_array, JSON_UNESCAPED_UNICODE));
            }
            if (!empty($out_id)) {
                $request->setOutId($out_id);
            }
            if (!empty($extend_code)) {
                $request->setSmsUpExtendCode($extend_code);
            }
            $acsResponse = $this->client->getAcsResponse($request);

            return $acsResponse;
        } catch (ClientException $e) {
            $di->logger->error(__NAMESPACE__, __FUNCTION__, ['ClientException' => $e->getErrorMessage()]);

            return false;
        }
    }

    /**
     * 批量发送短信
     *
     * @return stdClass
     */
    public function sendBatchSms($phone_no_array, $sign_array, $tpl_code, $tpl_param_array, $extend_code = null)
    {
        $di = \PhalApi\DI();
        try {
            $request = new SendBatchSmsRequest();
            $request->setProtocol('https');
            $request->setPhoneNumberJson(json_encode($phone_no_array, JSON_UNESCAPED_UNICODE));
            $request->setSignNameJson(json_encode($sign_array, JSON_UNESCAPED_UNICODE));
            $request->setTemplateCode($tpl_code);
            if (!empty($tpl_param_array)) {
                $request->setTemplateParamJson(json_encode($tpl_param_array, JSON_UNESCAPED_UNICODE));
            }
            if (!empty($extend_code)) {
                $request->setSmsUpExtendCode($extend_code);
            }
            $acsResponse = $this->client->getAcsResponse($request);

            return $acsResponse;
        } catch (ClientException $e) {
            $di->logger->error(__NAMESPACE__, __FUNCTION__, ['ClientException' => $e->getErrorMessage()]);

            return false;
        }
    }

    /**
     * 短信发送记录查询.
     *
     * @return stdClass
     */
    public function querySendDetails($phone_no, $date_ymd, $biz_id)
    {
        $di = \PhalApi\DI();
        try {
            $request = new QuerySendDetailsRequest();
            $request->setProtocol('https');
            $request->setPhoneNumber($phone_no);
            $request->setSendDate($date_ymd);
            $request->setPageSize(10);
            $request->setCurrentPage(1);
            if (!empty($biz_id)) {
                $request->setBizId($biz_id);
            }
            $acsResponse = $this->client->getAcsResponse($request);

            return $acsResponse;
        } catch (ClientException $e) {
            $di->logger->error(__NAMESPACE__, __FUNCTION__, ['ClientException' => $e->getErrorMessage()]);

            return false;
        }
    }

    /**
     * @var TokenGetterForAlicom
     */
    public function getTokenGetter()
    {
        $accountId = '1943695596114318'; // 此处不需要替换修改!
        $accessKeyId = $this->config['accessKeyId'];
        $accessKeySecret = $this->config['accessKeySecret'];
        if ($this->$tokenGetter == null) {
            $this->$tokenGetter = new TokenGetterForAlicom(
                $accountId,
                $accessKeyId,
                $accessKeySecret
            );
        }

        return $this->$tokenGetter;
    }

    /**
     * 获取消息.
     *
     * @param string   $messageType 消息类型
     * @param string   $queueName   在云通信页面开通相应业务消息后，就能在页面上获得对应的queueName<br/>(e.g. Alicom-Queue-xxxxxx-xxxxxReport)
     * @param callable $callback    <p>
     *                              回调仅接受一个消息参数;
     *                              <br/>回调返回true，则工具类自动删除已拉取的消息;
     *                              <br/>回调返回false,消息不删除可以下次获取.
     *                              <br/>(e.g. function ($message) { return true; }
     *                              </p>
     */
    public function receiveMsg($messageType, $queueName, callable $callback)
    {
        $di = \PhalApi\DI();
        $i = 0;
        // 取回执消息失败3次则停止循环拉取
        while ($i < 3) {
            try {
                $tokenForAlicom = $this->getTokenGetter()->getTokenByMessageType($messageType, $queueName);
                $queue = $tokenForAlicom->getClient()->getQueueRef($queueName);
                // 超时等待时间2秒
                $message = $queue->receiveMessage(2);
                // 计算消息体的摘要用作校验
                $bodyMD5 = strtoupper(md5(base64_encode($message->getMessageBody())));
                // 比对摘要，防止消息被截断或发生错误
                if ($bodyMD5 == $message->getMessageBodyMD5()) {
                    // 执行回调
                    if (call_user_func($callback, json_decode($message->getMessageBody()))) {
                        // 当回调返回真值时，删除已接收的信息
                        $receiptHandle = $message->getReceiptHandle();
                        $queue->deleteMessage($receiptHandle);
                    }
                }

                return;
            } catch (MnsException $e) {
                ++$i;
                $di->logger->error(__NAMESPACE__, __FUNCTION__, ['MnsException' => $e->getMnsErrorCode()]);
            }
        }
    }

    /**
     * 获取批量消息.
     *
     * @param string   $messageType 消息类型
     * @param string   $queueName   在云通信页面开通相应业务消息后，就能在页面上获得对应的queueName<br/>(e.g. Alicom-Queue-xxxxxx-xxxxxReport)
     * @param callable $callback    <p>
     *                              回调仅接受一个消息参数;
     *                              <br/>回调返回true，则工具类自动删除已拉取的消息;
     *                              <br/>回调返回false,消息不删除可以下次获取.
     *                              <br/>(e.g. function ($message) { return true; }
     *                              </p>
     */
    public function receiveBatchMsg($messageType, $queueName, callable $callback)
    {
        $di = \PhalApi\DI();
        $i = 0;
        // 取回执消息失败3次则停止循环拉取
        while ($i < 3) {
            try {
                $tokenForAlicom = $this->getTokenGetter()->getTokenByMessageType($messageType, $queueName);
                $queue = $tokenForAlicom->getClient()->getQueueRef($queueName);
                // 每次拉取10条，超时等待时间5秒
                $res = $queue->batchReceiveMessage(new BatchReceiveMessageRequest(10, 5));
                /* @var \AliyunMNS\Model\Message[] $messages */
                $messages = $res->getMessages();
                foreach ($messages as $message) {
                    // 计算消息体的摘要用作校验
                    $bodyMD5 = strtoupper(md5(base64_encode($message->getMessageBody())));
                    // 比对摘要，防止消息被截断或发生错误
                    if ($bodyMD5 == $message->getMessageBodyMD5()) {
                        // 执行回调
                        if (call_user_func($callback, json_decode($message->getMessageBody()))) {
                            // 当回调返回真值时，删除已接收的信息
                            $receiptHandle = $message->getReceiptHandle();
                            $queue->deleteMessage($receiptHandle);
                        }
                    }
                }

                return;
            } catch (MnsException $e) {
                ++$i;
                $di->logger->error(__NAMESPACE__, __FUNCTION__, ['MnsException' => $e->getMnsErrorCode()]);
            }
        }
    }
}
