<?php
namespace kepco\controllers;

use yii\helpers\Json;
use yii\helpers\Console;

use kepco\workers\BidWorker;

class WorkController extends \yii\console\Controller
{
  public function stdout2($string){
    $this->stdout(Console::renderColoredString($string));
  }

  public function actionBid(){
    $w=new \GearmanWorker;
    $w->addServers('127.0.0.1');
    $w->addFunction('kepco_work_bid',function($job){
      $workload=Json::decode($job->workload());
      $this->stdout2("kepco> %g{$workload['notinum']}%n {$workload['constnm']} {$workload['id']}\n");
      $worker=new BidWorker([
        'id'=>$workload['id'],
      ]);
      $data=$worker->run();
      print_r($data);
    });
    while($w->work());
  }
  public function actionSuc(){
    $w=new \GearmanWorker;
    $w->addServers('127.0.0.1');
    $w->addFunction('kepco_work_suc',function($job){
      echo $job->workload().PHP_EOL;
      $workload=Json::decode($job->workload());
      print_r($workload);
      });
      while($w->work());
  }
}

