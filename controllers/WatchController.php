<?php
namespace kepco\controllers;

use kepco\watchers\BidWatcher;

class WatchController extends \yii\console\Controller
{
  public function actionIndex(){
    while(true){
      $bid=new BidWatcher;
      $bid->watch();
      sleep(1);
    }
  }
}

