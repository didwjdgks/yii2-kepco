<?php
namespace kepco\workers;

use yii\helpers\Json;

class SucWorker2 extends Worker
{
  public $id;

  public function run(){
    $data=null;

    $json_data=[
      [ 'action'=>'smartsuit.ui.etnajs.pro.rfx.sp.BidDetailController',
        'method'=>'findAttendInfo',
        'tid'=>31,
        'type'=>'rpc',
        'data'=>[$this->id],
      ],
    ];
    $res=$this->post('/router',['json'=>$json_data]);
    $findAttendInfo=$res[0]['result'];
    //print_r($findAttendInfo);

    $data['notinum']=$findAttendInfo['bidNo'].'-'.$findAttendInfo['bidRevision'];

    switch($findAttendInfo['resultState']){
    case 'Fail':
    case 'FailPrivate':
      $data['officenm1']='유찰';
      $data['bidproc']='F';
      return $data;
    }

    $data['yega']=$findAttendInfo['estimatedAmount'];

    $json_data=[
      [ 'action'=>'smartsuit.ui.etnajs.pro.rfx.sp.BidDetailController',
        'method'=>'findByIdBidMultiEstimatedPriceSPList',
        'tid'=>29,
        'type'=>'rpc',
        'data'=>[[
          'bidId'=>$this->id,
          'limit'=>100,
          'page'=>1,
          'start'=>0,
          'type'=>'select',
        ]],
      ],
      [ 'action'=>'smartsuit.ui.etnajs.pro.rfx.sp.BidDetailController',
        'method'=>'findByIdBidMultiEstimatedPriceSPList',
        'tid'=>30,
        'type'=>'rpc',
        'data'=>[[
          'bidId'=>$this->id,
          'limit'=>100,
          'page'=>1,
          'start'=>0,
          'type'=>'noneSelect',
        ]],
      ],
    ];

    $res=$this->post('/router',['json'=>$json_data]);
    foreach($res as $row){
      switch($row['tid']){
      case 29: $selectRows=$row['result']; break;
      case 30: $noneSelectRows=$row['result']; break;
      }
    }
    if(!empty($selectRows) and !empty($noneSelectRows)){
      //print_r($selectRows);
      $multispares=[];
      if(is_array($selectRows)){
        $selms=[];
        foreach($selectRows as $row){
          $selms[]=$row['no'];
          $multispares[$row['no']]=$row['price'];
        }
        sort($selms);
        $data['selms']=join('|',$selms);
      }
      //print_r($noneSelectRows);
      if(is_array($noneSelectRows)){
        foreach($noneSelectRows as $row){
          $multispares[$row['no']]=$row['price'];
        }
      }
      ksort($multispares);
      $data['multispare']=join('|',$multispares);
    }

    $json_data=[
      [ 'action'=>'smartsuit.ui.etnajs.pro.rfx.sp.BidDetailController',
        'method'=>'findAttendList',
        'tid'=>34,
        'type'=>'rpc',
        'data'=>[[
          'bidId'=>$this->id,
          'limit'=>100,
          'page'=>1,
          'start'=>0,
        ]],
      ],
    ];
    $res=$this->post('/router',['json'=>$json_data]);
    $findAttendList=$res[0]['result'];
    //print_r($findAttendList);
    $plus=[];
    $minus=[];
    foreach($findAttendList as $i=>$row){
      $seq=$i+1;
      $succom=[
        'seq'=>$seq,
        'officeno'=>$row['vendorRegistrationNo'],
        'officenm'=>$row['vendorName'],
        'prenm'=>$row['vendorPresidentName'],
        'success'=>$row['attendAmount'],
        'pct'=>$row['qualifiedRate'],
      ];
      if($row['ranking']==1){
        $data['officeno1']=$row['vendorRegistrationNo'];
        $data['officenm1']=$row['vendorName'];
        $data['prenm1']=$row['vendorPresidentName'];
        $data['success1']=$row['attendAmount'];
      }
      $data['succoms'][$seq]=$succom;
      if(isset($data['officeno1'])){
        $plus[]=$seq;
      }else{
        $minus[]=$seq;
      }
    }

    $i=1;
    foreach($plus as $seq){
      $data['succoms'][$seq]['rank']=$i;
      $i++;
    }
    $i=count($minus)*-1;
    foreach($minus as $seq){
      $data['succoms'][$seq]['rank']=$i;
      $i++;
    }
    $data['innum']=count($data['succoms']);
    $data['bidproc']='S';

    return $data;
  }
}

