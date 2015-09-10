<?php

namespace app\controllers;

use app\controllers\RestController;

class PreferenceController extends RestController
{
	private $mongoCollection = null;
    private $pfCollection = null;

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $m = new \MongoClient();
        $this->mongoCollection = $m->selectCollection($this->mongoDbName,'user');

        $mongoConn = new \MongoClient();
        $this->pfCollection = $mongoConn->selectCollection($this->mongoDbName, 'preferences');

        return true;
    }


    public function actionRequest()
    {
    	$input = file_get_contents("php://input");
        $content = json_decode($input,true);
        if(json_last_error() != JSON_ERROR_NONE) {
            $rlt = [
                "type" => "pf_question_response",
                "success" => false,
                "error_no" => 1,
                "error_msg" => "json decode failed.",
                "question" => NULL
            ];
            return json_encode($rlt);
        }

        if(!isset($content['type']) ||
        	$content['type'] != 'pf_question_request' ||
        	!isset($content['tel']) ||
        	!isset($content['token'])) {
        	$rlt = [
        		"type" => "pf_question_response",
        		"success" => false,
        		"error_no" => 2,
        		"error_msg" => "input not valid.",
        		"question" => NULL
        	];

        	return json_encode($rlt);
        }

        $user = $this->mongoCollection->findOne(['tel' => $content["tel"]]);
        if($user == NULL) {
        	$rlt = [
        		"type" => "pf_question_response",
        		"success" => false,
        		"error_no" => 3,
        		"error_msg" => "tel not found.",
        		"question" => NULL
        	];
        	return json_encode($rlt);
        }

        if(!isset($user["token"]) ||
        	$user["token"] != $content["token"]) {

         	$rlt = [
          		"type" => "pf_question_response",
          		"success" => false,
          		"error_no" => 4,
          		"error_msg" => "token not valid.",
          		"question" => NULL
         	];
         	return json_encode($rlt);
        }

        //
        $answers = [];
        if(isset($user["pf_answers"])) {
            $answers = $user["pf_answers"];
        }

        $uploaded_pf_ids = [];
        foreach($answers as $answer) {
            $uploaded_pf_ids[] = $answer["pf_id"];
        }

        $pf_id = $this->get_next_pf_id($uploaded_pf_ids);
        if($pf_id == -1) {
            $rlt = [
                "type" => "pf_question_response",
                "success" => false,
                "error_no" => 5,
                "error_msg" => "no available pf questions.",
                "question" => NULL
            ];
            return json_encode($rlt);
        }

        $pf = $this->pfCollection->findOne(["pf_id" => $pf_id]);
        if($pf == NULL ||
            !isset($pf["pic0"]) ||
            !isset($pf["pic1"]) ||
            !isset($pf["description"])) {
            $rlt = [
                "type" => "pf_question_response",
                "success" => false,
                "error_no" => 6,
                "error_msg" => "database error.",
                "question" => NULL
            ];
            return json_encode($rlt);
        }
		
		$description = file_get_contents('../preferences/' . $pf["description"]);
        $pic0 = file_get_contents('../preferences/' . $pf["pic0"]);
        $pic1 = file_get_contents('../preferences/' . $pf["pic1"]);
        if($pic0 == false || $pic1 == false || $description == false) {
            $rlt = [
                "type" => "pf_question_response",
                "success" => false,
                "error_no" => 7,
                "error_msg" => "error in reading files",
                "question" => NULL
            ];
            return json_encode($rlt);
        }

        $pic0_enc = base64_encode($pic0);
		$pic1_enc = base64_encode($pic1);
        if($pic0_enc == false || $pic1_enc == false) {
            $rlt = [
                "type" => "pf_question_response",
                "success" => false,
                "error_no" => 8,
                "error_msg" => "base64 encryption error",
                "question" => NULL
            ];
            return json_encode($rlt);
		}

		$description = trim($description);
        $rlt = [
            "type" => "pf_question_response",
            "success" => true,
            "error_no" => 0,
            "error_msg" => "",
            "question" => [
                "pf_id" => $pf_id,
                "pic0_enc" => $pic0_enc,
                "pic1_enc" => $pic1_enc,
                "description" => $description
            ]
        ];

        return json_encode($rlt);
    }

    private function get_max_pf_id()
    {
        //TODO: add more resonable logic.
        return 3;
    }
    //return a random pf_id.
    private function get_next_pf_id($uploaded_pf_ids = [])
    {
        $max_pf_id = $this->get_max_pf_id();
        $pf_ids = [];
        for($i = 0; $i < $max_pf_id; $i++) {
            $pf_ids[] = $i;
        }
        foreach($uploaded_pf_ids as $item) {
            unset($pf_ids[$item]);
        }
        $available_pf_ids = array_values($pf_ids);
        $length = count($available_pf_ids);
        if($length == 0) {
            return -1; //no pf id available.
        } else {
            $index = rand(0, $length - 1);
            return $available_pf_ids[$index];
        }
    }

    public function actionUpload()
    {
        $input = file_get_contents("php://input");
        $content = json_decode($input,true);
        if(json_last_error() != JSON_ERROR_NONE) {
            $rlt = [
                "type" => "pf_answer_confirm",
                "success" => false,
                "error_no" => 1,
                "error_msg" => "json decode failed.",
            ];
            return json_encode($rlt);
        }

        if(!isset($content["type"]) ||
            $content["type"] != "pf_answer_upload" ||
            !isset($content["tel"]) ||
            !isset($content["token"]) ||
            !isset($content["answer"]) ||
            !isset($content["answer"]["pf_id"]) ||
            !isset($content["answer"]["choice"])) {
            $rlt = [
                "type" => "pf_answer_confirm",
                "success" => false,
                "error_no" => 2,
                "error_msg" => "input not valid.",
            ];

            return json_encode($rlt);
        }

        $user = $this->mongoCollection->findOne(["tel" => $content["tel"]]);
         if($user == NULL) {
            $rlt = [
                "type" => "pf_answer_confirm",
                "success" => false,
                "error_no" => 3,
                "error_msg" => "tel not found.",
            ];
            return json_encode($rlt);
        }

        if(!isset($user["token"]) ||
            $user["token"] != $content["token"]) {
            $rlt = [
                "type" => "pf_answer_confirm",
                "success" => false,
                "error_no" => 4,
                "error_msg" => "token not valid.",
            ];
            return json_encode($rlt);
        }

        $answer = $content["answer"];
        //check the validation of the answer.
        if( ( $answer["pf_id"] < 0 || $answer["pf_id"] >= $this->get_max_pf_id() ) 
        	|| !($answer["choice"] == 0 || $answer["choice"] == 1)) {
            $rlt = [
                "type" => "pf_answer_confirm",
                "success" => false,
                "error_no" => 5,
                "error_msg" => "answer not valid",
            ];
            return json_encode($rlt);
        }

        $newdata = ['$push' => ["pf_answers" => $answer]];
        if(!$this->mongoCollection->update([ "tel" => $content["tel"]], $newdata)) {
            $rlt = [
                "type" => "pf_answer_confirm",
                "success" => false,
                "error_no" => 6,
                "error_msg" => "database error",
            ];
            return json_encode($rlt);
        }

        $rlt = [
            "type" => "pf_answer_confirm",
            "success" => true,
            "error_no" => 0,
            "error_msg" => "",
            ];
        return json_encode($rlt);
    }
}
