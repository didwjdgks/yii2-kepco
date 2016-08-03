<?php
namespace kepco\workers;

use yii\helpers\Json;

class BidWorker extends Worker
{
  public $id;

  public function run(){
    $post_data=[
      [
        'action'=>'smartsuit.ui.etnajs.pro.rfx.sp.BidDetailController',
        'data'=>[$this->id],
        'method'=>"findBidBasicInfo",
        'tid'=>39,
        'type'=>"rpc",
      ],
      [
        'action'=>'smartsuit.ui.etnajs.pro.rfx.sp.BidDetailController',
        'method'=>'getFileItemList',
        'tid'=>45,
        'type'=>'rpc',
        'data'=>[
          [
            'bidFileType'=>'Bid',
            'bidId'=>$this->id,
            'fileGroupId'=>'ConstructionBidFileGroup',
            'limit'=>100,
            'page'=>1,
            'start'=>0,
            'type'=>'Bid',
          ],
        ],
      ],
    ];
    $res=$this->post('/router',['json'=>$post_data]);
    foreach($res as $row){
      switch($row['method']){
        case 'findBidBasicInfo': $basicInfo=$row['result']; break;
        case 'getFileItemList': $fileList=$row['result']; break;
      }
    }
    if($basicInfo['bidType']=='LowestPrice'){
      $data['succls']='02';
    }
  
    $data['opendt']=date('Y-m-d H:i:s',strtotime($basicInfo['beginDateTime']));
    $data['closedt']=date('Y-m-d H:i:s',strtotime($basicInfo['endDateTime']));

    $files=[];
    foreach($fileList as $file){
      $filename=$file['name'];
      $files[]=$filename;
    }
    $data['attchd_lnk']=join('|',$files);

    return $data;
  }
}
