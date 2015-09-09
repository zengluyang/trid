<?php

namespace app\controllers;

use Yii;

class RestController extends \yii\web\Controller
{
	private $mongoCollection = null;
	protected $logCollection = null;

    protected $logRequestAndResponse = true;

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        \Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;

        $m = new \MongoClient();
        $this->logCollection = $m->selectCollection('local','log');
        $this->header();
        return true;
    }

    public function afterAction($action,$result) {

        $result = parent::afterAction($action,$result);
        if($this->logRequestAndResponse) {
            $body = \Yii::$app->request->rawBody;
            $new_data = [
                "request"=>[
                    "route"=>isset(\Yii::$app->request->resolve()[0])?\Yii::$app->request->resolve()[0]:"",
                    "body"=>strlen($body)<200 ? $body : substr($body,0,200),
                    "Content-Type"=>\Yii::$app->request->headers->get('Content-Type'),
                    "User-Agent"=>\Yii::$app->request->headers->get('User-Agent'),
                ],
                "response" => [
                    "body"=>strlen($result)<200 ? json_decode($result) : substr($result,0,200),
                    "header"=>\Yii::$app->response->headers->toArray(),
                ],
                "time"=>new \MongoDate(),
            ];
            //var_dump($new_data);
            $this->logCollection->insert($new_data);
        }
        return $result;
    }

    private function header()
    {
        \Yii::$app->response->headers->set("Content-Type","application/json;charset=UTF-8");
    }

    protected function generateToken($key,$length=64) {
        if(function_exists('openssl_random_pseudo_bytes')) {
            $bytes = base64_encode(openssl_random_pseudo_bytes($length,$strong));
            if($strong == TRUE)
                return strtr(substr(base64_encode($bytes), 0, $length), '+/', '_-');
        }

        //fallback to mt_rand if php < 5.3 or no openssl available
        $characters = '0123456789';
        $characters .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz/+'; 
        $charactersLength = strlen($characters)-1;
        $token = '';

        //select some random characters
        for ($i = 0; $i < $length; $i++) {
            $token .= $characters[mt_rand(0, $charactersLength)];
        }        

        return $token;
    }
}
