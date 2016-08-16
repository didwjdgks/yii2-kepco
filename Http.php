<?php
namespace kepco;

use yii\helpers\Json;

class Http extends \yii\base\Component
{
  public $client;

  public $sub;
  public $module;

  public function init(){
    parent::init();

    $this->module=\kepco\Module::getInstance();

    $this->client=new \GuzzleHttp\Client([
      //'base_uri'=>'http://203.248.44.161/mdi.do',
			'base_uri'=>'http://srm.kepco.net/',
      'cookies'=>true,
      'headers'=>[
        'User-Agent'=>'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36',
        'Cookie'=>'WMONID=MjsXQm-vWwH; SRM_ID=j-GS3zuv5QwevGapawbknVdmnJUlBiRRoustWbAlLAC-PTxd8eJz!1386704706!-1013934162',
        'X-CSRF-TOKEN'=>'15252c43-79fa-4ef7-91c0-4366126247ea',
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

