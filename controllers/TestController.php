<?php

namespace app\controllers;

use Yii;

class TestController extends \app\controllers\RestController
{

    private $mongoCollection=null;

    public function actionIndex() {

        $m = new \MongoClient();
        $this->mongoCollection = $m->selectCollection($this->mongoDbName,'chatrecord'); 
        //db.chatrecord.find( { $or: [{from:"admin",to:"18615794931"},{from:"18615794931",to:"admin"}]}).sort([{timestamp:1}])
        $q = [
            '$or' => [
                ['from'=>'admin','to'=>'18615794931'],
                ['from'=>'18615794931','to'=>'admin'],

            ],
        ];
        $rlt = [
            'data'=>Yii::$app->easemobClient->chatRecord(),
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

    public function actionCurl() {
        $curl = curl_init (); // 启动一个CURL会话
        var_dump($curl);
        $url = "https://a1.easemob.com/tridtest/tridtest/chatmessages";
        $type = "GET";
        $header[] = "Authorization: Bearer YWMtt6e7PGN3EeWcjyvbP82RhgAAAVE5OxvWW5QyVvk0lInhjiAP-S_UG5DTgAw";
        //curl_setopt ( $curl, CURLOPT_PROXY, "http://10.169.95.174:3388");
        curl_setopt ( $curl, CURLOPT_URL, $url ); // 要访问的地址
        curl_setopt ( $curl, CURLOPT_SSL_VERIFYPEER, FALSE ); // 对认证证书来源的检查
        curl_setopt ( $curl, CURLOPT_SSL_VERIFYHOST, FALSE ); // 从证书中检查SSL加密算法是否存在
        curl_setopt ( $curl, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0; Trident/4.0)' ); // 模拟用户使用的浏览器
        curl_setopt ( $curl, CURLOPT_TIMEOUT, 30 ); // 设置超时限制防止死循环
        curl_setopt ( $curl, CURLOPT_HTTPHEADER, $header ); // 设置HTTP头
        curl_setopt ( $curl, CURLOPT_RETURNTRANSFER, 1 ); // 获取的信息以文件流的形式返回
        curl_setopt ( $curl, CURLOPT_CUSTOMREQUEST, $type );
        $result = curl_exec ( $curl ); // 执行操作
        //$res = object_array ( json_decode ( $result ) );
        //$res ['status'] = curl_getinfo ( $curl, CURLINFO_HTTP_CODE );
        //pre ( $res );
        curl_close ( $curl ); // 关闭CURL会话
        if($result==null) {
            return json_encode(['error'=>'network error.']);
        }
        $rlt_j = json_decode($result,true);
        if(isset($rlt_j['error']) && $rlt_j['error']=='auth_invalid') {
            //$this->refreshToken();
        }
        var_dump($result);
        //return $result;
    }
}
