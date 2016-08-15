<?php
namespace kepco\controllers;

use yii\helpers\Json;
use yii\helpers\Console;

use kepco\workers\BidWorker;
use kepco\workers\SucWorker;
use kepco\models\BidKey;

class WorkController extends \yii\console\Controller
{

//  public $i2_gman_func='i2_auto_bid_test';
//  public $i2_suc_gman_func='i2_auto_suc_test';
  public $i2_gman_func;

  public function stdout2($string){
    $this->stdout(Console::renderColoredString($string));
  }

  public function actionBid(){
    $this->i2_gman_func = 'i2_auto_bid_test';
    $w=new \GearmanWorker;
    $w->addServers('127.0.0.1');
    $w->addFunction('kepco_work_bid',function($job){
      $workload=Json::decode($job->workload());
      $this->stdout2("kepco> %g{$workload['notinum']}%n {$workload['constnm']} {$workload['id']}\n");
      $worker=new BidWorker([
        'id'=>$workload['id'],
      ]);
      $data=$worker->run();
      $bidkey = BidKey::find() -> where("notinum='{$workload['notinum']}' and constnm='{$workload['constnm']}' and notinum_ex='{$workload['revision']}'")
      ->andWhere(['whereis'=>'03'])
      ->orderBy('bidid desc')
      ->limit(1)->one();			
      
			if($bidkey!==null) return;
			
			$c=new \GearmanClient;
			$c->addServers('127.0.0.1');
			$c->doNormal($this->i2_gman_func,Json::encode($data));
			$this->stdout2("   %g>>> do {$this->i2_gman_func} {$data['bidid']} {$data['bidproc']}%n\n");

      print_r($data);
      sleep(1);
    });
    while($w->work());
  }
  public function actionSuc(){
    $this->i2_gman_func = 'i2_auto_suc_test';
    $w=new \GearmanWorker;
    $w->addServers('127.0.0.1');
    $w->addFunction('kepco_work_suc',function($job){
      $workload=Json::decode($job->workload());
      $this->stdout2("kepco> %g{$workload['notinum']}%n %r{$workload['constnm']} \n");
      $worker=new SucWorker([
        'id'=>$workload['id'],
      ]);
      $data =$worker->run();
      $bidkey = BidKey::find() -> where("notinum='{$workload['notinum']}'")
      ->andWhere(['whereis'=>'03'])
      ->orderBy('bidid desc')
      ->limit(1)->one();
      if($bidkey===null) return;
      $data['notinum'] = $bidkey['notinum'];
      $data['constnm'] = $bidkey['constnm'];
      $data['bidid'] = $bidkey['bidid'];

      $c=new \GearmanClient;
      $c->addServers('127.0.0.1');
      $c->doNormal($this->i2_gman_func,Json::encode($data));
      print_r($data);
      sleep(1);
    });
      while($w->work());
  }
}

