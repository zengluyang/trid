<?php


namespace app\controllers;

use Yii;

class ChatRecordController extends \app\controllers\RestController
{
    private $mongoCollection = null;
    private $chatRecords = [];
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $m = new \MongoClient();
        $this->mongoCollection = $m->selectCollection('local','chatrecord');       
        return true;
    }

    public function actionIndex()
    {
    }

    public function actionSync() {
        for($cursor = $this->getChatAndStore('');$cursor!=null;$cursor = $this->getChatAndStore($cursor)) {
            //do nothing
        }
        $rlt = [
            'success' => true,
            'count' => count($this->chatRecords),
            'chatRecords' => $this->chatRecords,
        ];
        return json_encode($rlt,JSON_PRETTY_PRINT);
    }

    private function getChatAndStore($cursor,$limit=1000) {
        $latestRecord = $this->mongoCollection->find()->sort(['timestamp'=>-1])->limit(1)->getNext();
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
            $this->mongoCollection->update(["uuid"=>$e['uuid']],$e,["upsert"=>true]);
        }
        return isset($huanxin_rlt['cursor']) ? $huanxin_rlt['cursor'] : null;
    }
}
