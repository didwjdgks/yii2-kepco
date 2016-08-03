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
        'Cookie'=>'SRM_ID=eJhO0lpGEbFnoZBNxa9nBLs0K1dGMkXTIkKgdJi6YMTMWQx7yYUo!-419358261!NONE; org.springframework.web.servlet.theme.CookieThemeResolver.THEME=default',
        'X-CSRF-TOKEN'=>'4e492e1f-6158-4f91-91e7-c1c6ea583674',
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

