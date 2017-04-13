<?php
namespace kepco\actions;

use yii\helpers\Console;
use yii\helpers\Json;

use kepco\models\BidKey;
use kepco\workers\BidWorker;
use kepco\workers\BidWorkerPur;

class BidWorkAction extends \yii\base\Action
{
  public $module;

  private $i2_func='i2_auto_bid';

  public function init(){
    parent::init();
    $this->module=$this->controller->module;
  }

  public function stdout($string){
    $this->controller->stdout(Console::renderColoredString($string));
  }

  public function memory_usage(){
    $this->controller->stdout(sprintf("[%s] Peak memory usage: %s Mb\n",
      date('Y-m-d H:i:s'),
      (memory_get_peak_usage(true)/1024/1024))
    ,Console::FG_GREY);
  }

  public function run(){
    $w=new \GearmanWorker;
    $w->addServers($this->module->gman_server);
    //-------------------
    //기초금액
    //-------------------
    $w->addFunction('kepco_work_basic',function($job){
      try{
        $workload=Json::decode($job->workload());
        $this->stdout("%4한전기초금액> {$workload['no']} {$workload['revision']} {$workload['name']}%n\n");
        $cookie=$this->module->redis_get('kepco.cookie');
        $token=$this->module->redis_get('kepco.token');
        if($workload['purchaseType']==='Product'){
          $bid=new BidWorkerPur([
            'id'=>$workload['id'],
            'cookie'=>$cookie,
            'token'=>$token,
          ]);
        }else{
          $bid=new BidWorker([
            'id'=>$workload['id'],
            'cookie'=>$cookie,
            'token'=>$token,
          ]);
        }
        $data=$bid->run();

        list($noti,$revision)=explode('-',$data['notinum']);
        if(preg_match('/^\d{10}$/',$noti,$m)){
          $old_noti=substr($noti,0,4).'-'.substr($noti,4);
        }else{
          $old_noti=substr($noti,0,3).'-'.substr($noti,3,2).'-'.substr($noti,5,5);
        }

        if($data!==null and $data['basic']>0){
          $query=BidKey::find()->where(['whereis'=>'03',])->andWhere("notinum like '{$noti}%' or notinum like '{$old_noti}%'");
					$bidkey=$query->orderBy('bidid desc')->limit(1)->one();					
          if($bidkey!==null){
            if(empty($bidkey->basic) or $bidkey->basic==0){
              $this->stdout(" 한전기초금액 : {$data['basic']}\n");
              $this->module->gman_do('i2_auto_basic',[
                'bidid'=>$bidkey->bidid,
                'basic'=>$data['basic'],
              ]);
            }
          }
        }
      }catch(\Exception $e){
        $this->stdout("%r$e%n\n");
        \Yii::error($e,'kepco');
      }

      $this->module->db->close();
      $this->memory_usage();
      sleep(10);
    });
    //------------------
    //입찰공고
    //-----------------
    $w->addFunction('kepco_work_bid',function($job){
      try{
        $workload=Json::decode($job->workload());
        $this->stdout("한전입찰> [worker] {$workload['no']} {$workload['revision']} {$workload['name']} ({$workload['purchaseType']})\n");

        $cookie=$this->module->redis_get('kepco.cookie');
        $token=$this->module->redis_get('kepco.token');

        if($workload['purchaseType']==='Product'){
          $bid=new BidWorkerPur([
            'id'=>$workload['id'],
            'cookie'=>$cookie,
            'token'=>$token,
          ]);
          $data=$bid->run();
        }else{
          $bid=new BidWorker([
            'id'=>$workload['id'],
            'cookie'=>$cookie,
            'token'=>$token,
          ]);
          $data=$bid->run();
        }
				
        $this->stdout("%r > location code : {$data['syscode']} {$data['convention']}%n\n");
				//$this->stdout("%r > bid_local : {$data['bid_local']}%n\n");
        print_r($data['bid_local']);				
        //exit;
        if($data!==null and $data['currencyCode']=='KRW'){
          switch($workload['noticeType']){
          case 'Cancel':
            $this->bid_c($data);
            break;
          case 'New':
          case 'OnceMore':
          case 'Postpone':
            if($workload['revision']>1){
              $this->bid_m($data,$workload);
            }
            else if($workload['resultState']=='Cancel'){
              $this->bid_c($data);
            }
            else{
              $this->bid_b($data);
            }
            break;
          case 'Correct':
            $this->bid_m($data,$workload);
            break;
          case 'ReBidding':
            $this->bid_r($data);
            break;
          default:
            $this->stdout("%r > unknown noticeType : {$workload['noticeType']}%n\n");
          }

          $this->module->publish('kepco-login','ok');
        }
      }
      catch(\Exception $e){
        $this->stdout("%r$e%n\n");
        \Yii::error($e,'kepco');
      }

      $this->module->db->close();
      $this->memory_usage();
      sleep(10);
    });
    while($w->work());
  }

  public function bid_r($data){
    list($noti,$revision)=explode('-',$data['notinum']);
    if(preg_match('/^\d{10}$/',$noti,$m)){
      $old_noti=substr($noti,0,4).'-'.substr($noti,4);
    }else{
      $old_noti=substr($noti,0,3).'-'.substr($noti,3,2).'-'.substr($noti,5,5);
    }
    $query=BidKey::find()->where([
      'whereis'=>'03',
    ])->andWhere("notinum like '{$noti}%' or notinum like '{$old_noti}%'");
    $bidkey=$query->orderBy('bidid desc')->limit(1)->one();
    if($bidkey!==null){
      list($a,$b,$c,$d)=explode('-',$bidkey->bidid);
      if($data['bidRevision']>intval($c)){
        $b=sprintf('%02s',intval($b)+1);
        $c=sprintf('%02s',intval($data['bidRevision']));
				$data['previd']=$bidkey->bidid;
        $data['bidid']="$a-$b-$c-$d";
        $data['bidproc']='R';
        $data['constnm']=$data['constnm'].'//재투찰';
        $this->stdout("%g > do {$this->i2_func} {$data['bidid']} {$data['bidproc']}%n\n");
        $this->module->gman_do($this->i2_func,Json::encode($data));

        if(!empty($data['attchd_lnk'])){
          $this->module->gman_doBack('kepco_file_download',[
            'bidid'=>$data['bidid'],
            'attchd_lnk'=>$data['attchd_lnk'],
          ]);
        }
      }
    }
  }

  public function bid_c($data){
    list($noti,$revision)=explode('-',$data['notinum']);
    if(preg_match('/^\d{10}$/',$noti,$m)){
      $old_noti=substr($noti,0,4).'-'.substr($noti,4);
    }else{
      $old_noti=substr($noti,0,3).'-'.substr($noti,3,2).'-'.substr($noti,5,5);
    }
    $query=BidKey::find()->where([
      'whereis'=>'03',
    ])->andWhere("notinum like '{$noti}%' or notinum like '{$old_noti}%'");
    $bidkey=$query->orderBy('bidid desc')->limit(1)->one();
    if($bidkey!==null and $bidkey->bidproc!='C'){
      list($a,$b,$c,$d)=explode('-',$bidkey->bidid);
      $b=sprintf('%02s',intval($b)+1);
      $data['bidid']="$a-$b-$c-$d";
      $data['bidproc']='C';
      $this->stdout(" %g> do {$this->i2_func} {$data['bidid']} {$data['bidproc']}%n\n");
      $this->module->gman_do($this->i2_func,Json::encode($data));
    }
  }

  public function bid_m($data,$workload){
    list($noti,$revision)=explode('-',$data['notinum']);
		$revision=$workload['revision'];
    if(preg_match('/^\d{10}$/',$noti,$m)){
      $old_noti=substr($noti,0,4).'-'.substr($noti,4);
    }else{
      $old_noti=substr($noti,0,3).'-'.substr($noti,3,2).'-'.substr($noti,5,5);
    }
    $query=BidKey::find()->where([
      'whereis'=>'03',
    ])->andWhere("notinum like '{$noti}%' or notinum like '{$old_noti}%'");
    $bidkey=$query->orderBy('bidid desc')->limit(1)->one();
    if($bidkey!==null){
      list($noti_p,$revision_p)=explode('-',$bidkey->notinum);
      if($revision_p<$revision){
        list($a,$b,$c,$d)=explode('-',$bidkey->bidid);
        $b=sprintf('%02s',intval($b)+1);
        $data['bidid']="$a-$b-$c-$d";
        $data['bidproc']='M';
				$data['notinum']=$noti.'-'.$revision;
        $this->stdout("%g > do {$this->i2_func} {$data['bidid']} {$data['bidproc']}%n\n");
        $this->module->gman_do($this->i2_func,Json::encode($data));

        if(!empty($data['attchd_lnk'])){
          $this->module->gman_doBack('kepco_file_download',[
            'bidid'=>$data['bidid'],
            'attchd_lnk'=>$data['attchd_lnk'],
          ]);
        }
      }
    }
    else{
      //정정공고중 차수변화없이 새공고번호로 공고
      if($workload['beforeBidNoticeNo']){
        $query=BidKey::find()->where(['whereis'=>'03'])
          ->andWhere("notinum like '{$workload['beforeBidNoticeNo']}%'");
        $bidkey=$query->orderBy('bidid desc')->limit(1)->one();
        if($bidkey!==null){
          list($a,$b,$c,$d)=explode('-',$bidkey->bidid);
          $b=sprintf('%02s',intval($b)+1);
          $data['bidid']="$a-$b-$c-$d";
          $data['bidproc']='M';
          $this->stdout("%g > do {$this->i2_func} {$data['bidid']} {$data['bidproc']}%n\n");
          $this->module->gman_do($this->i2_func,Json::encode($data));

          if(!empty($data['attchd_lnk'])){
            $this->module->gman_doBack('kepco_file_download',[
              'bidid'=>$data['bidid'],
              'attchd_lnk'=>$data['attchd_lnk'],
            ]);
          }
				}
				/* 정정공고인데 새공고번호로 등록됐지만 원공고가 없을때 처리 */
				else{			
					list($noti,$revision)=explode('-',$data['notinum']);					
					$data['bidid']=sprintf('%s%s-00-00-01',date('ymdHis'),str_pad(mt_rand(0,999),3,'0',STR_PAD_LEFT));
					$data['bidproc']='B';
					$this->stdout("%g > do {$this->i2_func} {$data['bidid']} {$data['bidproc']}%n\n");
					$this->module->gman_do($this->i2_func,Json::encode($data));
					
					if(!empty($data['attchd_lnk'])){
					$this->module->gman_doBack('kepco_file_download',[
						'bidid'=>$data['bidid'],
						'attchd_lnk'=>$data['attchd_lnk'],
					]);
					}					
				}
      }
    }
  }

  public function bid_b($data){
    list($noti,$revision)=explode('-',$data['notinum']);
    if(preg_match('/^\d{10}$/',$noti,$m)){
      $old_noti=substr($noti,0,4).'-'.substr($noti,4);
    }else{
      $old_noti=substr($noti,0,3).'-'.substr($noti,3,2).'-'.substr($noti,5,5);
    }
    $query=BidKey::find()->where([
      'whereis'=>'03',
    ])->andWhere("notinum like '{$noti}%' or notinum like '{$old_noti}%'");
    $bidkey=$query->orderBy('bidid desc')->limit(1)->one();
    if($bidkey===null){
      $data['bidid']=sprintf('%s%s-00-00-01',date('ymdHis'),str_pad(mt_rand(0,999),3,'0',STR_PAD_LEFT));
      $data['bidproc']='B';
      $this->stdout("%g > do {$this->i2_func} {$data['bidid']} {$data['bidproc']}%n\n");
      $this->module->gman_do($this->i2_func,Json::encode($data));

      if(!empty($data['attchd_lnk'])){
        $this->module->gman_doBack('kepco_file_download',[
          'bidid'=>$data['bidid'],
          'attchd_lnk'=>$data['attchd_lnk'],
        ]);
      }
    }
  }
}

