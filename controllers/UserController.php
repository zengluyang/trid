<?php


namespace app\controllers;

use Yii;

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


    //search user based on some attrs
    public function actionSearch() {
        $input = file_get_contents("php://input");
        $content = json_decode($input,true);
        $type = 'search_result';
        if(json_last_error()!=JSON_ERROR_NONE) {
            $rlt = [
                "type" =>  $type ,
                "success" => false,
                "error_no" => 1,
                "error_msg" => "json decode failed.",
            ];
            return json_encode($rlt);
        }

        if( 
            !isset($content["type"]) ||
            $content["type"]!="search" ||
            !isset($content["token"]) ||
            !isset($content["tel"]) ||
            !isset($content["search"])
        ) {
            $rlt = [
                "type" => $type,
                "success" => false,
                "error_no" => 2,
                "error_msg" => "input not valid.",
            ];
            return json_encode($rlt);
        }

        $user = $this->mongoCollection->findOne(['tel'=>$content["tel"]]);

        if($user==null) {
            $rlt = [
                "type" => $type,
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
                "type" => $type,
                "success" => false,
                "error_no" => 4,
                "error_msg" => "token not valid.",
            ];
            return json_encode($rlt);
        }
        $query = [];
        if(isset($content['search']['tel'])) {
            $query['tel']=$content['search']['tel'];
        }
        if(isset($content['search']['username'])) {
            $query['username']=$content['search']['username'];
        }
        if(isset($content['search']['_id'])) {
            try {
                $query['_id'] = new \MongoId($content['search']['_id']);
            } catch (\MongoException $ex) {
                $query['_id'] = null;
            }
        }
        $cursor = $this->mongoCollection->find($query,['username'=>1,'tel'=>1,'huanxin_id'=>1]);
        $count = $cursor->count();
        if($count==0) {
            $rlt = [
                "type" => $type,
                "success" => false,
                "error_no" => 5,
                "error_msg" => "user not found.",
            ];
            return json_encode($rlt);
        }
        
        $limit = $count;
        $search_rlt = iterator_to_array($cursor,false);

        $rlt = [
            "type" => $type,
            "success" => true,
            "error_no" => 0,
            "error_msg" => null,
            "count" => $count,
            "offset" => 0,
            "limit" => $limit,
            'users' => $search_rlt,
        ];
        return json_encode($rlt);

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
            return json_encode($rlt);
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
            return json_encode($rlt);
        }

        if(!$this->validateValidationCode($content["tel"],$content["code"])){

            $rlt = [
                "type" => "sms_validation_result",
                "success" => false,
                "error_no" => 3,
                "error_msg" => "validation code invalid.",
            ];
            return json_encode($rlt);
        }

        $token = $this->generateToken($content["tel"]);
        $user = $this->mongoCollection->findOne(['tel'=>$content["tel"]]);
        $basic_info_required = false;
        if(!isset($user['huanxin_id'])) {
            //first time login
            $basic_info_required = true;
            $huanxin_id = $content['tel'];
            $huanxin_pwd = $this->generateToken($content['tel'],$length=32);
            $huanxin_rlt = Yii::$app->easemobClient->accreditRegister(['username'=>$huanxin_id,'password'=>$huanxin_pwd]);
            $newdata = [
                '$set'=>[
                    'token'=>$token,
                    'huanxin_id' => $huanxin_id,
                    'huanxin_password' => $huanxin_pwd,
                    "verified"=>true,
                ],
                '$unset'=>[
                    'validation_code'=>1,
                ],
            ];
            if(
                !isset($huanxin_rlt) ||
                empty($huanxin_rlt) ||
                (
                    isset($huanxin_rlt['error']) && !empty($huanxin_rlt['error'])
                )
            ) {
                $rlt = [
                    "type" => "sms_validation_result",
                    "success" => false,
                    "error_no" => 4,
                    "error_msg" => "huanxin error.",
                    "huanxin_response" => $huanxin_rlt,
                ];
                return json_encode($rlt);
            }
        } else {
            if(!isset($user["sex"]) || !isset($user["birthdate"])) {
                $basic_info_required = true;
            }
            $newdata = [
                '$set'=>[
                    'token'=>$token,
                    "verified"=>true,
                ],
                '$unset'=>[
                    'validation_code'=>1,
                ],
            ];
            $huanxin_id = $user['huanxin_id'];
            $huanxin_pwd = $user['huanxin_password'];
        }

        if(!$this->mongoCollection->update(["tel"=>$content["tel"]],$newdata)) {
            $rlt = [
                "type" => "sms_validation_result",
                "success" => false,
                "error_no" => 5,
                "error_msg" => "database error.",
            ];
            return json_encode($rlt);
        }
        $rlt = [
            "type" => "sms_validation_result",
            "success" => true,
            "token" => $token,
            "huanxin_id"=>$huanxin_id,
            "huanxin_pwd"=>$huanxin_pwd,
            "error_no" => 0,
            "error_msg" => null,
            "basic_info_required" => $basic_info_required,
        ];
        return json_encode($rlt);
    }


    //get self profile
    public function actionProfile() {
        $input = file_get_contents("php://input");
        $content = json_decode($input,true);
        $type = 'profile_result';
        if(json_last_error()!=JSON_ERROR_NONE) {
            $rlt = [
                "type" =>  $type ,
                "success" => false,
                "error_no" => 1,
                "error_msg" => "json decode failed.",
            ];
            return json_encode($rlt);
        }

        if( 
            !isset($content["type"]) ||
            $content["type"]!="profile" ||
            !isset($content["token"]) ||
            !isset($content["tel"])
        ) {
            $rlt = [
                "type" => $type,
                "success" => false,
                "error_no" => 2,
                "error_msg" => "input not valid.",
            ];
            return json_encode($rlt);
        }

        $user = $this->mongoCollection->findOne(['tel'=>$content["tel"]]);

        if($user==null) {
            $rlt = [
                "type" => $type,
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
                "type" => $type,
                "success" => false,
                "error_no" => 4,
                "error_msg" => "token not valid.",
            ];
            return json_encode($rlt);
        }

            
        $rlt = [
            "success" => true,
            "error_no" => 0,
            "error_msg" => null,
            "profile" => [
                "_id"=>$user["_id"],
                "username"=> isset($user["username"]) ? isset($user["username"]) : null,
                "huanxin_id"=>$user["huanxin_id"],
                "huanxin_password "=> $user["huanxin_password"],
                "pf_answers" => isset($user["pf_answers"]) ? $user["pf_answers"] : null,
            ],
        ];
        return json_encode($rlt);


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
            return json_encode($rlt);
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
            return json_encode($rlt);
        }
        if(!$this->saveSmsNotValidateUser($content["tel"])) {

            $rlt = [
                "type" => "sms_validation_send",
                "success" => false,
                "error_no" => 3,
                "error_msg" => "database error.",
            ];
            return json_encode($rlt);
        }
        if(!$this->sendSmsToTel($content['tel'])) {

            $rlt = [
                "type" => "sms_validation_send",
                "success" => false,
                "error_no" => 4,
                "error_msg" => "3rd party sms send failed.",
            ];
            return json_encode($rlt);
        }

        $rlt = [
            "type" => "sms_validation_send",
            "success" => true,
            "error_no" => 0,
            "error_msg" => null,
        ];

        
        return json_encode($rlt);

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
        $validation_code = '123456';
        //validation_code = $this->generateValidationCode();
        //$result = Yii::$app->yuntongxunSmsClient->sendTemplateSMS($tel ,array($validation_code,'5'),"1");
        $newdata = ['$set'=>['validation_code'=>$validation_code,'sms_validation_request_timestamp'=>time()]];
        $this->mongoCollection->update(["tel"=>$tel],$newdata,["upsert"=>true]);
        return true;
    }

    private function validateValidationCode($tel,$code) {
        $user = $this->mongoCollection->findOne(['tel'=>$tel]);
        if($user==null) {
            return false;
        }
        if(!isset($user["validation_code"])) {
            return false;
        }

        return $user["validation_code"] == $code;
    }

    protected function generateValidationCode($length=6) {
        $characters = '0123456789';
        $charactersLength = strlen($characters)-1;
        $token = '';
        //select some random characters
        for ($i = 0; $i < $length; $i++) {
            $token .= $characters[mt_rand(0, $charactersLength)];
        }        

        return $token;
    }

}
