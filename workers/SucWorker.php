<?php
namespace kepco\workers;

use yii\helpers\Json;
use kepco\models\BidKey;

class SucWorker extends Worker
{
  public $id;
  public $alldata;

  public function run(){
    $post_data =[
        [
          'action'=>'smartsuit.ui.etnajs.pro.rfx.sp.BidDetailController',
          'data'=>[$this->id],
          'method'=>'findBidBasicInfo',
          'tid'=>35,
          'type'=>'rpc',
        ]
    ];

    $res=$this->post('/router',['json'=>$post_data]);
    foreach($res as $row){
			$bidtype = $row['result']['purchaseType'];
      $this->alldata = $row['result'];
      $result = $row['result']['resultState'];
    }
    if($bidtype =='ConstructionService' && $result=='Success' ){
			$post_data=[
        [ 
					'action'=>'smartsuit.ui.etnajs.pro.rfx.sp.OpenInfoDataDetailController',
					'data'=>[$this->id],
					'method'=>'findOpenInfoDataAttendGrid',
					'tid'=>30,
					'type'=>'rpc',
				],
      ];
    }
		elseif($bidtype =='Product' && $result =='Success'){
			$post_data=[
				[
					'action'=>'smartsuit.ui.etnajs.pro.rfx.sp.OpenInfoDataDetailController',
					'method'=>'findOpenInfoDataDetail',
					'data'=>[$this->alldata],
					'tid'=>20,
					'type'=>'rpc'
				],
			];
		}
		elseif($result =='Fail'){                                                      //유찰
			$data['officenm1']		= '유찰';
			$data['bidproc']			='F';
      $data['nbidcomment']	= $this->alldata['failReason'];
      $data['notinum']			= $this->alldata['no'];
      $data['revision']			= $this->alldata['revision'];			
			return $data;
		}
		elseif($result =='FailPrivate'){                                               //유찰.수의계약
			$data['officenm1']		='유찰';
			$data['bidproc']			= 'F';
      $data['nbidcomment']	= $this->alldata['failReason'];
      $data['notinum']			= $this->alldata['no'];
      $data['revision']			= $this->alldata['revision'];			
			return $data;
		}
		elseif($result =='FailReRfx' or $result =='ReRFX'){                                                 //유찰.재공고
			$data['nbidcomment']	= $this->alldata['failReason'];
      $data['bidproc']			='F';
      $data['notinum']			= $this->alldata['no'];
      $data['revision']			= $this->alldata['revision'];			
			print_r("유찰/재공고");
			return $data;
		}
		elseif($result =='NotDetermined'){                                             //유찰
			$data['nbidcomment']	= $this->alldata['failReason'];
      $data['bidproc']			= 'F';
      $data['notinum']			= $this->alldata['no'];
      $data['revision']			= $this->alldata['revision'];			
			print_r("유찰");
			return $data;
		}
     
		$res=$this->post('/router',['json'=>$post_data]);
		$res = $res[0]['result'];
		$k = 0;
		$j =0;
		
		foreach($res as $row){                                                           //값넣어주기
			$succom['prenm'][] = $row['representativeName'];
			$succom['officeno'][] = $row['vendorRegistrationNo'];
			$succom['officenm'][] = $row['vendorName'];
			$succom['success'][] = $row['attendAmount'];
			$succom['pct'][] =$row['attendRate'];
			$succom['etc'][] = $row['note'];
		}

    $size = count($succom['officenm'])-1;
		for($i=0; $i<=$size; $i++){                                                    //정리
			$data[$i]['prenm'] = $succom['prenm'][$i];
			$data[$i]['officenm'] = $succom['officenm'][$i];
			$data[$i]['officeno'] = $succom['officeno'][$i];
			$data[$i]['success'] = preg_replace('/[^0-9]*/s','',$succom['success'][$i]).'.00';
			$data[$i]['pct'] = $succom['pct'][$i];
			$data[$i]['etc'] = $succom['etc'][$i];
			if($data[$i]['etc'] =='낙찰'){
				$data[$i]['rank'] = 1;	
			}
			if($data[$i]['pct'] == ''){                                                 //투찰율이 있는것과 없는것 나눔
				$a_arr[$j] =  $data[$i];
				$j = $j + 1;
			}		 
			else if($data[$i]['pct'] != ''){
				$b_arr[$k] = $data[$i];
				$k = $k + 1;
			}
		}

		$pct;
		foreach($data as $row){                                                      //낙찰투찰율 기준
			if($row['etc'] == '낙찰'){
				$data['res']['prenm1'] = $row['prenm'];
				$data['res']['officenm1'] = $row['officenm'];
				$data['res']['officeno1'] = $row['officeno'];
				$data['res']['success1'] = $row['success'];
				$pct = $row['pct'];
			}
		}
		$k = 0;
		$j = 0;
		foreach($b_arr as $row){                                                         //투찰율기준으로 하한율과 초과를 나눔
			if($pct - $row['pct'] > 0){
				$min_arr[$k] = $row;
				$k = $k+1;
			}
			else{
				$plu_arr[$j] = $row;
				$j = $j+1;
			}
		}
		$rank = 1;
		$jsize = count($plu_arr)-1;
		for($i=0; $i<=$jsize; $i++){                                                   //초과했을경우
			$plu_arr[$i]['rank'] = $rank + $i;
			$tot_arr[$i] = $plu_arr[$i];
    }
   /* 
		$asize = count($a_arr)-1;                                                      //투찰율없는경우 뒤에 붙혀줌
		for($i=0; $i<=$asize; $i++){
			if ($i==0){
				$rank = $jsize+2;
				$jsize = $jsize+1;
			}
			$a_arr[$i]['rank'] = $rank + $i;
			$tot_arr[$jsize+$i] = $a_arr[$i];
    }
 */
		$rank = -1;
		$ksize = count($min_arr)-1;
		for($i=0; $i<=$ksize; $i++){                                                   //하한율 -랭크
      if($i==0){
        $jsize = $jsize+1;
/*  
				if($asize < 0){
					$jsize = $jsize +2;
				}
				else{
					$jsize = $jsize +1;
       	}
 */  
      }
      
			$min_arr[$i]['rank'] = $rank - $i;
      //			$tot_arr[$jsize+$asize+$i] = $min_arr[$i];
      $tot_arr[$jsize+$i] = $min_arr[$i];
    }

    $asize = count($a_arr)-1;                                                     //투찰율없는경우 -로 바꿈
    $rank = -$ksize -2;
    for($i=0; $i<=$asize; $i++){
      if($i==0){
        if($ksize <0){
          $jsize = $jsize +2;
        }
        else{
          $jsize = $jsize +1;
        }
      }
      $a_arr[$i]['rank'] = $rank - $i;
      $tot_arr[$jsize+$ksize+$i] = $a_arr[$i];
    }

 //      print_r($plu_arr);
//       print_r($a_arr);
//       print_r($min_arr);
       //      print_r($tot_arr);
		$seq = 1;
		for($i=0; $i<=count($tot_arr)-1; $i++){
			$tot_arr[$i]['seq'] = $seq+$i;
		}
		$data['res']['innum'] = count($tot_arr);
		$redata = $data['res'];

		$post_data=[                                                                  //예가 추첨된거
			'action'=>'smartsuit.ui.etnajs.pro.rfx.sp.BidDetailController',
			'data'=>[
				[
				'bidId'=>$this->id,
				'limit'=>100,
				'page'=>1,
				'start'=>0,
				'type'=>'select',
				],
			],
			'method'=>'findByIdBidMultiEstimatedPriceSPList',
			'tid'=>70,
			'type'=>'rpc',
		];
		$post_data2=[                                                               //예가추첨안된거
			'action'=>'smartsuit.ui.etnajs.pro.rfx.sp.BidDetailController',               
			'data'=>[
				[
				'bidId'=>$this->id,
				'limit'=>100,
				'page'=>1,
				'start'=>0,
				'type'=>'nonselect',
				 ], 
			 ],
			 'method'=>'findByIdBidMultiEstimatedPriceSPList',
			 'tid'=>32,
			 'type'=>'rpc',
		];
      
		$yega=$this->post('/router',['json'=>$post_data]);
		$multi=$this->post('/router',['json'=>$post_data2]);
		$selms;                                                                     //selms
		$selms_arr;
		$multi_arr;
		$multisp;
		$multispare;                                                               //multispare
		foreach($yega as $row){
			$selms_arr = $row['result'];
		}
		foreach($multi as $row){
			$multisp = $row['result'];
		}

		foreach($selms_arr as $row){
			$selms = $selms."|".$row['no'];
		}
		for($i=0; $i<=3; $i++){
			$no = $selms_arr[$i]['no'];
			$multi_arr[$no] = $selms_arr[$i]['price'];
		}
		for($i=0; $i<=14; $i++){
			$no = $multisp[$i]['no'];
			$multi_arr[$no] = $multisp[$i]['price'];
		}
		for($i=1; $i<=15; $i++){
			$multispare = $multispare."|".$multi_arr[$i];
		}
		$multispare = substr($multispare,1); 
		$selms = substr($selms,1);
		$redata['selms']			= $selms;
		$redata['multispare'] = $multispare;
		$redata['yega']				= $this->alldata['estimatedAmount'].'.00';
		$redata['bidproc']		= 'S';
    $redata['succoms']		= $tot_arr;
    $redata['notinum']		= $this->alldata['no'];
    $redata['revision']		= $this->alldata['revision'];

    unset($redata['rank']);
		return $redata;
	}
}


