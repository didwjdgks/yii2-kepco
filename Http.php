<?php
namespace kepco;

use yii\helpers\Json;

class Http extends \yii\base\Component
{
  public $client;

  public function init(){
    parent::init();

    $this->client=new \GuzzleHttp\Client([
      'base_uri'=>'http://203.248.44.161',
      'cookies'=>true,
      'headers'=>[
        'User-Agent'=>'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36',
        'Cookie'=>'WMONID=NHxChMXNQuU; SRM_ID=oo5ZDR8UMcbT85yY592ZbILF30076LuVEsyjU8loFFBM3guRXlll!-931456640!-1234176548',
        'X-CSRF-TOKEN'=>'7c119e43-07a6-4676-bffb-9396f013b8cf',
      ],
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

