<?php
/**
 * Created by PhpStorm.
 * User: LiuRuiQun
 * Date: 15-8-10
 * Time: 下午5:02
 */

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;
use yii\mongodb\Query;
use app\models\Status;

class PictureController extends \app\controllers\RestController {
    private $pictureCollection = null;
    private $userColleciton = null;

    protected $allowType = array('jpg', 'jpeg', 'gif', 'png', 'bmp', 'tif');

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $m = new \MongoClient();
        $this->pictureCollection = $m->selectCollection($this->mongoDbName,'picture');
        $this->userColleciton = $m->selectCollection($this->mongoDbName,'user');       
        return true;
    }

    /*
     * 上传图片及文字信息
     * */
    public function actionUpload()
    {
        //获取接口输入信息
        $content = file_get_contents('php://input');
        $json_data = json_decode($content, true);

        if(json_last_error()!=JSON_ERROR_NONE) {
            $rlt = [
                "type" => "picture_upload_response",
                "success" => false,
                "error_no" => 1,
                "error_msg" => "json decode failed.",
            ];
            return json_encode($rlt);
        }

        if(
            !isset($json_data['type']) ||
            $json_data['type']!="picture_upload_request" ||
            !isset($json_data['token']) ||
            !isset($json_data['picture']) ||
            !isset($json_data['type']) ||
            !isset($json_data['tel']) ||
            !isset($json_data['words'])
        ) {
            $rlt = [
                "type" => "picture_upload_response",
                "success" => false,
                "error_no" => 2,
                "error_msg" => "input not valid.",
            ];
            return json_encode($rlt);
        }

        $picture = $json_data['picture'];
        $type = $json_data['type'];
        $words = $json_data['words'];
        $tel = $json_data['tel'];
        $token = $json_data['token'];

        $user = $this->userColleciton->findOne(['tel'=>$tel]);

        if($user==null) {
            $rlt = [
                "type" => "picture_upload_response",
                "success" => false,
                "error_no" => 3,
                "error_msg" => "tel not found.",
            ];
            return json_encode($rlt);
        }

        if(!isset($user["token"])||$token!=$user["token"]) {
            $rlt = [
                "type" => "picture_upload_response",
                "success" => false,
                "error_no" => 4,
                "error_msg" => "token not valid.",
            ];
            return json_encode($rlt);
        }

        if(!isset($user["username"])) {
            $rlt = [
                "type" => "picture_upload_response",
                "success" => false,
                "error_no" => 5,
                "error_msg" => "nick does not exist..",
            ];
            return json_encode($rlt);
        }

        if (!preg_match('/^(data:\s*image\/(\w+);base64,)/', $picture, $result)) {
            $rlt = [
                "type" => "picture_upload_response",
                "success" => false,
                "error_no" => 6,
                "error_msg" => "picture not valid.",
            ];
            return json_encode($rlt);

        }

        $type = $result[2];
        //检测文件类型
        if (!in_array($type, $this->allowType)) {
            $rlt = [
                "type" => "picture_upload_response",
                "success" => false,
                "error_no" => 7,
                "error_msg" => "upload type not allowed.",
            ];
            return json_encode($rlt);
        }

        //随机生成一个文件名
        $randName = time() . $this->generateToken("",8) . "." . $type;
        //文件保存于 web/uploads 目录下
        $dirName = 'uploads/'.$tel;
        if(!file_exists($dirName)) {
            if(!mkdir($dirName,0744)) {
                $rlt = [
                    "type" => "picture_upload_response",
                    "success" => false,
                    "error_no" => 8,
                    "error_msg" => "file save failed.",
                ];
                return json_encode($rlt);
            }
        }
        $new_file = "$dirName/$randName";

        if (!file_put_contents($new_file, base64_decode(str_replace($result[1], '', $picture)))) {                  
            $rlt = [
                "type" => "picture_upload_response",
                "success" => false,
                "error_no" => 9,
                "error_msg" => "file save failed.",
            ];
            return json_encode($rlt);
        }
        $fileUrl = '/'.$new_file;
        if (!$this->saveTest($fileUrl, $words,$user["_id"],$user['username'])) {
            $rlt = [
                "type" => "picture_upload_response",
                "success" => false,
                "error_no" => 10,
                "error_msg" => "database error.",
            ];
            return json_encode($rlt);
        }

        $rlt = [
            "type" => "picture_upload_response",
            "success" => true,
            "error_no" => 0,
            "error_msg" => null,
            "picture" => $fileUrl,
        ];
        return json_encode($rlt);
    }

    /*
     * 删除图片及文字信息
     * */
    public function actionDelete()
    {
        //获取接口输入信息
        $content = file_get_contents('php://input');
        $json_data = json_decode($content, true);

        if(json_last_error()!=JSON_ERROR_NONE) {
            $rlt = [
                "type" => "picture_delete_response",
                "success" => false,
                "error_no" => 1,
                "error_msg" => "json decode failed.",
            ];
            return json_encode($rlt);
        }

        if(
            !isset($json_data['type']) ||
            $json_data['type']!="picture_delete_request" ||
            !isset($json_data['token']) ||
            !isset($json_data['tel'])||
            !isset($json_data['picture_id'])
        ) {
            $rlt = [
                "type" => "picture_delete_response",
                "success" => false,
                "error_no" => 2,
                "error_msg" => "input not valid.",
            ];
            return json_encode($rlt);
        }


        $token = $json_data['token'];
        $type = $json_data['type'];
        $tel = $json_data['tel'];
        $picture_id = $json_data['picture_id'];
        $user = $this->userColleciton->findOne(['tel'=>$tel]);

        if($user==null) {
            $rlt = [
                "type" => "picture_delete_response",
                "success" => false,
                "error_no" => 3,
                "error_msg" => "tel not found.",
            ];
            return json_encode($rlt);
        }

        if(!isset($user["token"])||$token!=$user["token"]) {
            $rlt = [
                "type" => "picture_delete_response",
                "success" => false,
                "error_no" => 4,
                "error_msg" => "token not valid.",
            ];
            return json_encode($rlt);
        }


        $mongoID = new \MongoID("$picture_id");
        $picture = $this->pictureCollection->findOne(array("_id" => $mongoID),array('created_by'));
        if($picture == null) {
            $rlt = [
                "type" => "picture_delete_response",
                "success" => false,
                "error_no" => 5,
                "error_msg" => "query result is null.",
            ];
            return json_encode($rlt);
        }

        if($user["_id"] == $picture["created_by"]){
            //删除指定信息
            if(!$this->deleteData( $mongoID)){
                $rlt = [
                    "type" => "picture_delete_response",
                    "success" => false,
                    "error_no" => 6,
                    "error_msg" => "database error.",
                ];
                return json_encode($rlt);
            }
        }else{
            $rlt = [
                "type" => "picture_delete_response",
                "success" => false,
                "error_no" => 7,
                "error_msg" => "permission denied.",
            ];
            return json_encode($rlt);
        }


        $rlt = [
            "type" => "picture_delete_response",
            "success" => true,
            "error_no" => 0,
            "error_msg" => null,
        ];
        return json_encode($rlt);
    }

    /*
     * 查询朋友圈信息
     * */
    public function actionSearch(){
        $content = file_get_contents('php://input');
        $json_data = json_decode($content, true);

        if(json_last_error()!=JSON_ERROR_NONE) {
            $rlt = [
                "type" => "picture_search_response",
                "success" => false,
                "error_no" => 1,
                "error_msg" => "json decode failed.",
            ];
            return json_encode($rlt);
        }

        if(
            !isset($json_data['type']) ||
            $json_data['type']!="picture_search_request" ||
            !isset($json_data['token']) ||
            !isset($json_data['tel'])
        ) {
            $rlt = [
                "type" => "picture_search_response",
                "success" => false,
                "error_no" => 2,
                "error_msg" => "input not valid.",
            ];
            return json_encode($rlt);
        }


        $token = $json_data['token'];
        $type = $json_data['type'];
        $tel = $json_data['tel'];
        $user = $this->userColleciton->findOne(['tel'=>$tel]);
        if($user==null) {
            $rlt = [
                "type" => "picture_search_response",
                "success" => false,
                "error_no" => 3,
                "error_msg" => "tel not found.",
            ];
            return json_encode($rlt);
        }

        if(!isset($user["token"])||$token!=$user["token"]) {
            $rlt = [
                "type" => "picture_search_response",
                "success" => false,
                "error_no" => 4,
                "error_msg" => "token not valid.",
            ];
            return json_encode($rlt);
        }
        $cursor = $this->pictureCollection->find()->sort(['createtime' => -1]);
        $count = $cursor->count();
        $limit = $count;
        $pics = iterator_to_array($cursor,false);
        $rlt = [
            "type" => "picture_search_response",
            "success" => true,
            "error_no" => 0,
            "error_msg" => null,
            "count" => $count,
            "offset" => 0,
            "limit" => $limit,
            "pictures" => $pics,
        ];
        return json_encode($rlt);

    }

    /*
    * 点赞
    * */
    public function actionLike(){
        $content = file_get_contents('php://input');
        $json_data = json_decode($content, true);

        if(json_last_error()!=JSON_ERROR_NONE) {
            $rlt = [
                "type" => "picture_search_response",
                "success" => false,
                "error_no" => 1,
                "error_msg" => "json decode failed.",
            ];
            return json_encode($rlt);
        }

        if(
            !isset($json_data['type']) ||
            $json_data['type']!="picture_like_request" ||
            !isset($json_data['token']) ||
            !isset($json_data['tel'])||
            !isset($json_data['picture_id'])
        ) {
            $rlt = [
                "type" => "picture_like_response",
                "success" => false,
                "error_no" => 2,
                "error_msg" => "input not valid.",
            ];
            return json_encode($rlt);
        }


        $token = $json_data['token'];
        $type = $json_data['type'];
        $tel = $json_data['tel'];
        $picture_id = $json_data['picture_id'];
        $user = $this->userColleciton->findOne(['tel'=>$tel]);

        if($user==null) {
            $rlt = [
                "type" => "picture_like_response",
                "success" => false,
                "error_no" => 3,
                "error_msg" => "tel not found.",
            ];
            return json_encode($rlt);
        }

        if(!isset($user["token"])||$token!=$user["token"]) {
            $rlt = [
                "type" => "picture_like_response",
                "success" => false,
                "error_no" => 4,
                "error_msg" => "token not valid.",
            ];
            return json_encode($rlt);
        }
        try {
            $mongoID = new \MongoID("$picture_id");
        } catch (\MongoException $ex) {
            $rlt = [
                "type" => "picture_like_response",
                "success" => false,
                "error_no" => 5,
                "error_msg" => "picture_id not valid.",
            ];
            return json_encode($rlt);
        }
        
        $picture = $this->pictureCollection->findOne(array("_id" => $mongoID),array('like_by'));
        $user = $this->userColleciton->findOne(['tel'=>$tel]);
        if(!isset($user['username'])){
            $rlt = [
                "type" => "picture_like_response",
                "success" => false,
                "error_no" => 5,
                "error_msg" => "username do not exist.",
            ];
            return json_encode($rlt);
        } else{
            $nick = $user['username'];
            if(!in_array($nick, $picture['like_by'])){
                $this->pictureCollection->update(
                    array("_id" => $mongoID),
                    array('$inc' => array("like" => 1)),
                    array("upsert" => true)
                );
                $newdata = array( '$push' => array('like_by' => "$nick"));
                $this->pictureCollection->update(array("_id" => $mongoID), $newdata);
            }
            $picture = $this->pictureCollection->findOne(array("_id" => $mongoID));

            $rlt = [
                "type" => "picture_like_response",
                "success" => true,
                "error_no" => 0,
                "error_msg" => null,
                "picture" => $picture,
            ];
            return json_encode($rlt);
        }
    }

    /*
    * 取消点赞
    * */
    public function actionUnlike(){
        $content = file_get_contents('php://input');
        $json_data = json_decode($content, true);

        if(json_last_error()!=JSON_ERROR_NONE) {
            $rlt = [
                "type" => "picture_unlike_response",
                "success" => false,
                "error_no" => 1,
                "error_msg" => "json decode failed.",
            ];
            return json_encode($rlt);
        }

        if(
            !isset($json_data['type']) ||
            $json_data['type']!="picture_unlike_request" ||
            !isset($json_data['token']) ||
            !isset($json_data['tel'])||
            !isset($json_data['picture_id'])
        ) {
            $rlt = [
                "type" => "picture_unlike_response",
                "success" => false,
                "error_no" => 2,
                "error_msg" => "input not valid.",
            ];
            return json_encode($rlt);
        }


        $token = $json_data['token'];
        $type = $json_data['type'];
        $tel = $json_data['tel'];
        $picture_id = $json_data['picture_id'];
        $user = $this->userColleciton->findOne(['tel'=>$tel]);

        if($user==null) {
            $rlt = [
                "type" => "picture_unlike_response",
                "success" => false,
                "error_no" => 3,
                "error_msg" => "tel not found.",
            ];
            return json_encode($rlt);
        }

        if(!isset($user["token"])||$token!=$user["token"]) {
            $rlt = [
                "type" => "picture_unlike_response",
                "success" => false,
                "error_no" => 4,
                "error_msg" => "token not valid.",
            ];
            return json_encode($rlt);
        }

        try {
            $mongoID = new \MongoID("$picture_id");
        } catch (\MongoException $ex) {
            $rlt = [
                "type" => "picture_unlike_response",
                "success" => false,
                "error_no" => 5,
                "error_msg" => "picture_id not valid.",
            ];
            return json_encode($rlt);
        }

        $picture = $this->pictureCollection->findOne(['_id' => $mongoID],['like_by']);
        $user = $this->userColleciton->findOne(['tel'=>$tel]);
        if(!isset($user['username'])){
            $rlt = [
                "type" => "picture_like_response",
                "success" => false,
                "error_no" => 6,
                "error_msg" => "username do not exist.",
            ];
            return json_encode($rlt);
        } else{
            $nick = $user['username'];
            if(!in_array($nick, $picture['like_by'])){
                $rlt = [
                    "type" => "picture_unlike_response",
                    "success" => false,
                    "error_no" => 6,
                    "error_msg" => "permission denied.",
                ];
                return  json_encode($rlt);
            }else{
                $this->pictureCollection->update(
                    array("_id" => $mongoID),
                    array('$inc' => array("like" => -1)),
                    array("upsert" => true)
                );
                $newdata = array( '$pull' => array('like_by' => "$nick"));
                $this->pictureCollection->update(array("_id" => $mongoID), $newdata);

                $picture = $this->pictureCollection->findOne(array("_id" => $mongoID));
                $rlt = [
                    "type" => "picture_unlike_response",
                    "success" => true,
                    "error_no" => 0,
                    "error_msg" => null,
                    "picture" => $picture,
                ];
                return  json_encode($rlt);
            }
        }
    }

    /*
    * 评论
    * */
    public function actionComment(){
        $content = file_get_contents('php://input');
        $json_data = json_decode($content, true);

        if(json_last_error()!=JSON_ERROR_NONE) {
            $rlt = [
                "type" => "picture_comment_response",
                "success" => false,
                "error_no" => 1,
                "error_msg" => "json decode failed.",
            ];
            return json_encode($rlt);
        }

        if(
            !isset($json_data['type']) ||
            $json_data['type']!="picture_comment_request" ||
            !isset($json_data['token']) ||
            !isset($json_data['tel'])||
            !isset($json_data['picture_id'])
        ) {
            $rlt = [
                "type" => "picture_comment_response",
                "success" => false,
                "error_no" => 2,
                "error_msg" => "input not valid.",
            ];
            return json_encode($rlt);
        }


        $token = $json_data['token'];
        $type = $json_data['type'];
        $tel = $json_data['tel'];
        $picture_id = $json_data['picture_id'];
        $comment = $json_data['comment'];
        //print_r($comment) ;
        $user = $this->userColleciton->findOne(['tel'=>$tel]);

        if($user==null) {
            $rlt = [
                "type" => "picture_comment_response",
                "success" => false,
                "error_no" => 3,
                "error_msg" => "tel not found.",
            ];
            return json_encode($rlt);
        }

        if(!isset($user["token"])||$token!=$user["token"]) {
            $rlt = [
                "type" => "picture_comment_response",
                "success" => false,
                "error_no" => 4,
                "error_msg" => "token not valid.",
            ];
            return json_encode($rlt);
        }


        
        try {
            $mongoID = new \MongoID("$picture_id");
        } catch (\MongoException $ex) {
            $rlt = [
                "type" => "picture_comment_response",
                "success" => false,
                "error_no" => 5,
                "error_msg" => "picture_id not valid.",
            ];
            return json_encode($rlt);
        }
        
        $picture = $this->pictureCollection->findOne(['_id' => $mongoID]);
        $user = $this->userColleciton->findOne(['tel'=>$tel]);
        if(!isset($user['username'])){
            $rlt = [
                "type" => "picture_like_response",
                "success" => false,
                "error_no" =>6,
                "error_msg" => "username do not exist.",
            ];
            return json_encode($rlt);
        } else{
            $nick = $user['username'];
            if(isset($picture['comments'])) {
                $comment["id"] = count($picture['comments']) + 1;//id 为 0 时，表示对图片的直接回复
            } else {
                $comment["id"] = 1;
            }

            $comment["nick"] = $nick;
            $comment["create_time"] = time();
            $newdata = array( '$push' => array('comments' => $comment));
            $this->pictureCollection->update(array("_id" => $mongoID), $newdata);

            $picture = $this->pictureCollection->findOne(array("_id" => $mongoID));
            $rlt = [
                "type" => "picture_comment_response",
                "success" => true,
                "error_no" => 0,
                "error_msg" => null,
                "picture" => $picture,
            ];
            return json_encode($rlt);
        }
    }

    /*
     * 数据库操作：插入数据
     * */
    private function saveTest($pictureName, $words,$user_id,$username)
    {
        $time = time();
        $like = 0;
        $like_by = array();
        $comments = array();
        $newdata = array(
            "picture" => "$pictureName",
            "word" => $words,
            "like" => $like,
            "like_by" => $like_by,
            "comments" => $comments,
            "createtime" => $time,
            "created_by"=>$user_id,
            "nick"=> $username);
        $this->pictureCollection->insert($newdata);
        return true;
    }

    /*
     * 数据库操作：删除数据
     * */
    private function deleteData( $mongoID){
        $this->pictureCollection->remove(array('_id' =>  $mongoID));
        return true;
    }
} 