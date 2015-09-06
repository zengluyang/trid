<?php
namespace app\controllers;

use Yii;

class ContactController extends \app\controllers\RestController
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

    public function actionSendInvitation()
    {
        $req_type = "send_invitation_request";
        $rlt_type = "send_invitation_result";

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
            !isset($content["peer_tel"])
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

        if( !isset($user["token"]) || $user["token"]!=$content["token"] ) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 4,
                "error_msg" => "token not valid.",
            ];
            return json_encode($rlt);
        }

        if($content["tel"] == $content["peer_tel"]) {
        	$rlt = [
        		"type" => $rlt_type,
        		"success" => false,
        		"error_no" => 5,
        		"error_msg" => "peer tel can't be same as tel.",
        	];
        	return json_encode($rlt);
        }

        //look for peer user.
        $peer_user = $this->mongoCollection->findOne(['tel'=>$content["peer_tel"]]);
        if($peer_user == null) {
            $peer_user = ["tel" => $content["peer_tel"]];
            $this->mongoCollection->insert($peer_user);
        }

        if(isset($peer_user["huanxin_id"])) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 6,
                "error_msg" => "peer user already exist.",
            ];
            return json_encode($rlt);
        }

        $last_invitation_send = $this->find_invitation_send($content["tel"], $content["peer_tel"]);
        if($last_invitation_send != null && time() < $last_invitation_send["expire"]) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 7,
                "error_msg" => "last invitation not expire.",
            ];
            return json_encode($rlt);
        }

	    if( isset($content["word"])  && $content["word"] != "") {
	        $word = $content["word"];
	    } else {
            $word = $this->get_default_word();
        }

        if(!$this->send_invitation_sms($content["tel"], $content["peer_tel"], $word)) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 8,
                "error_msg" => "fail to send invitation sms."
            ];
            return json_encode($rlt);
        }
        
	   $expire = $this->get_invitation_expire_time();
	   $invitation_send = [
		  "peer_tel" => $content["peer_tel"],
		  "num" => 1,
		  "expire" => $expire,
		  "word" => $word,
	   ];
	   //update user's invitation_send_list
        if($last_invitation_send == null) {
            $newdata = [ '$push' => ["invitation_send_list" => $invitation_send]];
            $this->mongoCollection->update(["tel" => $content["tel"]], $newdata);
        } else {
	    $invitation_send["num"] = $last_invitation_send["num"] + 1;
            $newdata = [ '$set' => [
                'invitation_send_list.$.num' => $invitation_send["num"],
                'invitation_send_list.$.expire' => $invitation_send["expire"],
                'invitation_send_list.$.word' => $invitation_send["word"]
                ]
            ];
            $this->mongoCollection->update(["tel" => $content["tel"], "invitation_send_list.peer_tel" => $content["peer_tel"]], 
                $newdata);
        }
        //update peer user's invitation_recv_list
	    $invitation_recv = $invitation_send;
	    $invitation_recv["peer_tel"] = $content["tel"];

	    $last_invitation_recv = $this->find_invitation_recv($content["peer_tel"], $content["tel"]);
        if($last_invitation_recv == null) {
            $newdata = ['$push' => ["invitation_recv_list" => $invitation_recv]];
            $this->mongoCollection->update(["tel" => $content["peer_tel"]], $newdata);
        } else {
            $newdata = [ '$set' => [
                'invitation_recv_list.$.num' => $invitation_recv["num"],
                'invitation_recv_list.$.expire' => $invitation_recv["expire"],
                'invitation_recv_list.$.word' => $invitation_recv["word"]
                ]
            ];
            $this->mongoCollection->update(["tel" => $content["peer_tel"], "invitation_recv_list.peer_tel" => $content["tel"]],
                $newdata);
        }

        $rlt = [
            "type" => $rlt_type,
            "success" => true,
            "error_no" => 0,
            "error_msg" => "",
            "invitation_send" => $invitation_send,
        ];
        return json_encode($rlt);
    }

    public function actionDeleteInvitationSend()
    {
    	$req_type = "delete_invitation_send_request";
    	$rlt_type = "delete_invitation_send_result";

    	$input = file_get_contents("php://input");
        $content = json_decode($input,true);
        if(json_last_error() != JSON_ERROR_NONE) {
            $rlt = [
                "type" => $rlt_type,
                "error_no" => 1,
                "error_msg" => "json decode failed.",
                "success" => false,
            ];
            return json_encode($rlt);
        }

        if( 
            !isset($content["type"]) ||
            $content["type"] != $req_type ||
            !isset($content["tel"]) ||
            !isset($content["token"]) ||
            !isset($content["peer_tel"])
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

        $last_invitation_send = $this->find_invitation_send($content["tel"], $content["peer_tel"]);

        if($last_invitation_send == null) {
        	$rlt = [
        		"type" => $rlt_type,
        		"success" => false,
        		"error_no" => 5,
        		"error_msg" => "invitation not exist.",
        	];
        	return json_encode($rlt);
        }

        $newdata = ['$pull'  => ["invitation_send_list" => ["peer_tel" => $content["peer_tel"]]]];
        $this->mongoCollection->update(["tel" => $content["tel"]], $newdata);

        $rlt = [
        	"type" => $rlt_type,
        	"success" => true,
        	"error_no" => 0,
        	"error_msg" => "",
        ];

        return json_encode($rlt);
    }

    public function actionGetInvitationSendList()
    {
    	$req_type = "get_invitation_send_list";
    	$rlt_type = "invitation_send_list";

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
            !isset($content["token"])
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

        $invitation_send_list = [];
        if(isset($user["invitation_send_list"])) {
            $invitation_send_list = $user["invitation_send_list"];
        }
        $count = count($invitation_send_list);

        $rlt = [
        	"type" => $rlt_type,
        	"success" => true,
        	"error_no" => 0,
        	"error_msg" => "",
        	"count" => $count,
        	"invitation_send_list" => $invitation_send_list,
        ];
	return json_encode($rlt);
    }


    public function actionAcceptInvitation()
    {
    	$req_type = "accept_invitation_request";
    	$rlt_type = "accept_invitation_result";

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
            !isset($content["peer_tel"])
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

        $peer_user = $this->mongoCollection->findOne(["tel" => $content["peer_tel"]]);
        if( $peer_user == null || !isset($peer_user["huanxin_id"])) {
        	$rlt = [
        		"type" => $rlt_type,
        		"success" => false,
        		"error_no" => 5,
        		"error_msg" => "peer user not exist."
        	];

        	return json_encode($peer_user);
        }

        $last_invitation_recv = $this->find_invitation_recv($content["tel"], $content["peer_tel"]);

        if( $last_invitation_recv == null ||
        	$last_invitation_recv["expire"] < time()
        ) {
        	$rlt = [
        		"type" => $rlt_type,
        		"success" => false,
        		"error_no" => 6,
        		"error_msg" => "invitation not valid.",
        	];
		return json_encode($rlt);
        }

	    if( $this->find_friend($content["tel"], $content["peer_tel"]) != null) {
		    $rlt = [
			    "type" => $rlt_type,
			    "success" => false,
			    "error_no" => 7,
			    "error_msg" => "peer user is already in the friend list",
		    ];
		    return json_encode($rlt);
	    }

        $expire = $this->get_chat_expire_time();
        $chat_title = $this->get_chat_title($content["tel"], $content["peer_tel"]);
        $word = $last_invitation_recv["word"];


       	$friend = [
       		"peer_tel" => $content["tel"],
       		"huanxin_id" => $user["huanxin_id"],
       		"type" =>  0,
       		"chat_title" => $chat_title,
       		"expire" => $expire,
       	];

       	$newdata = ['$push' => ["friend_list" => $friend]];
       	$this->mongoCollection->update(["tel" => $content["peer_tel"]], $newdata);
       	$this->add_friend_notify($content["peer_tel"], $friend, $word);
	
       	$friend["peer_tel"] = $content["peer_tel"];
       	$friend["huanxin_id"] = $peer_user["huanxin_id"];
       	$friend["type"] = 1;
       	$newdata = ['$push' => ["friend_list" => $friend]];
       	$this->mongoCollection->update(["tel" => $content["tel"]], $newdata);

       	$rlt = [
       		"type" => $rlt_type,
       		"success" => true,
       		"error_no" => 0,
       		"error_msg" => "",
       		"friend" => $friend,
       		"word" => $word,
       	];

       	return json_encode($rlt);
    }



    public function actionDeleteInvitationRecv()
    {
    	$req_type = "delete_invitation_recv_request";
    	$rlt_type = "delete_invitation_recv_result";

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
            !isset($content["peer_tel"])
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

        $last_invitation_recv = $this->find_invitation_recv($content["tel"], $content["peer_tel"]);

        if($last_invitation_recv == null) {
        	$rlt = [
        		"type" => $rlt_type,
        		"success" => false,
        		"error_no" => 5,
        		"error_msg" => "invitation not exist.",
        	];
        	return json_encode($rlt);
        }

        $newdata = ['$pull'  => ["invitation_recv_list" => ["peer_tel" => $content["peer_tel"]]]];
        $this->mongoCollection->update(["tel" => $content["tel"]], $newdata);

        $rlt = [
        	"type" => $rlt_type,
        	"success" => true,
        	"error_no" => 0,
        	"error_msg" => "",
        ];

        return json_encode($rlt);

    }


    public function actionGetInvitationRecvList()
    {
    	$req_type = "get_invitation_recv_list";
    	$rlt_type = "invitation_recv_list";

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
            !isset($content["token"])
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

        $invitation_recv_list = [];
        if(isset($user["invitation_recv_list"])) {
            $invitation_recv_list = $user["invitation_recv_list"];
        }
        $count = count($invitation_recv_list);

        $rlt = [
        	"type" => $rlt_type,
        	"success" => true,
        	"error_no" => 0,
        	"error_msg" => "",
        	"count" => $count,
        	"invitation_recv_list" => $invitation_recv_list,
        ];
	    return json_encode($rlt);
    }

    public function actionAddFriend()
    {
        $req_type = "add_friend_request";
        $rlt_type = "add_friend_result";

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
            !isset($content["peer_tel"])
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

        if($content["tel"] == $content["peer_tel"]) {
        	$rlt = [
        		"type" => $rlt_type,
        		"success" => false,
        		"error_no" => 5,
        		"error_msg" => "peer_tel can't be same as tel",
        	];

        	return json_encode($rlt);
        }

        $peer_user = $this->mongoCollection->findOne(['tel'=>$content["peer_tel"]]);
        if($peer_user == null || !isset($peer_user["huanxin_id"])) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 6,
                "error_msg" => "peer user not exist.",
            ];

            return json_encode($rlt);
        }

        if($this->find_friend($content["tel"], $content["peer_tel"]) != null) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 7,
                "error_msg" => "peer user is already in the friend list.",
            ];
            return json_encode($rlt);
        }

        if(isset($peer_user["accept_friend"]) && !$peer_user["accept_friend"]) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 8,
                "error_msg" => "peer user reject.",
            ];

            return json_encode($rlt);
        }

	    if( isset($content["word"])  && $content["word"] != "") {
	        $word = $content["word"];
	    } else {
            $word = $this->get_default_word();
        }

        $chat_title = $this->get_chat_title($content["tel"], $content["peer_tel"]);
        $expire = $this->get_chat_expire_time();
        $friend = [
            "peer_tel" => $content["tel"],
            "huanxin_id" => $user["huanxin_id"],
	    	//被暗恋
            "type" => 1,
            "chat_title" => $chat_title,
            "expire" => $expire,
        ];

        //send new_friend_notity to peer user.
        if(!$this->new_friend_notify($content["peer_tel"], $friend, $word)) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 9,
                "error_msg" => "fail to notify peer user.",
            ];
            return json_encode($rlt);
        }
        
        //update peer user's friend_list
        $newdata = ['$push' => ["friend_list" => $friend]];
        $this->mongoCollection->update(["tel" => $content["peer_tel"]], $newdata);
        
        $friend["peer_tel"] = $content["peer_tel"];
        $friend["huanxin_id"] = $peer_user["huanxin_id"];
		//暗恋
        $friend["type"] = 0;
	//update user's friend_list
        $newdata = ['$push' => ["friend_list" => $friend]];
        $this->mongoCollection->update(["tel" => $content["tel"]], $newdata);
       
        $rlt = [
            "type" => $rlt_type,
            "success" => true,
            "error_no" => 0,
            "error_msg" => "",
            "friend" => $friend,
            "word" => $word,
        ];
        return json_encode($rlt);
    }

    public function actionDeleteFriend()
    {
        $req_type = "delete_friend_request";
        $rlt_type = "delete_friend_result";

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
            !isset($content["peer_tel"])
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

        $ufriend = $this->find_friend($content["tel"], $content["peer_tel"]);
        if($ufriend == null) {
            $rlt = [
                "type" => $rlt_type,
                "success" => false,
                "error_no" => 5,
                "error_msg" => "friend don't exist."
            ];
            return json_encode($rlt);
        }

        $pfriend = $this->find_friend($content["peer_tel"], $content["tel"]);
        $newdata = ['$pull' => ["friend_list" => ["peer_tel" => $content["tel"]]]];
        $this->mongoCollection->update(["tel" => $content["peer_tel"]], $newdata);
        $this->delete_friend_notify($content["peer_tel"], $pfriend);

        $newdata = ['$pull' => ["friend_list" => ["peer_tel" => $content["peer_tel"]]]];
        $this->mongoCollection->update(["tel" => $content["tel"]], $newdata);

        $rlt = [
            "type" => $rlt_type,
            "success" => true,
            "error_no" => 0,
            "error_msg" => "",
        ];
        return json_encode($rlt);
    }

    public function actionGetFriendList()
    {
    	$req_type = "get_friend_list";
    	$rlt_type = "friend_list";

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
            !isset($content["token"])
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


        $friend_list = [];
        if(isset($user["friend_list"])) {
            $friend_list = $user["friend_list"];
        }

        $count = count($friend_list);

        $rlt = [
        	"type" => $rlt_type,
        	"success" => true,
        	"error_no" => 0,
        	"error_msg" => "",
        	"count" => $count,
        	"friend_list" => $friend_list,
        ];

        return json_encode($rlt);
    }

    private function get_default_word() {
        //TODO get the default word from database or somewhere else.
        return "somebody have crushed on you in TriD";
    }

    private function send_invitation_sms($tel, $peer_tel, $word) {
        $user = $this->mongoCollection->findOne(['tel'=>$tel]);
        if($user == null) {
            return false;
        }

        if(!isset($user["invitation_sms_timestamp"])) {
            $timediff = 9999999;
        } else {
            $timediff = time() - $user["invitation_sms_timestamp"];
        }

        if($timediff < 60) {
            return false;
        }

        //TODO call 3rd party sms service.

        $newdata = ['$set' => ['invitation_sms_timestamp' => time()]];
        $this->mongoCollection->update(["tel"=>$tel], $newdata);
        return true;
    }

    private function get_chat_title($tel, $peer_tel) {
        //TODO add a algorithm to produce the chat title.
        return "aojiao77254";
    }


    private function find_friend($tel, $peer_tel) {
        $user = $this->mongoCollection->findOne(["tel" => $tel]);
        if($user == null || !isset($user["friend_list"])) {
            return null;
        }

        $friend_list = $user["friend_list"];
        foreach($friend_list as $item) {
            if($item["peer_tel"] == $peer_tel) {
                return $item;
            }
        }

        return null;
    }

    private function find_invitation_send($tel, $peer_tel) {
        $user = $this->mongoCollection->findOne(["tel" => $tel]);
        if($user == null || !isset($user["invitation_send_list"])) {
            return null;
        }
        $invitation_send_list = $user["invitation_send_list"];
        foreach($invitation_send_list as $item) {
            if($item["peer_tel"] == $peer_tel) {
                return $item;
            }
        }
        return null;
    }

    private function find_invitation_recv($tel, $peer_tel) {
        $user = $this->mongoCollection->findOne(["tel" => $tel]);
        if($user == null || !isset($user["invitation_recv_list"])) {
            return null;
        }
        $invitation_recv_list = $user["invitation_recv_list"];
        foreach($invitation_recv_list as $item) {
            if($item["peer_tel"] == $peer_tel) {
                return $item;
            }
        }
        return null;
    }

    private function get_invitation_expire_time() {
        return (time() + 24 * 3600);
    }

    private function get_chat_expire_time() {
    	return (time() + 3 * 24 * 3600);
    }

    private function add_friend_notify($tel, $friend, $word) {
    	return;
    }

    private function delete_friend_notify($tel, $friend) {
    	return;
    }

    private function new_friend_notify($tel, $friend, $word) {
        $user = $this->mongoCollection->findOne(["tel" => $tel]);
        if($user == null || !isset($user["huanxin_id"])) {
            return false;
        }

        $from = "admin";
        $target[] = $user["huanxin_id"]; 
        $target_type = "users";
        $msg = [
            "type" => "cmd",
            "action" => "new_friend_notify",
        ];
        $ext = [
            "friend" => $friend,
            "word" => $word,
        ];

        $result = Yii::$app->easemobClient->yy_hxSend($from, $target, $msg, $target_type, $ext);

        if(!isset($result) ||
            empty($result) ||
            ( isset($result['error']) && !empty($result['error']) )
        ) {
            return false;
        }

        return true;
    }    
}
