<?php

namespace app\controllers;

class UserController extends \yii\web\Controller
{
    private $mongoCollection = null;

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $m = new \MongoClient();
        $this->mongoCollection = $m->selectCollection('local','user');       
        return true;
    }

    private function header() 
    {
        header("Content-Type:application/json;charset=UTF-8");
    }

    public function actionIndex()
    {
        
    }

    public function actionSmsValidationCode()
    {

    }

    public function actionSmsValidationRequest()
    {
        $this->header();
        $input = file_get_contents("php://input");
        $content = json_decode($input,true);
        if(json_last_error()!=JSON_ERROR_NONE) {
            $rlt = [
                "type" => "sms_validation_send",
                "success" => false,
                "error_no" => 1,
                "error_msg" => "json decode failed.",
            ];
            echo json_encode($rlt);
            return;
        }

        if( 
            !isset($content["type"]) ||
            $content["type"]!="sms_validation_request" ||
            !isset($content["tel"]) ||
            !isset($content["time"])
        ) {
            $rlt = [
                "type" => "sms_validation_send",
                "success" => false,
                "error_no" => 2,
                "error_msg" => "input not valid.",
            ];
            echo json_encode($rlt);
            return ;
        }
        if(!$this->saveSmsNotValidateUser($content["tel"])) {

            $rlt = [
                "type" => "sms_validation_send",
                "success" => false,
                "error_no" => 3,
                "error_msg" => "database error.",
            ];
            echo json_encode($rlt);
            return ;
        }
        if(!$this->sendSmsToTel($content['tel'])) {

            $rlt = [
                "type" => "sms_validation_send",
                "success" => false,
                "error_no" => 4,
                "error_msg" => "3rd party sms send failed.",
            ];
            echo json_encode($rlt);
            return ;
        }

        $rlt = [
            "type" => "sms_validation_send",
            "success" => true,
            "error_no" => 0,
            "error_msg" => null,
        ];

        
        echo json_encode($rlt);

    }

    private function saveSmsNotValidateUser($tel) {
        $newdata = array('$set' => array("verified" => false));
        $this->mongoCollection->update(["tel"=>$tel],$newdata,["upsert"=>true]);
        return true;
    }

    private function sendSmsToTel($tel) {
        return true;
    }


}
