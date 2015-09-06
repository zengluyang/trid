<?php

namespace app\controllers;

use Yii;

class TestController extends \app\controllers\RestController
{

    public function actionIndex() {
        $rlt = [
            'data'=>Yii::$app->yuntongxunSmsClient->sendTemplateSMS("13800000000" ,array('6532','5'),"1"),
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

    public function actionCmdMsgSend() {
        $req_type = "cmd_msg_send_request";
        $rlt_type = "cmd_msg_send_result";

        $input = file_get_contents("php://input");
        $content = json_decode($input);

        if(json_last_error() != JSON_ERROR_NONE) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 1,
                "error_msg" => "json decode failed.",
            ];
            return json_encode($rlt);
        }

        if(!isset($content["peer_huanxin_id"])) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 2,
                "error_msg" =>"invalid input",
            ];
            return json_encode($rlt);
        }

        $target_type = "users";
        $target[] = $content["peer_huanxin_id"];
        $from = "admin";
        $msg = [
            "type" => "cmd",
            "action" => "test",
        ];

        $result = Yii::$app->easemobClient->yy_hxSend($from, $target, $msg, $target_type);

        $rlt = [
            "type" => $rlt_type,
            "success" => true,
            "error_no" => 0,
            "error_msg" => "",
            "result" => $result,
        ];

        return json_encode($rlt);
    }
}
