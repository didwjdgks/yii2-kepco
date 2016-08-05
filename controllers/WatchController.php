<?php
namespace kepco\controllers;

use yii\helpers\Console;
use yii\helpers\Json;
use yii\helpers\ArrayHelper;

use kepco\watchers\BidWatcher;
use kepco\watchers\SucWatcher;
use kepco\workers\SucWorker;
use kepco\models\BidKey;

class WatchController extends \yii\console\Controller
{
  public function stdout2($string){
    $this->stdout(Console::renderColoredString($string));
  }

  public function actionBid(){
    while(true){
      $bid=new BidWatcher;
      $bid->watch(function($row){
        $this->stdout2("한전입찰> %g{$row['no']}%n %y{$row['revision']}%n {$row['name']}");
        $notinum=$row['no'];
        if(preg_match('/([A-Z0-9]{3})(\d{2})(\d{5})/',$notinum,$m)){
          $old_notinum=$m[1].'-'.$m[2].'-'.$m[3];
        }
        $bidkey=BidKey::find()->where("notinum='{$notinum}' or notinum='{$old_notinum}'")
          ->andWhere(['whereis'=>'03'])
          ->orderBy('bidid desc')
          ->limit(1)->one();
        if($bidkey===null){
          $this->stdout2(" %yNEW%n\n");
          $client=new \GearmanClient;
          $client->addServers('127.0.0.1');
          $client->doBackground('kepco_work_bid',Json::encode([
            'id'=>$row['id'],
            'notinum'=>$row['no'],
            'revision'=>$row['revision'],
            'constnm'=>$row['name'],
          ]));
          sleep(1);
          return;
        }

        $this->stdout2(" {$bidkey->org_i}\n");
      });
      sleep(1);
    }
  }
  public function actionSuc(){
  while(true){
      $bid =new SucWatcher;
      $bid->watch(function($row){
        $this->stdout2("한전낙찰> %g{$row['no']}%n %y{$row['name']}%n %r{$row['id']}");
        $notinum=$row['no'];
        if(preg_match('/([A-Z0-9]{3})(\d{2})(\d{5})/',$notinum,$m)){
          $old_notinum=$m[1].'-'.$m[2].'-'.$m[3];
        }
        $bidkey=BidKey::find() ->where("notinum='{$notinum}' or notinum='{$old_notinum}'")
          ->andWhere(['whereis'=>'03'])
          ->orderBy('bidid desc')
          ->limit(1)->one();
       if($bidkey===null){
        $this->stdout2(" %yNEW%n\n");
        $client=new \GearmanClient;
        $client->addServers('127.0.0.1');
        $client->doBackground('kepco_work_suc',Json::encode([
          'notinum'=>$row['no'],
          'constnm'=>$row['name'],
          'purchaseType'=>$row['purchaseType'],
          'alldata'=>$row,
          'id'=>$row['id'],
          ]));
          sleep(1);
          return;
        }
        $tihs->stdout2(" {$bidkey->org_i}\n");
      });
      sleep(1);
    }
  }
}

