<?php
namespace kepco\controllers;

use yii\helpers\Json;

class WorkController extends \yii\console\Controller
{
  public function actionBid(){
    $w=new \GearmanWorker;
    $w->addServers('127.0.0.1');
    $w->addFunction('kepco_work_bid',function($job){
       echo $job->workload().PHP_EOL;
       $workload=Json::decode($job->workload());
       print_r($workload);
    });
    while($w->work());
  }
}

