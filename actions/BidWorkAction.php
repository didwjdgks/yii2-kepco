<?php
namespace kepco\actions;

use yii\helpers\Console;
use yii\helpers\Json;

use kepco\models\BidKey;
use kepco\workers\BidWorker;

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
    $w->addFunction('kepco_work_bid',function($job){
      try{
        $workload=Json::decode($job->workload());
        $this->stdout("한전입찰> [worker] {$workload['notinum']} {$workload['revision']} {$workload['constnm']} ({$workload['id']})\n");

        $cookie=$this->module->redis_get('kepco.cookie');
        $token=$this->module->redis_get('kepco.token');
        $bid=new BidWorker([
          'id'=>$workload['id'],
          'cookie'=>$cookie,
          'token'=>$token,
        ]);
        $data=$bid->run();

        switch($workload['noticeType']){
        case 'Cancel':
          $this->bid_c($data);
          break;
        case 'New':
        case 'OnceMore':
        case 'Correct':
          if($workload['revision']>1){
            $this->bid_m($data);
          }
          else if($workload['resultState']=='Cancel'){
            $this->bid_c($data);
          }
          else{
            $this->bid_b($data);
          }
          break;
        default:
          $this->stdout("%r > unknown noticeType : {$workload['noticeType']}%n\n");
        }

        $this->module->publish('kepco-login','ok');
      }
      catch(\Exception $e){
        $this->stdout("%r$e%n\n");
        \Yii::error($e,'kepco');
      }

      $this->module->db->close();
      $this->memory_usage();
      sleep(1);
    });
    while($w->work());
  }

  public function bid_c($data){
    list($noti,$revision)=explode('-',$data['notinum']);
    $query=BidKey::find()->where([
      'whereis'=>'03',
    ])->andWhere("notinum like '{$noti}%'");
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

  public function bid_m($data){
    list($noti,$revision)=explode('-',$data['notinum']);
    $query=BidKey::find()->where([
      'whereis'=>'03',
    ])->andWhere("notinum like '{$noti}%'");
    $bidkey=$query->orderBy('bidid desc')->limit(1)->one();
    if($bidkey!==null){
      list($noti_p,$revision_p)=explode('-',$bidkey->notinum);
      if($revision_p<$revision){
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
    }
  }

  public function bid_b($data){
    $query=BidKey::find()->where([
      'whereis'=>'03',
      'notinum'=>$data['notinum'],
    ]);
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

