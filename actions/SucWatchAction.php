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
    while(true){
      try{
        $cookie=$this->module->redis_get('kepco.cookie');
        $token=$this->module->redis_get('kepco.token');
        $suc=new SucWatcher([
          'cookie'=>$cookie,
          'token'=>$token,
        ]);
        $suc->on('kepco-login',function($event){
          $this->stdout(" > %g로그인을 요청합니다.%n\n");
          $this->module->gman_talk("로그인을 요청합니다. 확인하십시요.",[
            142, //송치문
            149, //양정한
            150, //이광용
          ]);
        });
        $suc->watch(function($row){          
					$this->stdout("한전낙찰> %g[watcher]%n {$row['no']} {$row['revision']} {$row['name']}");
          
          if(preg_match('/^\d{10}$/',$row['no'],$m)){
            $old_noti=substr($row['no'],0,4).'-'.substr($row['no'],4);
          }else if(preg_match('/^[A-Z]\d{9}$/',$row['no'],$m)){
            $old_noti=substr($row['no'],0,3).'-'.substr($row['no'],3,2).'-'.substr($row['no'],5);
          }else{
            $this->stdout(" %rERROR: {$row['no']}%n\n");
            sleep(5);
            return;
          }
          
					$noti=$row['no'];
					$notinum = $row['no'].'-'.$row['revision'];
          
					$bidkey=BidKey::find() ->where("notinum like '{$noti}%'")						
            ->andWhere(['whereis'=>'03'])
						//->andWhere("bidproc not in ('S','F')")
            ->orderBy('bidid desc')
            ->limit(1)->one();

					$oldBidkey=BidKey::find() ->where("notinum like '{$old_noti}%'")						
            ->andWhere(['whereis'=>'03'])
						//->andWhere("bidproc not in ('S','F')")
            ->orderBy('bidid desc')
            ->limit(1)->one();

          if($bidkey===null or ($bidkey!==null and $oldBidkey!==null and (($bidkey->bidproc=='S' or $bidkey->bidproc=='F') and ($oldBidkey->bidproc!='S' and $oldBidkey->bidproc!='F')))
							or ($bidkey!==null and $oldBidkey===null and ($bidkey->bidproc=='S' or $bidkey->bidproc=='F'))){
            $this->stdout("\n");
            return;
          }

          $this->stdout(" %yNEW%n\n");
          
          $this->module->gman_do('kepco_work_suc',$row);
          sleep(1);
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

