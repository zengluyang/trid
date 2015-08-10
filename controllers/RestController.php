<?php

namespace app\controllers;

class RestController extends \yii\web\Controller
{
	private $mongoCollection = null;

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        $this->header();
        return true;
    }

    private function header() 
    {
        header("Content-Type:application/json;charset=UTF-8");
    }
}
