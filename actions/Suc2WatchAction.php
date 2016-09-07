<?php
namespace kepco\actions;

use yii\helpers\Json;
use yii\helpers\Console;

use kepco\models\BidKey;
use kepco\watchers\Suc2Watcher;

class Suc2WatchAction extends \yii\base\Action
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
    while(true){
      try{
        $cookie=$this->module->redis_get('kepco.cookie');
        $token=$this->module->redis_get('kepco.token');
        $suc=new Suc2Watcher([
          'cookie'=>$cookie,
          'token'=>$token,
        ]);
        $suc->on('kepco-login',function($event){
          $this->stdout(" > 로그인을 요청합니다.\n");
        });

        $query=BidKey::find()->where([
          'whereis'=>'03',
          'bidproc'=>'B',
          'state'=>'Y',
          'bidcls'=>'01',
          'succls'=>['01','02','03','04','05','06'],
        ]);
        $date1=date('Y-m-d',strtotime('-15 day'));
        $date2=date('Y-m-d H:i:s');
        $query->andWhere(['between','constdt',$date1,$date2]);
        $rows=$query->all();
        foreach($rows as $row){
          $this->stdout("%2%kkepco> [suc2watcher] {$row['notinum']} {$row['constnm']} {$row['constdt']}%n\n");
          //{old
          if(preg_match('/^[A-Z]\d{2}-\d{2}-\d{5}/',$row['notinum'])){
            list($a,$b,$c)=explode('-',$row['notinum']);
            $no=$a.$b.$c;
          }
          else if(preg_match('/^\d{4}-\d{6}/',$row['notinum'])){
            list($a,$b)=explode('-',$row['notinum']);
            $no=$a.$b;
          }
          //old}
          else{
            list($a,$b)=explode('-',$row['notinum']);
            $no=$a;
          }
          $info=$suc->search($no);
          if($info!==null){
            $this->stdout(" > {$info['progressState']} {$info['resultState']} {$info['no']} {$info['id']}\n");
            switch($info['resultState']){
            case 'Fail':
            case 'FailPrivate':
              $data['bidid']=$row['bidid'];
              $data['officenm1']='유찰';
              $data['bidproc']='F';
              $this->stdout("%y > 유찰 : {$row['bidid']}%n\n");
              $this->module->gman_do('i2_auto_suc',Json::encode($data));
              continue 2;
            }

            switch($info['progressState']){
            case 'Final':
            case 'OpenTimed':
              $this->stdout("%y > 개찰수집 : kepco_work_suc2 %n\n");
              $this->module->gman_do('kepco_work_suc2',Json::encode($info));
            }

          }
          sleep(5);
        }//end foreach
      }
      catch(\Exception $e){
        $this->stdout("%r".$e->getMessage()."%n\n");
        \Yii::error($e,'kepco');
      }
      $this->module->db->close();
      $this->memory_usage();
      sleep(30);
    }
  }
}

