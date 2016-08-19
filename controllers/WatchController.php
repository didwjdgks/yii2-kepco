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
            $this->module->gman_talk("로그인을 요청합니다. 확인하십시요.",[
              142, //송치문
              149, //양정한
              150, //이광용
            ]);
        });

        $bid->watch(function($row){
          $this->stdout2("한전입찰> %g[watcher]%n {$row['no']} {$row['revision']} {$row['name']}");
          $notinum=$row['no'];
          
          if($row['progressState']=='Close' || $row['progressState']=='OpenTimed' || $row['progressState']=='Fail'
            || ($row['progressState']=='Final' && $row['resultState']=='Success')
            || ($row['progressState']=='Final' && $row['resultState']=='Fail')
            || ($row['progressState']=='Final' && $row['resultState']=='FailPrivate')
            || ($row['progressState']=='Final' && $row['resultState']=='FailReRfx')
            || ($row['progressState']=='Final' && $row['resultState']=='NotDetermined')
          ){
            $this->stdout("\n");
            return;
          }

          if(preg_match('/^\d{10}$/',$row['no'],$m)){
            $old_notinum=substr($row['no'],0,4).'-'.substr($row['no'],4);
          }else{
            $old_notinum=substr($row['no'],0,3).'-'.substr($row['no'],3,2).'-'.substr($row['no'],5);
          }

          $bidkey=BidKey::find()->where("notinum like '{$notinum}%' or notinum like '{$old_notinum}%'")
            ->andWhere(['whereis'=>'03'])
            ->orderBy('bidid desc')
            ->limit(1)->one();

          if($bidkey!==null){
            if(($row['resultState']==='Cancel' or $row['noticeType']==='Cancel') and $bidkey->bidproc!=='C'){
              $this->stdout2("\n%g > 취소공고 입력을 요청합니다.%n\n");
              $this->module->gman_do('kepco_work_bid',$row);
              return;
            }
            if(($row['revision']>1 or $row['noticeType']==='Correct') and $row['resultState']!=='Cancel'){
              $p_notinum=str_replace('-','',$bidkey->notinum);
              if(strlen($p_notinum)===10) $p_revision=1;
              else $p_revision=substr($p_notinum,10);
              if($p_revision<$row['revision']){
                $this->stdout2(" %yMODIFY%n\n");
                $this->module->gman_do('kepco_work_bid',$row);
                return;
              }
            }
            $this->stdout("\n");

            //입찰마감비교
            $endDateTime=strtotime($row['endDateTime']);
            $closedt=strtotime($bidkey->closedt);
            if($closedt!=$endDateTime){
              $this->stdout2("%y > {$row['endDateTime']} : {$bidkey->closedt}%n\n");
            }

            return;
          }

          if($row['resultState']==='Cancel' or $row['noticeType']==='Cancel'){
            $this->stdout2("\n%4 > 취소 전 공고가 없습니다.%n\n");
            return;
          }

          $this->stdout2(" %yNEW%n\n");
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

