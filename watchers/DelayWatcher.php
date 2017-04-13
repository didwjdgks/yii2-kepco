<?php
namespace kepco\watchers;

use yii\helpers\Json;

class DelayWatcher extends Watcher
{
  const URL='/router';

  public $post_data;

  public function watch($callback){
    try{
      $this->post_data=[
        'action'=>'smartsuit.ui.etnajs.pro.rfx.sp.BidListController',
        'data'=>[
          [
            'companyId'=>'ALL',
            'fromNoticeDate'=>date('Y-m-d',strtotime('-20 day')).'T00:00:00',
            'toNoticeDate'=>date('Y-m-d').'T00:00:00',		
            'limit'=>100,
            'page'=>1,
            'start'=>0,
            'totalCount'=>0,
          ],
        ],
        'method'=>'findDelayBidSPList',
        'tid'=>13,
        'type'=>'rpc',
      ];
      $res=$this->post(static::URL,[
        'json'=>$this->post_data,
      ]);

      $total=$res[0]['result']['total'];
      $total_page=ceil($total/100);

      $this->post_data['data'][0]['totalCount']=$total;

      for($page=1;$page<=$total_page;$page++){
        if($page>1){
          $this->post_data['data'][0]['page']=$page;
          $this->post_data['data'][0]['start']+=100;
          $res=$this->post(static::URL,['json'=>$this->post_data]);
        }

        $rows=$res[0]['result']['records'];
        foreach($rows as $row){
          $callback($row);
        }
        $this->sub->publish('kepco-login','ok');
        sleep(20);
      }
    }
    catch(\Exception $e){
      $this->trigger('kepco-login',new \yii\base\Event());

      $this->sub->subscribe(['kepco-login'],function($redis,$chan,$msg){
        $this->sub->close();
      });

      throw $e;
    }
  }
}

