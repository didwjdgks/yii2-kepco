<?php
namespace kepco;

use yii\helpers\Json;
use yii\helpers\ArrayHelper;

class Http extends \yii\base\Component
{
  public $client;

  public $sub;
  public $module;

  public $cookie;
  public $token;

  public function init(){
    parent::init();

    $this->module=\kepco\Module::getInstance();

    $this->client=new \GuzzleHttp\Client([
			'base_uri'=>'http://srm.kepco.net/',
      'cookies'=>true,
      //'debug'=>true,
      'allow_redirects'=>false,
      'headers'=>[
        'User-Agent'=>'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36',
        'Cookie'=>$this->cookie,
        'X-CSRF-TOKEN'=>$this->token,
      ],
    ]);

    $this->sub=\Yii::createObject([
      'class'=>\kepco\Redis::className(),
      'hostname'=>$this->module->redis_server,
    ]);
  }

  public function request($method,$uri='',array $options=[]){
    $res=$this->client->request($method,$uri,$options);
    $body=$res->getBody();
    return $body;
  }

  public function post($uri,array $options=[]){
    $body=$this->request('POST',$uri,$options);
    return Json::decode($body);
  }
}

