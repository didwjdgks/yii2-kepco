<?php
namespace kepco\controllers;

use yii\helpers\Console;
use yii\helpers\Json;
use yii\helpers\ArrayHelper;

use kepco\watchers\BidWatcher;
use kepco\watchers\SucWatcher;

use kepco\models\BidKey;

class WatchController extends \yii\console\Controller
{

	public function stdout2($string){
    $this->stdout(Console::renderColoredString($string));
  }

  public function memory_usage(){
    $this->stdout(sprintf("[%s] Peak memory usage: %s Mb\n",
      date('Y-m-d H:i:s'),
      (memory_get_peak_usage(true)/1024/1024))
    ,Console::FG_GREY);
  }

  public function actions(){
    return [
      'suc'=>'kepco\actions\SucWatchAction',
    ];
  }

  public function actionBid(){
    while(true){
      try{
        $cookie=$this->module->redis_get('kepco.cookie');
        $token=$this->module->redis_get('kepco.token');
        $bid=new BidWatcher([
          'cookie'=>$cookie,
          'token'=>$token,
        ]);
        $bid->on('kepco-login',function($event){
            $this->stdout2(" > %g로그인을 요청합니다.%n\n");
            $tihs->module->gman_talk("로그인을 요청합니다. 확인하십시요.",[
              142, //송치문
              149, //양정한
              150, //이광용
            ]);
        });

        $bid->watch(function($row){
          $this->stdout2("한전입찰> [watcher] {$row['no']} {$row['revision']} {$row['name']}\n");
          $this->stdout2(" > noticeType:{$row['noticeType']},resultState:{$row['resultState']},progressState:{$row['progressState']}\n");
          $notinum=$row['no'];
          
          if(preg_match('/([A-Z0-9]{3})(\d{2})(\d{5})/',$notinum,$m)){
            $old_notinum=$m[1].'-'.$m[2].'-'.$m[3];
          }
          
          if($row['progressState']=='Close' || $row['progressState']=='OpenTimed' || $row['progressState']=='Fail'
            || ($row['progressState']=='Final' && $row['resultState']=='Success')
            || ($row['progressState']=='Final' && $row['resultState']=='Fail')
            || ($row['progressState']=='Final' && $row['resultState']=='FailPrivate')
            || ($row['progressState']=='Final' && $row['resultState']=='FailReRfx')
            || ($row['progressState']=='Final' && $row['resultState']=='NotDetermined')
          ){
            return;
          }

          $row['notinum']=$row['no'];
          $row['constnm']=$row['name'];

          $notinum=$notinum.'-'.$row['revision'];
          $bidkey=BidKey::find()->where("notinum='{$notinum}' or notinum='{$old_notinum}'")
            ->andWhere(['whereis'=>'03'])
            ->orderBy('bidid desc')
            ->limit(1)->one();
          if($bidkey!==null){
            if($row['resultState']==='Cancel' and $bidkey->bidproc!=='C'){
              $this->stdout2("%g > 취소공고 입력을 요청합니다.%n\n");
              $this->module->gman_do('kepco_work_bid',$row);
            }
            return;
          }

          $this->stdout2("%g > 신규공고 입력을 요청합니다.%n\n");
          $this->module->gman_do('kepco_work_bid',$row);
          sleep(1);
        }); // end watch()
      }
      catch(\Exception $e){
        $this->stdout("$e\n",Console::FG_RED);
        \Yii::error($e,'kepco');
      }

      $this->module->db->close();
      $this->memory_usage();
      sleep(1);
    }
  }
}

