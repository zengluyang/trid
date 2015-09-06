<?php

namespace app\controllers;

use Yii;

class TestController extends \app\controllers\RestController
{

    private $mongoCollection=null;

    public function actionIndex() {

        $m = new \MongoClient();
        $this->mongoCollection = $m->selectCollection('local','chatrecord'); 
        //db.chatrecord.find( { $or: [{from:"admin",to:"18615794931"},{from:"18615794931",to:"admin"}]}).sort([{timestamp:1}])
        $q = [
            '$or' => [
                ['from'=>'admin','to'=>'18615794931'],
                ['from'=>'18615794931','to'=>'admin'],

            ],
        ];
        $rlt = [
            'data'=>iterator_to_array($this->mongoCollection->find($q)->sort(['timestamp'=>1])),
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
        $content = json_decode($input, true);

        if(json_last_error() != JSON_ERROR_NONE) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 1,
                "error_msg" => "json decode failed.",
            ];
            return json_encode($rlt);
        }

        if(!isset($content["type"]) ||
            $content["type"] != $req_type ||
            !isset($content["huanxin_id"])) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 2,
                "error_msg" =>"invalid input",
            ];
            return json_encode($rlt);
        }
	
        $target_type = "users";
        $target[] = $content["huanxin_id"];
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
