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

  public function stdout2($string){
    $this->stdout(Console::renderColoredString($string));
  }

  public function actionBid(){
    $this->i2_gman_func = 'i2_auto_bid';
    $w=new \GearmanWorker;
    $w->addServers('127.0.0.1');
    $w->addFunction('kepco_work_bid',function($job){
      $workload=Json::decode($job->workload());
      $this->stdout2("kepco> %g{$workload['notinum']}%n {$workload['constnm']} {$workload['id']}\n");
      $worker=new BidWorker([
        'id'=>$workload['id'],
      ]);
      $data=$worker->run();

			print_r($data);
			if(strpos($data['constnm'],'[모의입찰]')!==false)	return;
			if(strpos($data['constnm'],'용역')!==false){
				$data['bidtype']	= 'ser';
				$data['bidview']	= 'ser';
			}

			list($noti,$chasu)=explode('-',$data['notinum']);
      if($workload['noticeType']=='Cancel'){        
				$query=BidKey::find()->where([
          'whereis'=>'03',
          'notinum'=>$noti,
        ]);
        
        $bidkey=$query->orderBy('bidid desc')->limit(1)->one();
        if($bidkey!==null and $bidkey->bidproc!='C' and $bidkey->orgcode_y<$workload['revision']){
          list($a,$b,$c,$d)=explode('-',$bidkey->bidid);
          $b=sprintf('%02s',intval($b)+1);
          $data['bidid']="$a-$b-$c-$d";
          $data['bidproc']='C';

					$c=new \GearmanClient;
					$c->addServers('127.0.0.1');
					$c->doNormal($this->i2_gman_func,Json::encode($data));

          $this->stdout2("   %g>>> do {$this->i2_gman_func} {$data['bidid']} {$data['bidproc']}%n\n");
        }
      }
      else if($workload['noticeType']=='New' or $workload['noticeType']=='OnceMore' or $workload['noticeType']=='Correct'){
        $query=BidKey::find()->where([
          'whereis'=>'03',
          'notinum'=>$noti,
        ]);     
        $bidkey=$query->orderBy('bidid desc')->limit(1)->one();
        if($bidkey===null){
          //new          
          $data['bidid']=sprintf('%s%s-00-00-01',date('ymdHis'),str_pad(mt_rand(0,999),3,'0',STR_PAD_LEFT));
          $data['bidproc']='B';
					echo "---------------------------------";
					//print_r($data);
					$c=new \GearmanClient;
					$c->addServers('127.0.0.1');
					$c->doNormal($this->i2_gman_func,Json::encode($data));
          $this->stdout2("   %g>>> do {$this->i2_gman_func} {$data['bidid']} {$data['bidproc']}%n\n");
        }else{
          if($workload['revision']>1 and $bidkey->orgcode_y!=$workload['revision']){ //정정공고
            list($a,$b,$c,$d)=explode('-',$bidkey->bidid);
            $b=sprintf('%02s',intval($b)+1);
            $data['bidid']="$a-$b-$c-$d";
            $data['bidproc']='M';
            
						$c=new \GearmanClient;
						$c->addServers('127.0.0.1');
						$c->doNormal($this->i2_gman_func,Json::encode($data));
            $this->stdout2(" %g> do {$this->i2_gman_func} {$data['bidid']} {$data['bidproc']}%n\n");
          }
        }
      }

      //print_r($data);
      sleep(1);
    });
    while($w->work());
  }
  public function actionSuc(){
    $this->i2_gman_func = 'i2_auto_suc';
    $w=new \GearmanWorker;
    $w->addServers('127.0.0.1');
    $w->addFunction('kepco_work_suc',function($job){
      $workload=Json::decode($job->workload());
      $this->stdout2("kepco> %g{$workload['notinum']}%n %r{$workload['constnm']} \n");
      $worker=new SucWorker([
        'id'=>$workload['id'],
      ]);
      $data =$worker->run();
      $bidkey = BidKey::find() -> where("notinum='{$workload['notinum']}'")
      ->andWhere(['whereis'=>'03'])
      ->orderBy('bidid desc')
      ->limit(1)->one();
      if($bidkey===null) return;
      $data['notinum'] = $bidkey['notinum'];
      $data['constnm'] = $bidkey['constnm'];
      $data['bidid'] = $bidkey['bidid'];

      $c=new \GearmanClient;
      $c->addServers('127.0.0.1');
      $c->doNormal($this->i2_gman_func,Json::encode($data));
      print_r($data);
      sleep(1);
    });
      while($w->work());
  }
}

