<?php

namespace app\controllers;

class AdminController extends \app\controllers\RestController
{
    protected $logRequestAndResponse = false;

    public function actionDelete()
    {

    }

    public function actionLog()
    {
        $cursor = $this->logCollection->find()->sort(['time'=>-1])->limit(40);
        $logs = iterator_to_array($cursor,false);
        return json_encode($logs,JSON_PRETTY_PRINT);
    }

    public function actionUser() {
        $m = new \MongoClient();
        $userCollection = $m->selectCollection($this->mongoDbName,'user');
        $cursor = $userCollection->find()->sort(['sms_validation_request_timestamp'=>-1]);
        $users = iterator_to_array($cursor,false);
        return json_encode($users,JSON_PRETTY_PRINT);

    }

    public function actionPicture() {
        $m = new \MongoClient();
        $userCollection = $m->selectCollection($this->mongoDbName,'picture');
        $cursor = $userCollection->find()->sort(['createtime'=>-1]);
        $users = iterator_to_array($cursor,false);
        return json_encode($users,JSON_PRETTY_PRINT);

    }

    public function actionPreference() {
        $m = new \MongoClient();
        $preferenceCollection = $m->selectCollection($this->mongoDbName,'preference');
        $cursor = $preferenceCollection->find();
        $preferences = iterator_to_array($cursor,false);
        return json_encode($preferences,JSON_PRETTY_PRINT);

    }

    public function actionDeleteAllFriend() {

        $tel = isset($_GET['tel'])? $_GET['tel'] : "";
        $m = new \MongoClient();
        $userCollection = $m->selectCollection($this->mongoDbName,'user');

        $user = $userCollection->findOne(["tel" => $tel]);

        if($user == null ||
            !isset($user["friend_list"])) {
            $rlt = [
                "success" => false
            ];
            return json_encode($rlt);
        }

        $friend_list = $user["friend_list"];

        foreach($friend_list as $item) {
            $peer_tel = $item["peer_tel"];
            $newdata1 = ['$pull' => ["friend_list" => ["peer_tel" => $peer_tel]]];
            $newdata2 = ['$pull' => ["friend_list" => ["peer_tel" => $tel]]];
            $userCollection->update(["tel" => $tel], $newdata1);
            $userCollection->update(["tel" => $peer_tel], $newdata2);
        }

        $rlt = [
            "success"=>true,
            "count"=>count($friend_list),
            "friend_list" => $friend_list,
        ];

        return json_encode($rlt);
    }
    
    public function actionChatRecord() {
        $m = new \MongoClient();
        $chatRecordCollection = $m->selectCollection($this->mongoDbName,'chatrecord');
        $cursor = $chatRecordCollection->find()->sort(['timestamp'=>-1]);
        $chatRecords = iterator_to_array($cursor,false);
        return json_encode($chatRecords,JSON_PRETTY_PRINT);

    }

    public function actionUpdate()
    {

    }

    public function actionView()
    {

    }

    public function actionClearPfAnswer() {
        $tel = isset($_GET['tel'])? $_GET['tel'] : "";
        $m = new \MongoClient();
        $userCollection = $m->selectCollection($this->mongoDbName,'user');
	$user = $userCollection->findOne(["tel"=>$tel]);
	$pf_answer = [];
	if($user != null && isset($user["pf_answer"])) {
		$pf_answer = $user["pf_answer"];
		$userCollection->update(["tel" => $tel], ['$unset'=>["pf_answer"=>[]]]);
	}
	return json_encode($pf_answer);
    }
}
