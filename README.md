# PhalApi 2.x 的阿里云SMS扩展
PhalApi 2.x扩展类库，基于Aliyun的SMS扩展。

## 安装和配置
修改项目下的composer.json文件，并添加：  
```
    "vivlong/phalapi-aliyun-sms":"dev-master"
```
然后执行```composer update```。  

安装成功后，添加以下配置到/path/to/phalapi/config/app.php文件：  
```php
    /**
     * 阿里云SMS相关配置
     */
    'AliyunSms' =>  array(
        'accessKeyId'       => '<yourAccessKeyId>',
        'accessKeySecret'   => '<yourAccessKeySecret>',
        'endpoint'          => 'cn-hangzhou',
    ),
```
并根据自己的情况修改填充。 

## 注册
在/path/to/phalapi/config/di.php文件中，注册：  
```php
$di->aliyunSms = function() {
        return new \PhalApi\AliyunSms\Lite();
};
```

## 使用
第一种使用方式：
```php
  \PhalApi\DI()->aliyunSms->sendSms('XXXX', 'XXXX', 'XXXX', array('code'=>'XXXX'));
```  

