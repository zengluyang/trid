<?php

namespace app\controllers;

use Yii;

class TestController extends \app\controllers\RestController
{

    public function actionIndex() {
    	//var_dump(Yii::$app->easemobClient->url);
        $rlt = [
            'data'=>Yii::$app->easemobClient->accreditRegister(['username'=>'test1','password'=>'test1']),
        ];
        return json_encode($rlt,JSON_PRETTY_PRINT);
    }

    public function actionMock() {
    	$rlt = [
    		"access_token"=>"YWMtY-v4RkY1EeWPv5e4Y13MkwAAAVB5eoBMLNTDML5CNKk6vbmjlD-sv3p8Vx4",
    		"expires_in"=> 5184000,
    		"application"=> "0c6fc7c0-4584-11e5-ad97-bf184b66b711",
    		"data"=>"YWMtY-v4RkY1EeWPv5e4Y13MkwAAAVB5eoBMLNTDML5CNKk6vbmjlD-sv3p8Vx4",
    	];
    	return json_encode($rlt);
    }
}
