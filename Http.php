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
        'User-Agent'=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36',
        'Cookie'=>'SRM_ID=G7g0K1SXBZACMGAwFqs3P9MY_Qlqknp-y0pMMGv7QWVIVDTiKGgQ!-220352303!-1759289166; org.springframework.web.servlet.theme.CookieThemeResolver.THEME=default',
        'X-CSRF-TOKEN'=>'b9838610-e07b-475a-9650-c2aea00dc500',
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

