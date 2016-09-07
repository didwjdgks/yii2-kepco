<?php
namespace kepco\watchers;

use yii\helpers\Json;

class Suc2Watcher extends Watcher
{
  public function search($no){
    try{
      $json_data=[
        'action'=>'smartsuit.ui.etnajs.pro.rfx.sp.BidListController',
        'data'=>[
          [
            'companyId'=>'ALL',
            'fromNoticeDate'=>date('Y-m-d',strtotime('-90 day')).'T00:00:00',
            'toNoticeDate'=>date('Y-m-d').'T00:00:00',
            'no'=>$no,
            'limit'=>30,
            'page'=>1,
            'start'=>0,
            'totalCount'=>0,
          ],
        ],
        'method'=>'findNoticeSPList',
        'tid'=>181,
        'type'=>'rpc',
      ];
      $res=$this->post('/router',['json'=>$json_data]);
      $result=$res[0]['result'];
      if($result['total']==0) return null;

      $row=$result['records'][0];
      return $row;
    }
    catch(\Exception $e){
      $this->trigger('kepco-login',new \yii\base\Event);
      $this->sub->subscribe(['kepco-login'],function($redis,$chan,$msg){
        $this->sub->close();
      });
      throw $e;
    }
  }
}

