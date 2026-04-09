<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | 百度地图：须「服务端」类型 AK + 地理编码接口 + 服务器出口 IP 白名单。
    | 浏览器端 AK（Referer 校验）不能用于本项目的服务端请求。
    | browser_ak：仅用于后台「地图选点」加载 JS API，须单独创建「浏览器端」应用并配置 Referer。
    */
    'baidu_map' => [
        'ak' => env('BAIDU_MAP_AK'),
        'browser_ak' => env('BAIDU_MAP_BROWSER_AK', ''),
    ],

];
