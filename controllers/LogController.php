<?php

namespace app\controllers;

class LogController extends \app\controllers\RestController
{
    protected $logRequestAndResponse = false;

    public function actionDelete()
    {

    }

    public function actionIndex()
    {

        $cursor = $this->logCollection->find();
        $logs = iterator_to_array($cursor,false);
        return json_encode($logs);
    }

    public function actionUpdate()
    {

    }

    public function actionView()
    {

    }

}
