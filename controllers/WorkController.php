<?php
namespace kepco\controllers;

use yii\helpers\Json;
use yii\helpers\Console;

use kepco\workers\BidWorker;
use kepco\workers\SucWorker;


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
      sleep(1);
    });
    while($w->work());
  }
  public function actionSuc(){
    $w=new \GearmanWorker;
    $w->addServers('127.0.0.1');
    $w->addFunction('kepco_work_suc',function($job){
      $workload=Json::decode($job->workload());
      $this->stdout2("kepco> %g{$workload['id']}%n {$workload['notinum']} {$workload['constnm']}\n");
      $worker=new SucWorker([
        'id'=>$workload['id'],
      ]);
        $data =$worker->run();
        print_r($data);
        sleep(1);
      });
      while($w->work());
  }
}

