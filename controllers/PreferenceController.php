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
        $this->mongoCollection = $m->selectCollection('local','user');

        $mongoConn = new \MongoClient();
        $this->pfCollection = $mongoConn->selectCollection('local', 'preferences');

        return true;
    }

    public function actionIndex()
    {
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
            echo json_encode($rlt);
            return;
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

        	echo json_encode($rlt);
        	return;
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
        	echo json_encode($rlt);
        	return;
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
         	echo json_encode($rlt);
         	return;
        }

        $answers = [];
        if(isset($user["pf_collected"])) {
            $answers = $user["pf_collected"];
        }

        $collected = [];
        foreach($answers as $answer) {
            $collected[] = $answer["id"];
        }

        $pf_id = $this->get_next_pf_ID($collected);
        if($pf_id == -1) {
            $rlt = [
                "type" => "pf_question_response",
                "success" => false,
                "error_no" => 5,
                "error_msg" => "no available pf questions.",
                "question" => NULL
            ];
            echo json_encode($rlt);
            return;
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
            echo json_encode($rlt);
            return;
        }

        $pic0 = file_get_contents('../preferences/' . $pf["pic0"]);
        $pic1 = file_get_contents('../preferences/' . $pf["pic1"]);
        if($pic0 == false || $pic1 == false) {
            $rlt = [
                "type" => "pf_question_response",
                "success" => false,
                "error_no" => 7,
                "error_msg" => "can not read the pictures.",
                "question" => NULL
            ];
            echo json_encode($rlt);
        }

        $pic0_enc = base64_encode($pic0);
        $pic1_enc = base64_encode($pic1);
        if($pic0_enc == false || $pic1_enc == false) {
            $rlt = [
                "type" => "pf_question_response",
                "success" => false,
                "error_no" => 8,
                "error_msg" => "base64 encode error",
                "question" => NULL
            ];
            echo json_encode($rlt);
        }

        $rlt = [
            "type" => "pf_question_response",
            "success" => true,
            "error_no" => 0,
            "error_msg" => "",
            "question" => [
                "id" => $pf_id,
                "pic0_enc" => $pic0_enc,
                "pic1_enc" => $pic1_enc,
                "description" => $pf["description"] 
            ]
        ];

        echo json_encode($rlt);
        return;
    }

    private function get_max_pf_ID()
    {
        //TODO: add more resonable logic.
        return 100;
    }
    //return a random pf_id.
    private function get_next_pf_ID($collected = [])
    {
        $max_pf_ID = $this->get_max_pf_ID();
        $IDs = [];
        for($i = 0; $i < $max_pf_ID; $i++) {
            $IDs[] = $i;
        }
        foreach($collected as $item) {
            unset($IDs[$item]);
        }
        $available_IDs = array_values($IDs);
        $length = count($available_IDs);
        if($length == 0) {
            return -1; //no pf id available.
        } else {
            $index = rand(0, $length - 1);
            return $available_IDs[$index];
        }
    }

    public function actionCollect()
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
            echo json_encode($rlt);
            return;
        }

        if(!isset($content["type"]) ||
            $content["type"] != "pf_answer_send" ||
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

            echo json_encode($rlt);
            return;
        }

        $user = $this->mongoCollection->findOne(["tel" => $content["tel"]]);
         if($user == NULL) {
            $rlt = [
                "type" => "pf_answer_confirm",
                "success" => false,
                "error_no" => 3,
                "error_msg" => "tel not found.",
            ];
            echo json_encode($rlt);
            return;
        }

        if(!isset($user["token"]) ||
            $user["token"] != $content["token"]) {

            $rlt = [
                "type" => "pf_answer_confirm",
                "success" => false,
                "error_no" => 4,
                "error_msg" => "token not valid.",
            ];
            echo json_encode($rlt);
            return;
        }

        $answer = $content["answer"];
        //check the validation of the answer.
        if(($answer["pf_id"] < 0 || $answer["pf_id"] >= $this->get_max_pf_ID())
                    || !($answer["choice"] == 0 || $answer["choice"] == 1)) {

            $rlt = [
                "type" => "pf_answer_confirm",
                "success" => false,
                "error_no" => 5,
                "error_msg" => "answer not valid",
            ];
            echo json_encode($rlt);
            return;
        }

        $newdata = ['$push' => ["pf_collected" => $answer]];
        if(!$this->mongoCollection->update([ "tel" => $content["tel"]], $newdata)) {
            $rlt = [
                "type" => "pf_answer_confirm",
                "success" => false,
                "error_no" => 6,
                "error_msg" => "database error",
            ];
            echo json_encode($rlt);
            return;
        }

        $rlt = [
            "type" => "pf_answer_confirm",
            "success" => true,
            "error_no" => 0,
            "error_msg" => "",
            ];
        echo json_encode($rlt);
        return;
    }
}