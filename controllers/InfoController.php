<?php

namespace app\controllers;

use app\controllers\RestController;

class InfoController extends RestController
{
	private $mongoCollection;
	private $pfCollection;


	public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $m = new \MongoClient();
        $this->mongoCollection = $m->selectCollection('local','user');

        $mongoConn = new \MongoClient();
        $this->pfCollection = $mongoConn->selectCollection('local', 'preferences');

        return true;
    }


    public function actionBasicInfoUpload() {
    	$req_type = "basic_info_upload";
    	$rlt_type = "basic_info_upload_result";

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
            !isset($content["sex"]) ||
            !isset($content["birthdate"]) ||
            !$this->check_sex($content["sex"]) ||
            !$this->check_birthdate($content["birthdate"])
        ) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 2,
                "error_msg" => "input not valid.",
            ];
            return json_encode($rlt);
        }

        $user = $this->mongoCollection->findOne(['tel'=>$content["tel"]]);
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

        $newdata = ['$set' => ["sex" => $content["sex"], "birthdate" => $content["birthdate"]]];
        if(!$this->mongoCollection->update(["tel" => $content["tel"]], $newdata)) {
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
        ];

        return json_encode($rlt);
    }

    private function check_sex($sex)
    {
        if(!isset($sex)) {
            return false;
        }
	
	return $sex == 0 || $sex == 1;
    }

    private function check_birthdate($birthdate) 
    {
        if(!isset($birthdate) || 
            !isset($birthdate["month"]) ||
            !isset($birthdate["day"]) ||
            !isset($birthdate["year"])
        ) {
            return false;
        }

        return checkdate($birthdate["month"], $birthdate["day"], $birthdate["year"]);
    }    
}
