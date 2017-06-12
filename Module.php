<?php
namespace kepco;

use yii\db\Connection;
use yii\di\Instance;
use yii\helpers\Json;

class Module extends \yii\base\Module
{
  public $db='db';
  public $gman_server;
  public $redis_server;

  protected $gman_client;
  protected $talk_client;
  protected $redis;

  public function init(){
    parent::init();

    $this->db=Instance::ensure($this->db,Connection::className());
  }

  public function gman_do($func,$data){
    if($this->gman_client===null){
      $this->gman_client=new \GearmanClient;
      $this->gman_client->addServers($this->gman_server);
    }
    if(is_array($data)) $data=Json::encode($data);
    $this->gman_client->doNormal($func,$data);
  }

  public function gman_doBack($func,$data){
    if($this->gman_client===null){
      $this->gman_client=new \GearmanClient;
      $this->gman_client->addServers($this->gman_server);
    }
    if(is_array($data)) $data=Json::encode($data);
    $this->gman_client->doBackground($func,$data);
  }

  public function gman_talk($msg,$recv=[]){
    if($this->talk_client===null){
      $this->talk_client=new \GearmanClient;
      $this->talk_client->addServers('115.68.48.245');
			//$this->talk_client->addServers('115.68.48.242');
    }
    if(empty($recv)) $recv[]=149;
    $msg="==한국전력공사==\n".$msg;
    foreach($recv as $id){
      $this->talk_client->doBackground('send_chat_message_from_admin',Json::encode([
        'recv_id'=>$id,
        'message'=>$msg,
      ]));
    }
  }

  public function redis_get($key){
    if($this->redis===null){
      $this->redis=\Yii::createObject([
        'class'=>\kepco\Redis::className(),
        'hostname'=>$this->redis_server,
      ]);
    }
    return $this->redis->get($key);
  }

  public function redis_set($key,$value){
    if($this->redis===null){
      $this->redis=\Yii::createObject([
        'class'=>\kepco\Redis::className(),
        'hostname'=>$this->redis_server,
      ]);
    }
    $this->redis->set($key,$value);
  }

  public function publish($channel,$message){
    if($this->redis===null){
      $this->redis=\Yii::createObject([
        'class'=>\kepco\Redis::className(),
        'hostname'=>$this->redis_server,
      ]);
    }
    $this->redis->publish($channel,$message);
  }
}
