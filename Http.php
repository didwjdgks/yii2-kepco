<?php
namespace kepco;

use yii\helpers\Json;

class Http extends \yii\base\Component
{
  public $client;

  public function init(){
    parent::init();

    $this->client=new \GuzzleHttp\Client([
      //'base_uri'=>'http://203.248.44.161/mdi.do',
			'base_uri'=>'http://srm.kepco.net//mdi.do',
      'cookies'=>true,
      'headers'=>[
        'User-Agent'=>'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36',
        'Cookie'=>'WMONID=NHxChMXNQuU; pop25741=done; pop25641=done; pop25761=done; SRM_ID=kw6ODTbztNiX70OG-axStdZCkfGeIret4G1ZqN6c-z4sP16d0MiH!-1812632849!2047882231',
        'X-CSRF-TOKEN'=>'f0efda81-470a-470a-88a8-15d7a9f0e438',
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

