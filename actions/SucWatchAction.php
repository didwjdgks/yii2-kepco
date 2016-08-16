<?php
namespace kepco\actions;

use yii\helpers\Console;

use kepco\models\BidKey;
use kepco\watchers\SucWatcher;

class SucWatchAction extends \yii\base\Action
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
    $suc=new SucWatcher;
    $suc->on('kepco-login',function($event){
      $this->stdout(" > %g로그인을 요청합니다.%n\n");
    });
    while(true){
      try{
        $suc->watch(function($row){
          $this->stdout("한전낙찰> %g{$row['no']}%n {$row['name']}");
          $notinum=$row['no'];
          if($row['purchaseType']=='ConstructionService'){
            if(preg_match('/([A-Z0-9]{3})(\d{2})(\d{5})/',$notinum,$m)){
              $old_notinum=$m[1].'-'.$m[2].'-'.$m[3];
            }
          }else if($row['purchaseType']=='Product'){
            if(preg_match('/(\d{4})(\d{6})/',$notinum,$m)){
              $old_notinum=$m[1].'-'.$m[2];
            }
          }
          $bidkey=BidKey::find() ->where("notinum='{$notinum}' or notinum='{$old_notinum}'")
            ->andWhere(['whereis'=>'03'])
            ->orderBy('bidid desc')
            ->limit(1)->one();
          if($bidkey!==null){
            $this->stdout("\n");
            return;
          }

          $this->stdout(" %yNEW%n\n");
          sleep(3);
        }); // end watch()
      }
      catch(\Exception $e){
        $this->stdout("%r$e%n\n");
        \Yii::error($e,'kepco');
      }

      $this->module->db->close();
      $this->memory_usage();
      sleep(1);
    }
  }
}

