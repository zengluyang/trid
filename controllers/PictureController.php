<?php
/**
 * Created by PhpStorm.
 * User: LiuRuiQun
 * Date: 15-8-10
 * Time: 下午5:02
 */

namespace app\controllers;

class PictureController extends Controller{
    private $mongoCollection = null;
    protected $allowType = array('jpg', 'jpeg', 'gif', 'png', 'bmp', 'tif');

    /*
     * 上传图片及文字信息
     * */
    public function actionUpload()
    {
        //获取接口输入信息
        $content = file_get_contents('php://input');
        $json_data = json_decode($content, true);
        //var_dump( $json_data);
        $token = $json_data['token'];
        //var_dump($token);
        $picture = $json_data['picture'];
        $type = $json_data['type'];
        $words = $json_data['words'];
        var_dump($words);
        if ("picture_upload_request" == $type) {
            if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $picture, $result)) {
                $type = $result[2];
                //检测文件类型
                if (!in_array($type, $this->allowType)) {
                    return false;
                } else {
                    //随机生成一个文件名
                    $randName = time() . rand(1000, 9999) . "." . $type;
                    //文件保存于 web/uploads 目录下
                    $new_file = "uploads/$randName";
                    if (file_put_contents($new_file, base64_decode(str_replace($result[1], '', $picture)))) {
                        echo '新文件保存成功：', $new_file;
                    }
                };
            }
        } else {
            return false;
        };


        //连接数据库
        $m = new \MongoClient();
        $this->mongoCollection = $m->selectCollection('test', 'picture');

        if (!$this->saveTest($token, $randName, $words)) {
            return false;
        }
    }

    /*
     * 查询朋友圈信息
     * */
    public function actionSearch(){
        $content = file_get_contents('php://input');
        $json_data = json_decode($content, true);
        //var_dump( $json_data);
        $token = $json_data['token'];
        $type = $json_data['type'];
        //var_dump( $type);
        if ("picture_info_request" == $type) {
            echo "hello";
            $query = new Query();
            $result = $query->select([])->from('picture')->all();
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