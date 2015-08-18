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

        $cursor = $this->logCollection->find();
        $logs = iterator_to_array($cursor,false);
        return json_encode($logs,JSON_PRETTY_PRINT);
    }

    public function actionUser() {
        $m = new \MongoClient();
        $userCollection = $m->selectCollection('local','user');
        $cursor = $userCollection->find();
        $users = iterator_to_array($cursor,false);
        return json_encode($users,JSON_PRETTY_PRINT);

    }

    public function actionUpdate()
    {

    }

    public function actionView()
    {

    }

}
