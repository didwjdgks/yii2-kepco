<?php
namespace kepco\workers;

class SucWorker extends Worker
{
  const URL ='/router';

  public $post_data;
  public function work(){
    $this->post_data = [
     '0'=>[
      'action'=>'smartsuit.ui.etnajs.pro.rfx.sp.OpenInfoDataDetailController',
      'data'=>[
           [
       '0'=>'71eca1e8-5402-4315-8e93-275e4393cbc9',
           ],
         ],
        'method'=>'findOpenInfoDataAttendGrid',
        'tid'=>'27',
        'type'=>'rpc',

       ],
    ];
    $res=$this->post(static::URL,[
      'json'=>$this->post_data,
    ]);
    $rows =$res[0]['result'];
    foreach($rows as $row){
      print_r ($row);
    }

  }

}
