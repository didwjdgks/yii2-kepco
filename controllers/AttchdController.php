<?php
namespace kepco\controllers;

use yii\helpers\Json;
use yii\helpers\Console;

use kepco\workers\FileWorker;

class AttchdController extends \yii\console\Controller
{
  public $gman_fileconv;

  public function init(){
    parent::init();
    $this->gman_fileconv=new \GearmanClient;
    $this->gman_fileconv->addServers('115.68.48.231');
  }

  public function actionIndex(){
    $w=new \GearmanWorker();
    $w->addServers($this->module->gman_server);
    $w->addFunction('kepco_file_download',function($job){
      $workload=Json::decode($job->workload());
      $bidid=$workload['bidid'];
      $attchd_lnk=$workload['attchd_lnk'];

      $this->stdout("한전파일> $bidid \n",Console::FG_GREEN);

      try{
        $saveDir="/home/info21c/data/kepco/".substr($bidid,0,4)."/{$bidid}";
        @mkdir($saveDir,0777,true);

        $cookie=$this->module->redis_get('kepco.cookie');
        $token=$this->module->redis_get('kepco.token');

        $downinfo=explode('|',$attchd_lnk);
        foreach($downinfo as $info){
          $this->stdout(" > $info\n");
          list($name,$url)=explode('#',$info);

          $savePath=$saveDir.'/'.$name;
          $cmd="wget -q -T 30 --header 'Cookie: $cookie' --header \"X-CSRF-TOKEN: $token\"  --header 'Accept-Encoding: gzip' -O - '$url' | gunzip > \"$savePath\"";
          //echo $cmd,PHP_EOL;
          $res=exec($cmd);
        }

        $this->gman_fileconv->doBackground('fileconv',$bidid);
      }
      catch(\Exception $e){
        $this->stdout("$e\n",Console::FG_RED);
        \Yii::error($e,'kepco');
      }
      $this->stdout(sprintf("[%s] Peak memory usage: %s Mb\n",
        date('Y-m-d H:i:s'),
        (memory_get_peak_usage(true)/1024/1024))
        ,Console::FG_GREY);
      sleep(1);
    });
    while($w->work());
  }
}

