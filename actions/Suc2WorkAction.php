<?php
namespace kepco\actions;

use yii\helpers\Console;
use yii\helpers\Json;

use kepco\workers\SucWorker2;
use kepco\models\BidKey;

class Suc2WorkAction extends \yii\base\Action
{
  public $module;

  public function init(){
    parent::init();
    $this->module=$this->controller->module;
  }

  public function stdout($str){
    $this->controller->stdout(Console::renderColoredString($str));
  }

  public function memory_usage(){
    $this->controller->stdout(sprintf("[%s] Peak memory usage: %s Mb\n",
      date('Y-m-d H:i:s'),
      (memory_get_peak_usage(true)/1024/1024))
    ,Console::FG_GREY);
  }

  public function run(){
    $w=new \GearmanWorker;
    $w->addServers($this->module->gman_server);
    $w->addFunction('kepco_work_suc2',function($job){
      try{
        $workload=Json::decode($job->workload());
        $this->stdout("%2%kkepco> [낙찰2] {$workload['id']} {$workload['no']} {$workload['name']}%n\n");
        $cookie=$this->module->redis_get('kepco.cookie');
        $token=$this->module->redis_get('kepco.token');

        $suc=new SucWorker2([
          'id'=>$workload['id'],
          'cookie'=>$cookie,
          'token'=>$token,
        ]);
        $data=$suc->run();
        //print_r($data);
        $this->stdout(" > 공고번호 : {$data['notinum']}\n");
        $this->stdout(" > 예정가격 : {$data['yega']}\n");
        //$this->stdout(" > 복수예가 : {$data['multispare']}\n");
        $this->stdout(" > 1순위 : {$data['officenm1']}\n");
        $this->stdout(" > 참여수 : {$data['innum']}\n");
        $this->stdout(" > 진행상태 : {$data['bidproc']}\n");

        if(strlen($data['notinum'])<10) return;
        list($notino,$revno)=explode('-',$data['notinum']);

        if(preg_match('/^\d{10}$/',$notino,$m)){
          $old_noti=substr($notino,0,4).'-'.substr($notino,4);
        }else{
          $old_noti=substr($notino,0,3).'-'.substr($notino,3,2).'-'.substr($notino,5);
        }
        $query=BidKey::find()->where([
          'whereis'=>'03',
        ]);
        $query->andWhere("notinum like '{$old_noti}%' or notinum like '{$notino}%'");
        $bidkey=$query->orderBy('bidid desc')->limit(1)->one();
        if($bidkey===null) return;

        $data['bidid']=$bidkey->bidid;

        $this->stdout(" > 개찰정보 저장 : {$bidkey->notinum} {$bidkey->constnm} ({$bidkey->state} {$bidkey->bidproc})\n");

        $this->module->gman_do('i2_auto_suc',Json::encode($data));
      }
      catch(\Exception $e){
        $this->stdout("%r$e%n\n");
        \Yii::error($e,'kepco');
      }
      $this->module->db->close();
      $this->memory_usage();
      sleep(1);
    });
    while($w->work());
  }
}

