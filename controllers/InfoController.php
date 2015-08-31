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
        $this->pfCollection = $mongoConn->selectCollection('local', 'preference');

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


    public function actionPfPictureRequest()
    {
        $req_type = "pf_picture_request";
        $rlt_type = "pf_picture_result";

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

        if(!isset($content['type']) ||
            $content['type'] != $req_type ||
            !isset($content['tel']) ||
            !isset($content['token'])) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 2,
                "error_msg" => "input not valid.",
            ];

            return json_encode($rlt);
        }

        $user = $this->mongoCollection->findOne(['tel' => $content["tel"]]);
        if($user == NULL) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 3,
                "error_msg" => "tel not found.",
            ];
            return json_encode($rlt);
        }

        if(!isset($user["token"]) ||
            $user["token"] != $content["token"]) {

            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 4,
                "error_msg" => "token not valid.",
            ];
            return json_encode($rlt);
        }

        $pf_id = $this->get_next_pf_id($content["tel"]);
        if($pf_id == -1) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 5,
                "error_msg" => "no available pf picture.",
            ];
            return json_encode($rlt);
        }

        $pf = $this->pfCollection->findOne(["pf_id" => $pf_id]);
        if($pf == null ||
            !isset($pf["pic0"]) ||
            !isset($pf["pic0"]["path"]) ||
            !isset($pf["pic0"]["name_cn"]) ||
            !isset($pf["pic0"]["name_en"]) ||
            !isset($pf["pic1"]) ||
            !isset($pf["pic1"]["path"]) ||
            !isset($pf["pic1"]["name_cn"]) ||
            !isset($pf["pic1"]["name_en"])
        ) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 6,
                "error_msg" => "database error.",
            ];
            return json_encode($rlt);
        }

        $pic0_url = '../preference/' . $pf["pic0"]["path"];
        $pic1_url = '../preference/' . $pf["pic1"]["path"];

        $pic0_type = pathinfo($pic0_url, PATHINFO_EXTENSION);
        $pic1_type = pathninfo($pic1_url, PATHINFO_EXTENSION);
        $pic0_raw_data = file_get_contents($pic0_url);
        $pic1_raw_data = file_get_contents($pic1_url);
        if($pic0_type == "" ||
            $pic1_type == "" ||
            $pic0_raw_data == false || 
            $pic1_raw_data == false) 
        {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 7,
                "error_msg" => "error in reading image files or unknown image type.",
            ];
            return json_encode($rlt);
        }

        $pic0_enc_data = base64_encode($pic0_raw_data);
        $pic1_enc_data = base64_encode($pic1_raw_data);
        if($pic0_enc_data == false || $pic1_enc_data == false) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 8,
                "error_msg" => "base64 encryption error",
            ];
            return json_encode($rlt);
        }

        $rlt = [
            "type" => $rlt_type,
            "success" => true,
            "error_no" => 0,
            "error_msg" => "",
            "pf" => [
                "pf_id" => $pf_id,
                "pic0" => [
                    "type" => $pic0_type,
                    "name_en" => $pf["pic0"]["name_en"],
                    "name_cn" => $pf["pic0"]["name_cn"],
                    "data" => $pic0_enc_data,
                ],
                "pic1" => [
                    "type" => $pic1_type,
                    "name_en" => $pf["pic1"]["name_en"],
                    "name_cn" => $pf["pic1"]["name_cn"],
                    "data" => $pic1_enc_data,
                ],
            ],
        ];

        return json_encode($rlt);
    }

    public function actionPfAnswerUpload()
    {
        $req_type = "pf_answer_upload";
        $rlt_type = "pf_answer_upload_result";

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

        if(!isset($content["type"]) ||
            $content["type"] != $req_type ||
            !isset($content["tel"]) ||
            !isset($content["token"]) ||
            !isset($content["count"]) ||
            !isset($content["pf_answer"]) ||
	    !$this->check_pf_answer($content["count"], $content["pf_answer"]) 
        ) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 2,
                "error_msg" => "input not valid.",
            ];

            return json_encode($rlt);
        }

        $user = $this->mongoCollection->findOne(["tel" => $content["tel"]]);
         if($user == NULL) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 3,
                "error_msg" => "tel not found.",
            ];
            return json_encode($rlt);
        }

        if(!isset($user["token"]) ||
            $user["token"] != $content["token"]) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 4,
                "error_msg" => "token not valid.",
            ];
            return json_encode($rlt);
        }

        $pf_answer = $content["pf_answer"];
        $pf_answer_in_db = [];
        if(isset($user["pf_answer"])) {
            $pf_answer_in_db = $user["pf_answer"];
        }

        $pf_answer_to_push = $this->find_pf_answer_to_push($pf_answer, $pf_answer_in_db);
        //db update for operator set
        foreach($pf_answer as $item) {
            if(!in_array($item, $pf_answer_to_push)) {
                $criteria = ["tel" => $content["tel"], 'pf_answer.pf_id' => $item["pf_id"]];
                $newdata = ['$set' => ['pf_answer.$.choice' => $item["choice"]]];
                if(!$this->mongoCollection->update($criteria, $newdata)) {
                    $rlt = [
                        "type" => $rlt_type,
                        "success" => false,
                        "error_no" => 5,
                        "error_msg" => "database error",
                    ];
                    return json_encode($rlt);
                }
            }
        }

        //db update for operator pushAll
        if(count($pf_answer_to_push) > 0) {
            $newdata = ['$pushAll' => ["pf_answer" => $pf_answer_to_push]];
            if(!$this->mongoCollection->update([ "tel" => $content["tel"]], $newdata)) {
                $rlt = [
                    "type" => $rlt_type,
                    "success" => false,
                    "error_no" => 5,
                    "error_msg" => "database error",
                ];
                return json_encode($rlt);
            }
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
	   return $sex == 0 || $sex == 1;
    }

    private function check_birthdate($birthdate) 
    {
        if(!is_array($birthdate) || 
            !isset($birthdate["month"]) ||
            !isset($birthdate["day"]) ||
            !isset($birthdate["year"])
        ) {
            return false;
        }

        return checkdate($birthdate["month"], $birthdate["day"], $birthdate["year"]);
    }

    private function check_pf_answer($count, $pf_answer)
    {
        if(!is_array($pf_answer) ||
            $count != count($pf_answer)
        ) {
            return false;
        }

        $pf_ids = $this->get_all_pf_id();
	    $id_in_pf_answer = [];

        foreach($pf_answer as $item) {
            if(!is_array($item) ||
                !isset($item["pf_id"]) ||
                !isset($item["choice"])
            ) {
                return false;
            }

            if($item["choice"] != 0 && $item["choice"] != 1) {
                return false;
            } 

            if(!in_array($item["pf_id"], $pf_ids)) {
                return false;
            }
	   //check for repeated pf_id in pf_answer. 
	    if(in_array($item["pf_id"], $id_in_pf_answer)) {
		    return false;
	    } else {
		    $id_in_pf_answer[] = $item["pf_id"];
	    }
        }
        return true;
    }

    private function get_all_pf_id()
    {
        $cursor = $this->pfCollection->find([], ["pf_id" => true]);
        $pf_ids = [];
        foreach($cursor as $doc) {
            if(!in_array($doc["pf_id"], $pf_ids)) {
                $pf_ids[] = $doc["pf_id"];
            }
        }
        return $pf_ids;
    }

    private function find_pf_answer_to_push($pf_answer, $pf_answer_in_db) {
        $pf_answer_to_push = [];
        foreach($pf_answer as $item1) {
            $tag = false;
            foreach($pf_answer_in_db as $item2) {
                if($item2["pf_id"] == $item1["pf_id"]) {
                    $tag = true;
                    break;
                }
            }

            if(!$tag) {
                $pf_answer_to_push[] = $item1;
            }
        }
        return $pf_answer_to_push;
    }


    private function get_next_pf_id($tel)
    {
        $pf_ids = $this->get_all_pf_id();

        $user = $this->mongoCollection->findOne(["tel" => $tel]);
        if($user == null) {
            return -1;
        }

        $pf_ids_sent = [];

        if(isset($user["pf_sent"]) {
            $pf_sent = $user["pf_sent"];

            foreach($pf_sent as $item) {
                if(isset($item["pf_id"]) && !in_array($item["pf_id"], $pf_ids_sent)) {
                    $pf_ids_sent[] = $item["pf_id"];
                }
            }
        }

        $pf_id_available = array_diff($pf_ids, $pf_ids_sent);
        $pf_id_available = array_values($pf_id_available);

        $len = count($pf_id_available);

        if($len > 0) {
            $index = rand(0, $len -1);

            return $pf_id_available[$index];
        }

        $pf_ids_answered = [];
        if(isset($user["pf_answer"])) {
            $pf_answer = $user["pf_answer"];

            foreach($pf_answer as $item) {
                if(isset($item["pf_id"]) && !in_array($item["pf_id"], $pf_ids_answered)) {
                    $pf_ids_answered[] = $item["pf_id"];
                }
            }
        }

        $pf_id_available = array_diff($pf_ids, $pf_ids_answered);
        $pf_id_available = array_values($pf_id_available);

        $len = count($pf_id_available);
        if($len > 0) {
            $index = rand(0, $len - 1);
            return $pf_id_available[$index];
        }

        return -1;
    }

}
