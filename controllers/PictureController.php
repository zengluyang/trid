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
    private $mongoCollection = null;
    protected $allowType = array('jpg', 'jpeg', 'gif', 'png', 'bmp', 'tif');

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $m = new \MongoClient();
        $this->mongoCollection = $m->selectCollection('local','picture');       
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
            echo json_encode($rlt);
            return;
        }

        if(
            !isset($json_data['type']) ||
            $json_data['type']!="picture_upload_request" ||
            !isset($json_data['picture']) ||
            !isset($json_data['type']) ||
            !isset($json_data['words'])
        ) {

            $rlt = [
                "type" => "picture_upload_response",
                "success" => false,
                "error_no" => 2,
                "error_msg" => "input not valid.",
            ];
            echo json_encode($rlt);
            return;
        }

        $token = $json_data['token'];
        $picture = $json_data['picture'];
        $type = $json_data['type'];
        $words = $json_data['words'];

        if (!preg_match('/^(data:\s*image\/(\w+);base64,)/', $picture, $result)) {
            $rlt = [
                "type" => "picture_upload_response",
                "success" => false,
                "error_no" => 2,
                "error_msg" => "input not valid.",
            ];
            echo json_encode($rlt);
            return;

        }

        $type = $result[2];
        //检测文件类型
        if (!in_array($type, $this->allowType)) {
            $rlt = [
                "type" => "picture_upload_response",
                "success" => false,
                "error_no" => 3,
                "error_msg" => "upload type not allowed.",
            ];
            echo json_encode($rlt);
            return;
        }

        //随机生成一个文件名
        $randName = time() . rand(1000, 9999) . "." . $type;
        //文件保存于 web/uploads 目录下
        $new_file = "uploads/$randName";

        if (!file_put_contents($new_file, base64_decode(str_replace($result[1], '', $picture)))) {                  
            $rlt = [
                "type" => "picture_upload_response",
                "success" => false,
                "error_no" => 4,
                "error_msg" => "file save failed.",
            ];
            echo json_encode($rlt);
            return;
        }

        if (!$this->saveTest($token, $randName, $words)) {
            $rlt = [
                "type" => "picture_upload_response",
                "success" => false,
                "error_no" => 5,
                "error_msg" => "database error.",
            ];
            echo json_encode($rlt);
            return ;
        }

        $rlt = [
            "type" => "picture_upload_response",
            "success" => true,
            "error_no" => 0,
            "error_msg" => null,
            "picture" => $randName,
        ];
        echo json_encode($rlt);
        return ;
    }

    /*
     * 查询朋友圈信息
     * */
    public function actionSearch(){
        $content = file_get_contents('php://input');
        $json_data = json_decode($content, true);
        $token = $json_data['token'];
        $type = $json_data['type'];
        if ("picture_info_request" == $type) {
            $result = iterator_to_array($this->mongoCollection->find());
            return json_encode($result);
        }
    }

    /*
     * 数据库操作：插入数据
     * */
    private function saveTest($token, $pictureName, $words)
    {
        $time = time();
        /*$newdata = array('$set' => array("token" => "$token","picture" => "$pictureName","word" => "$words"));
        $this->mongoCollection->update(["token"=>$token],$newdata,["upsert"=>true]);*/
        $newdata = array("token" => "$token", "picture" => "$pictureName", "word" => "$words","createtime" => "$time");
        $this->mongoCollection->insert($newdata);
        return true;
    }


} 