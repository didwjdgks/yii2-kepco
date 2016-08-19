<?php
namespace kepco\controllers;

use yii\helpers\Json;
use yii\helpers\Console;

use kepco\workers\BidWorker;
use kepco\workers\SucWorker;
use kepco\models\BidKey;

class WorkController extends \yii\console\Controller
{


  public $i2_gman_func;
//  private $i2_func='i2_auto_suc';

  


  public function stdout2($string){
    $this->stdout(Console::renderColoredString($string));
  }

  public function actions(){
    return [
      'bid'=>'kepco\actions\BidWorkAction',
    ];
  }
  public function memory_usage(){
    $this->controller->sudout(sprintf("[%s] peak memory usage: %s Mb\n",
      date('Y-m-d H:i:s'),
       (memory_get_peak_usage(true)/1024/1024))
      ,Console::FG_GREY);
  }

  public function actionSuc(){
    $this->i2_gman_func = 'i2_auto_suc';
    $w=new \GearmanWorker;
    $w->addServers($this->module->gman_server);
    $w->addFunction('kepco_work_suc',function($job){
      $workload=Json::decode($job->workload());
      $this->stdout2("kepco> %g{$workload['no']}%n %r{$workload['name']} %g{$workload['id']}%n\n");      
			$cookie=$this->module->redis_get('kepco.cookie');
			$token=$this->module->redis_get('kepco.token');

			if(preg_match('/^\d{10}$/',$workload['no'],$m)){
				$old_notinum=substr($workload['no'],0,4).'-'.substr($workload['no'],4);
			}else{
				$old_notinum=substr($workload['no'],0,3).'-'.substr($workload['no'],3,2).'-'.substr($workload['no'],5);
			}
        
      $notinum = $workload['no'].'-'.$workload['revision'];

			$worker=new SucWorker([
			 'id'=>$workload['id'],
			 'cookie'=>$cookie,
			 'token'=>$token,
			]);

      $data =$worker->run();

      $bidkey = BidKey::find() -> where("notinum='{$notinum}' or notinum='{$old_notinum}'")
      ->andWhere(['whereis'=>'03'])
      ->orderBy('bidid desc')
      ->limit(1)->one();

      if($bidkey===null) return;
			
			$this->stdout(" %yNEW%n\n");

      $data['notinum'] = $bidkey['notinum'];
      $data['constnm'] = $bidkey['constnm'];
      $data['bidid'] = $bidkey['bidid'];

      $this->stdout("%g> do {$this->i2_gman_func} {$data['bidid']} {$data['bidproc']}%n\n");
      $this->module->gman_do($this->i2_gman_func,Json::encode($data));


//      $c=new \GearmanClient;
//      $c->addServers('127.0.0.1');
//      $c->doNormal($this->i2_gman_func,Json::encode($data));
    
     // print_r($data);
      sleep(1);
    });
      while($w->work());
  }
}

