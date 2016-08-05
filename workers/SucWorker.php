<?php
namespace kepco\workers;

use yii\helpers\Json;
//use kepco\workers\BidWorker;

class SucWorker extends Worker
{
  public $id;
  public $bInfo;
  public function run(){
    $post_data=[
      [ 
       'action'=>'smartsuit.ui.etnajs.pro.rfx.sp.OpenInfoDataDetailController',
       'data'=>[$this->id],
       'method'=>'findOpenInfoDataAttendGrid',
       'tid'=>30,
       'type'=>'rpc',
      ],
    //  [
    //    'action'=>'smartsuit.ui.etnajs.pro.rfx.sp.BidDetailController',
    //    'method'=>'findBidBasicInfo',
    //    'data'=>[$this->id],
    //    'tid'=>20,
    //    'type'=>'rpc',
     // ],
     ];
      $res=$this->post('/router',['json'=>$post_data]); 
     foreach($res as $row){
      // switch($row['method']){
       //   case 'findOpenInfoDataAttendGrid' : $data=$row['result'];  break;
       //   case 'findBidBasicInfo' : $bInfo =$row['result'];   break;
       //   }
            $rows = $row['result'];
       foreach($rows as $row){
          $succom['prenm'][] = $row['representativeName'];
          $succom['officeno'][] = $row['vendorRegistrationNo'];
          $succom['officenm'][] = $row['vendorName'];
          $succom['success'][] = $row['attendAmount'];
          $succom['pct'][] = $row['attendRate'];
          $succom['etc'][] =$row['note'];
          }
          $size = count($succom['prenm'])-1;
          for($i=0; $i<=$size; $i++){
            $data[$i]['prenm'] = $succom['prenm'][$i];
            $data[$i]['officenm'] = $succom['officenm'][$i];
            $data[$i]['officeno'] = $succom['officeno'][$i];
            $data[$i]['success'] = $succom['succom'][$i];
            $data[$i]['pct'] = $succom['pct'][$i];
            $data[$i]['etc'] = $succom['etc'][$i];
          }
          foreach($data as $row){
            if($row['etc'] == '낙찰'){
              $data_res = $row;
            }
          }
       }
         
     //  if($bInfo != ''){
     //   $post_data=[
     //     [
     //       'action'=>'smartsuit.ui.etnajs.pro.rfx.sp.OpenInfoDataDetailController',
     //       'method'=>'findOpenInfoDataDetail',
     //       'data'=>[$this->bInfo],
     //       'tid'=>21,
     //       'type'=>'rpc',
     //     ],
     //    ];
      //    $res = $this->post('/router',['json'=>$post_data]);
      //    foreach($res as $row){
      //      print_r($row);
      //    }
      // }
       return $data;

     }
}
