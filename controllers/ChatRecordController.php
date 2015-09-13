<?php


namespace app\controllers;

use Yii;

class ChatRecordController extends \app\controllers\RestController
{
    private $chatRecordCollection = null;
    private $userCollection = null;
    private $chatRecords = [];
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $m = new \MongoClient();
        $this->chatRecordCollection = $m->selectCollection($this->mongoDbName,'chatrecord');
        $this->userCollection = $m->selectCollection($this->mongoDbName,'user');
        return true;
    }

    public function actionIndex()
    {
    }

    public function actionGetChatRecordPerConversation (){
        $req_type = "get_chat_record_per_conversation";
        $rlt_type = "get_chat_record_per_conversation_result";

        $input = file_get_contents("php://input");
        $content = json_decode($input,true);
        if(json_last_error() != JSON_ERROR_NONE) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 1,
                "error_msg" => "json decode failed.",
            ];
            return json_encode($rlt);
        }

        if( 
            !isset($content["type"]) ||
            $content["type"] != $req_type ||
            !isset($content["tel"]) ||
            !isset($content["token"]) ||
            !isset($content["peer_huanxin_id"])
        ) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 2,
                "error_msg" => "input not valid.",
            ];
            return json_encode($rlt);
        }


        $user = $this->userCollection->findOne(['tel'=>$content["tel"]]);
        if($user==null) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 3,
                "error_msg" => "tel not found.",
            ];
            return json_encode($rlt);
        }

        if(
            !isset($user["token"]) ||
            $user["token"]!=$content["token"]
        ) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 4,
                "error_msg" => "token not valid.",
            ];
            return json_encode($rlt);
        }

        if(!isset($user["friend_list"])) {
            $rlt = [
                "type" => $rlt_type,
                "success" => true,
                "error_no" => 0,
                "error_msg" => "",
                "offset"=>0,
                "limit"=>0,
                "count"=>0,
                "chat_records" => null,
            ];
            return json_encode($rlt);
        }

        $this->sync();
            
        $self_huanxin_id = $user['huanxin_id'];
        $peer_huanxin_id = $content["peer_huanxin_id"];
        $q = [
            '$or' => [
                ['from'=>$self_huanxin_id,'to'=>$peer_huanxin_id],
                ['from'=>$peer_huanxin_id,'to'=>$self_huanxin_id],

            ],
        ];

       

        if(isset($content["offset"])) {
            $offset=(int) $content["offset"];
        } else {
            $offset=0;
        }

        if(isset($content["limit"])) {
            $limit = (int) $content["limit"];
        } else {
            $limit = 10;
        }

        try {
            $cursor = $this->chatRecordCollection->find($q)->sort(['timestamp'=>-1])->skip($offset)->limit($limit);
            $chat_records = iterator_to_array($cursor,false);  
        } catch (Exception $e) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 5,
                "error_msg" => "database error.",
            ];
            return json_encode($rlt);
        }

        $rlt = [
            "type" => $rlt_type,
            "success" => true,
            "error_no" => 0,
            "error_msg" => "",
            "offset"=>$offset,
            "limit"=>$limit,
            "count"=>count($chat_records),
            'chat_records'=>$chat_records,
        ];

        return json_encode($rlt);

    }

    public function actionGetChatRecord() {
        $req_type = "get_chat_record";
        $rlt_type = "get_chat_record_result";

        $input = file_get_contents("php://input");
        $content = json_decode($input,true);
        if(json_last_error() != JSON_ERROR_NONE) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 1,
                "error_msg" => "json decode failed.",
            ];
            return json_encode($rlt);
        }

        if( 
            !isset($content["type"]) ||
            $content["type"] != $req_type ||
            !isset($content["tel"]) ||
            !isset($content["token"])
        ) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 2,
                "error_msg" => "input not valid.",
            ];
            return json_encode($rlt);
        }

        $user = $this->userCollection->findOne(['tel'=>$content["tel"]]);
        if($user==null) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 3,
                "error_msg" => "tel not found.",
            ];
            return json_encode($rlt);
        }

        if(
            !isset($user["token"]) ||
            $user["token"]!=$content["token"]
        ) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 4,
                "error_msg" => "token not valid.",
            ];
            return json_encode($rlt);
        }

        if(!isset($user["friend_list"])) {
            $rlt = [
                "type" => $rlt_type,
                "success" => true,
                "error_no" => 0,
                "error_msg" => "",
                "chat_records" => null,
            ];
            return json_encode($rlt);
        }
        
        $this->sync();
        
        foreach ($user["friend_list"] as &$f) {
            # code...
            $self_huanxin_id = $user['huanxin_id'];
            $peer_huanxin_id = $f['huanxin_id'];
            $q = [
                '$or' => [
                    ['from'=>$self_huanxin_id,'to'=>$peer_huanxin_id],
                    ['from'=>$peer_huanxin_id,'to'=>$self_huanxin_id],

                ],
            ];

            $chat_records = iterator_to_array($this->chatRecordCollection->find($q),false);
            $f['chat_records'] = $chat_records;
            
        }

        $rlt = [
            "type" => $rlt_type,
            "success" => true,
            "error_no" => 0,
            "error_msg" => "",
            'chat_records'=>$user["friend_list"],
        ];

        return json_encode($rlt);
    }

    public function actionSync() {
        $this->sync();
        $rlt = [
            'success' => true,
            'count' => count($this->chatRecords),
            'chatRecords' => $this->chatRecords,
        ];
        return json_encode($rlt,JSON_PRETTY_PRINT);
    }

    private function sync() {
        for($cursor = $this->getChatAndStore('');$cursor!=null;$cursor = $this->getChatAndStore($cursor)) {
            //do nothing
        }
    }

    private function getChatAndStore($cursor,$limit=1000) {
        $latestRecord = $this->chatRecordCollection->find()->sort(['timestamp'=>-1])->limit(1)->getNext();
        if($latestRecord!=null) {
            $timestamp = $latestRecord['timestamp'];
            $ql = "select+*+where+timestamp>$timestamp+order+by+timestamp+asc";
        } else {
            $ql = "";
        }
        $huanxin_rlt =Yii::$app->easemobClient->chatRecord($ql,$cursor,$limit);
        if(isset($huanxin_rlt['error'])) { 
            $rlt = [
                'success' => false,
                'huanxin_rlt' => $huanxin_rlt, 
                'error_no'=> -1,
            ];
            return json_encode($rlt);
        }
        $entities = $huanxin_rlt['entities'];
        foreach ($entities as $e) {
            $this->chatRecords[] = $e;
            $this->chatRecordCollection->update(["uuid"=>$e['uuid']],$e,["upsert"=>true]);
        }
        return isset($huanxin_rlt['cursor']) ? $huanxin_rlt['cursor'] : null;
    }
}
