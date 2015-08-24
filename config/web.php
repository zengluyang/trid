<?php

$params = require(__DIR__ . '/params.php');

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'components' => [
        'urlManager' => [
            'rules' => [
            ],
        ],
        'easemobClient'=>[
            'class'=>'app\components\EasemobClient',
            'client_id'=>'YXA6DG_HwEWEEeWtl78YS2a3EQ',
            'client_secret'=>'YXA6VgBNRbMukcP8_e9sv1qTIpschpA',
            'org_name'=>'tridtest',
            'app_name'=>'tridtest',
            'api_url'=>'https://a1.easemob.com/',
        ],
        'yuntongxunSmsClient'=>[
            'class'=>'app\components\YuntongxunSmsClient',
            'AccountSid'=>'aaf98f894f4fbec2014f5dc7d88305ea',
            'AccountToken'=>'eeb36c5a66a3442fb688a96850f3c93a',
            'AppId'=>'8a48b5514f4fc588014f5dc89b7a1496',
            'ServerIP'=>'sandboxapp.cloopen.com',
            'ServerPort'=>'8883',
            'SoftVersion'=>'2013-12-26',
        ],
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'Vj-q6_19tFgv0W2woNApcYR_W0pbJS5t',
            'enableCookieValidation' => false,
            'enableCsrfValidation' => false,
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            // send all mails to a file by default. You have to set
            // 'useFileTransport' to false and configure a transport
            // for the mailer to send real emails.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'response' => [
            'format' => yii\web\Response::FORMAT_JSON,
            'charset' => 'UTF-8',
            'class' => 'yii\web\Response',
            'on beforeSend' => function ($event) {
                $response = $event->sender;
                $response->format = yii\web\Response::FORMAT_JSON;
                if ($response->isSuccessful != true) {
                    $response->data = [
                        'success' => $response->isSuccessful,
                        'error_no' => -1,
                        'error_msg' => 'fatal error.',
                        'data' => $response->data,
                    ];
                }
            },
        ],
	'mongodb' => [
		'class' => '\yii\mongodb\Connection',
		'dsn' => 'mongodb://localhost:27017/local',
	],
	//      'db' => require(__DIR__ . '/db.php'),
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = 'yii\debug\Module';

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = 'yii\gii\Module';
}

return $config;
