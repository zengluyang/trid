<?php

namespace app\controllers;

class RestController extends \yii\web\Controller
{
	private $mongoCollection = null;

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        $this->header();
        return true;
    }

    private function header() 
    {
        header("Content-Type:application/json;charset=UTF-8");
    }

    protected function generateToken($key,$length=64) {
        if(function_exists('openssl_random_pseudo_bytes')) {
            $token = base64_encode(openssl_random_pseudo_bytes($length/4,$strong));
            if($strong == TRUE)
                return bin2hex($token);
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
