<?php

namespace app\controllers;

class UserController extends \app\controllers\RestController
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

    public function actionIndex()
    {
    }

    public function actionSmsValidationCode()
    {
        $input = file_get_contents("php://input");
        $content = json_decode($input,true);
        if(json_last_error()!=JSON_ERROR_NONE) {
            $rlt = [
                "type" => "sms_validation_result",
                "success" => false,
                "error_no" => 1,
                "error_msg" => "json decode failed.",
            ];
            echo json_encode($rlt);
            return;
        }


        if( 
            !isset($content["type"]) ||
            $content["type"]!="sms_validation_code" ||
            !isset($content["code"]) ||
            !isset($content["tel"])
        ) {
            $rlt = [
                "type" => "sms_validation_result",
                "success" => false,
                "error_no" => 2,
                "error_msg" => "input not valid.",
            ];
            echo json_encode($rlt);
            return ;
        }

        if(!$this->validateValidationCode($content["tel"],$content["code"])){

            $rlt = [
                "type" => "sms_validation_result",
                "success" => false,
                "error_no" => 3,
                "error_msg" => "validation code invalid.",
            ];
            echo json_encode($rlt);
            return ;
        }

        $token = $this->generateToken($content["tel"]);
        $user = $this->mongoCollection->findOne(['tel'=>$content["tel"]]);
        $newdata = ['$set'=>['token'=>$token,"verified"=>true]];
        $this->mongoCollection->update(["tel"=>$content["tel"]],$newdata,["upsert"=>true]);
        $rlt = [
            "type" => "sms_validation_result",
            "success" => true,
            "token" => $token,
            "error_no" => 0,
            "error_msg" => null,   
        ];
        echo json_encode($rlt);
        return ;
    }


    public function actionRegister() {
        $input = file_get_contents("php://input");
        $content = json_decode($input,true);
        if(json_last_error()!=JSON_ERROR_NONE) {
            $rlt = [
                "type" => "register",
                "success" => false,
                "error_no" => 1,
                "error_msg" => "json decode failed.",
            ];
            echo json_encode($rlt);
            return;
        }

        if(
            !isset($content["type"]) ||
            $content["type"]!="register" ||
            !isset($content["username"]) ||
            !isset($content["password"]) ||
            !isset($content["token"]) ||
            !isset($content["tel"])
        ) {
            $rlt = [
                "type" => "register",
                "success" => false,
                "error_no" => 2,
                "error_msg" => "input not valid.",
            ];
            echo json_encode($rlt);
            return ;       
        }

        $user = $this->mongoCollection->findOne(['tel'=>$content["tel"]]);

        if($user==null) {
            $rlt = [
                "type" => "register",
                "success" => false,
                "error_no" => 3,
                "error_msg" => "tel not found.",
            ];
            echo json_encode($rlt);
            return ;
        }

        if(
            !isset($user["token"]) ||
            $user["token"]!=$content["token"]
        ) {
            $rlt = [
                "type" => "register",
                "success" => false,
                "error_no" => 4,
                "error_msg" => "token not valid.",
            ];
            echo json_encode($rlt);
            return ;
        }
        $new_token = $this->generateToken($content['tel'].$content['username']);
        $newdata = [
            '$set'=>[
                'username'=>$content['username'],
                'password'=>password_hash($content['password'], PASSWORD_DEFAULT),
                'token'=>$new_token,
            ]
        ];
        if(!$this->mongoCollection->update(["tel"=>$content["tel"]],$newdata)) {
            $rlt = [
                "type" => "register",
                "success" => false,
                "error_no" => 5,
                "error_msg" => "database error.",
            ];
            echo json_encode($rlt);
            return ;
        }

        $rlt = [
            "type" => "register",
            "token" => $new_token,
            "success" => true,
            "error_no" => 0,
            "error_msg" => null,
        ];
        echo json_encode($rlt);
        return ;

    }

    public function actionLogin() {
        $input = file_get_contents("php://input");
        $content = json_decode($input,true);
        if(json_last_error()!=JSON_ERROR_NONE) {
            $rlt = [
                "type" => "login",
                "success" => false,
                "error_no" => 1,
                "error_msg" => "json decode failed.",
            ];
            echo json_encode($rlt);
            return;
        }

        if(
            !isset($content["type"]) ||
            $content["type"]!="login" ||
            !isset($content["password"]) ||
            !isset($content["tel"])
        ) {
            $rlt = [
                "type" => "login",
                "success" => false,
                "error_no" => 2,
                "error_msg" => "input not valid.",
            ];
            echo json_encode($rlt);
            return ;       
        }

        $user = $this->mongoCollection->findOne(['tel'=>$content["tel"]]);

        if($user==null) {
            $rlt = [
                "type" => "login",
                "success" => false,
                "error_no" => 3,
                "error_msg" => "tel not found.",
            ];
            echo json_encode($rlt);
            return ;
        }

        if(
            !isset($user["password"]) ||
            !password_verify($content["password"],$user["password"])
        ) {
            $rlt = [
                "type" => "login",
                "success" => false,
                "error_no" => 5,
                "error_msg" => "password not valid.",
            ];
            echo json_encode($rlt);
            return ;
        }
        $new_token = $this->generateToken($content['tel'].$content['username']);
        $newdata = [
            '$set'=>[
                'token' => $new_token,
            ]
        ];
        if(!$this->mongoCollection->update(["tel"=>$content["tel"]],$newdata)) {
            $rlt = [
                "type" => "login",
                "success" => false,
                "error_no" => 6,
                "error_msg" => "database error.",
            ];
            echo json_encode($rlt);
            return ;
        }

        $rlt = [
            "type" => "login",
            "token" => $this->generateToken($content['tel'].$content['username']),
            "success" => true,
            "error_no" => 0,
            "error_msg" => null,
        ];
        echo json_encode($rlt);
        return ;
    }

    public function actionSmsValidationRequest()
    {
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
        $user = $this->mongoCollection->findOne(['tel'=>$tel]);
        if(!isset($user["sms_validation_request_timestamp"])) {
            $timediff = 9999999;
        } else {
            $timediff = time() - $user["sms_validation_request_timestamp"];
        }
        if($timediff<60) {
            return false;
        }
        // call 3rd party sms service
        $validation_code = '123456';
        $newdata = ['$set'=>['validation_code'=>'123456','sms_validation_request_timestamp'=>time()]];
        $this->mongoCollection->update(["tel"=>$tel],$newdata,["upsert"=>true]);
        return true;
    }

    private function validateValidationCode($tel,$code) {
        $user = $this->mongoCollection->findOne(['tel'=>$tel]);
        if(!isset($user["validation_code"])) {
            return false;
        }

        return $user["validation_code"] == $code;
    }

}
