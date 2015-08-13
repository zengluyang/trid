<?php

namespace app\controllers;

use Yii;

class TestController extends \app\controllers\RestController
{

    public function actionIndex() {
        $url = Yii::$app->request->hostInfo;
        $rlt = [
            'data'=>$url,
        ];
        return $rlt;
    }
}
