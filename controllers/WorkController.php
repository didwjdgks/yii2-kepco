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
  //private $i2_func='i2_auto_suc';

  public function stdout2($string){
    $this->stdout(Console::renderColoredString($string));
  }

  public function actions(){
    return [
      'bid'=>'kepco\actions\BidWorkAction',
      'suc2'=>'kepco\actions\Suc2WorkAction',
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
      try{
        $workload=Json::decode($job->workload());
        $this->stdout2("kepco> %g[worker]%n {$workload['no']} {$workload['revision']} {$workload['name']} %g{$workload['id']}%n\n");      
        $cookie=$this->module->redis_get('kepco.cookie');
        $token=$this->module->redis_get('kepco.token');

        if(preg_match('/^\d{10}$/',$workload['no'],$m)){
          $old_noti=substr($workload['no'],0,4).'-'.substr($workload['no'],4);
        }else{
          $old_noti=substr($workload['no'],0,3).'-'.substr($workload['no'],3,2).'-'.substr($workload['no'],5);
        }
          
        $notinum = $workload['no'].'-'.$workload['revision'];

        $worker=new SucWorker([
         'id'=>$workload['id'],
         'cookie'=>$cookie,
         'token'=>$token,
        ]);

        $data =$worker->run();
				
        $notinum = $data['notinum'].'-'.$data['revision'];

        list($noti,$revision)=explode('-',$notinum);
        $bidkey = BidKey::find() -> where("notinum like '{$noti}%' or notinum like '{$old_noti}%'")
        ->andWhere(['whereis'=>'03'])
        ->orderBy('bidid desc')
        ->limit(1)->one();
		
        if($bidkey===null) return;
        if($data['bidproc']===null) return;

        $this->stdout2(" %yNEW%n\n");

        $data['notinum'] = $notinum;
        $data['constnm'] = $bidkey['constnm'];
        $data['bidid'] = $bidkey['bidid'];

        $this->stdout2("%g > do {$this->i2_gman_func} {$data['bidid']} {$data['bidproc']}%n\n");

        $this->module->gman_do($this->i2_gman_func,Json::encode($data));
      }catch(\Exception $e){
        $this->stdout2("%r$e%n\n");
        \Yii::error($e,'kepco');
      }

			$this->module->db->close();
      $this->stdout2(sprintf("[%s] Peak memory usage: %sMb\n",
      date('Y-m-d H:i:s'),
      (memory_get_peak_usage(true)/1024/1024)
    ),Console::FG_GREY);
			
      sleep(1);
    });
      while($w->work());
  }
}

