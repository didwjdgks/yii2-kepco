<?php
namespace kepco\workers;

use yii\helpers\Json;
use DateTime;
use kepco\models\BidKey;
use kepco\components\func;

class BidWorkerPur extends Worker
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
      [ 'action'=>'smartsuit.ui.etnajs.pro.rfx.sp.BidDetailController',
        'method'=>'findBidChangeTimeDetailList',
        'tid'=>84,
        'type'=>'rpc',
        'data'=>[
          [ 'bidFileType'=>'Bid',
            'bidId'=>$this->id,
            'fileGroupId'=>'ProductBidFileGroup',
            'limit'=>100,
            'page'=>1,
            'start'=>0,
            'type'=>'Bid',
          ],
        ],
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
            'fileGroupId'=>'ProductBidFileGroup',
            'limit'=>100,
            'page'=>1,
            'start'=>0,
            'type'=>'Bid',
          ],
        ],
      ],
			[
        'action'=>'smartsuit.ui.etnajs.pro.rfx.sp.BidDetailController',        
        'method'=>'findRegionCodeData',
        'tid'=>40,
        'type'=>'rpc',
				'data'=>[
					[	'bidFileType'=>'Bid',
            'bidId'=>$this->id,
            'fileGroupId'=>'ConstructionBidFileGroup',
            'limit'=>100,
            'page'=>1,
            'start'=>0,
            'type'=>'Bid',
					],
				],
      ],
      //품목정보요청
      [ 'action'=>'smartsuit.ui.etnajs.pro.rfx.sp.BidDetailController',
        'method'=>'findBidItems',
        'type'=>'rpc',
        'tid'=>74,
        'data'=>[
          [ 'bidFileType'=>'Bid',
            'bidId'=>$this->id,
            'fileGroupId'=>'ProductBidFileGroup',
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
				case 'findRegionCodeData' : $locationInfo=$row['result']; break;
        case 'findBidChangeTimeDetailList':
          $findBidChangeTimeDetailList=$row['result'];
          break;
        case 'findBidItems':
          $bidItems=$row['result'];
          break;
      }    
    }

    if(empty($basicInfo)){
      return null;
    }

    $json=[
      [ 'action'=>'smartsuit.ui.etnajs.cmmn.CommonController',
        'method'=>'getAttachments',
        'tid'=>159,
        'type'=>'rpc',
        'data'=>[
          [
            'groupId'=>$basicInfo['purchaseSpecAttachFileGroupId'],
            'limit'=>100,
            'page'=>1,
            'start'=>0,
          ],
        ],
      ],
      [ 'action'=>'smartsuit.ui.etnajs.cmmn.CommonController',
        'method'=>'getAttachments',
        'tid'=>160,
        'type'=>'rpc',
        'data'=>[
          [
            'groupId'=>$basicInfo['specialConditionAttachFileGrpId'],
            'limit'=>100,
            'page'=>1,
            'start'=>0,
          ],
        ],
      ],
      [ 'action'=>'smartsuit.ui.etnajs.cmmn.CommonController',
        'method'=>'getAttachments',
        'tid'=>161,
        'type'=>'rpc',
        'data'=>[
          [
            'groupId'=>$basicInfo['etcAttachFileGroupId'],
            'limit'=>100,
            'page'=>1,
            'start'=>0,
          ],
        ],
      ],
    ];
    $res=$this->post('/router',['json'=>$json]);
    $fileList2=[];
    foreach($res as $row){
      switch($row['method']){
      case 'getAttachments':
        if(is_array($row['result'])){
          foreach($row['result'] as $val){
            $fileList2[]=$val;
          }
        }
        break;
      }
    }

		$location = array(
			"전국"		=>	0,												
			"서울"		=>	1,
			"부산"		=>	2,
			"광주"		=>	3,
			"대전"		=>	4,
			"인천"		=>	5,
			"대구"		=>	6,
			"울산"		=>	7,
			"경기"		=>	8,
			"강원"		=>	9,
			"충북"		=>	10,
			"충남"		=>	11,
			"경북"		=>	12,
			"경남"		=>	13,
			"전북"		=>	14,
			"전남"		=>	15,
			"제주"		=>	16,
			"세종"		=>	17,
		);
		
		$bidtype = $basicInfo['itemType'];
		
		//whereis
		$data['whereis']	= '03';											
		
		//syscode
		if($basicInfo['purchaseType']=='ConstructionService'){
			$data['syscode'] = 'KBC';						
			$data['bidtype']	= 'con';
			$data['bidview']	= 'con';
		}else if($basicInfo['purchaseType']=='Product'){
			$data['syscode'] = 'KPBC';
			$data['bidtype']	= 'pur';
			$data['bidview']	= 'pur';						
		}

    $data['notinum'] = $basicInfo['no'].'-'.$basicInfo['revision'];
    $data['bidRevision']=$basicInfo['bidRevision'];

		//통화(달러입찰 USD,원화입찰 KRW)
		$data['currencyCode'] = $basicInfo['currencyCode'];
		
		//chasu
		$data['orgcode_y'] = $basicInfo['revision'];

		//constnm
		$data['constnm'] = $basicInfo['name'];
		
		/*if(strpos($data['constnm'],'용역')!==false){
			$data['bidtype']	= 'ser';
			$data['bidview']	= 'ser';
		}*/

		$company = array(
			"COM01"		=>	"한국전력공사",
			"COM02"		=>	"한국서부발전(주)",
			"COM03"		=>	"한국전력국제원자력대학원대학교",
			"COM04"		=>	"한국남부발전(주)",
			"COM05"		=>	"한국중부발전(주)",
			"COM06"		=>	"한국남동발전(주)",
			"COM08"		=>	"한국동서발전(주)",
			"COM09"		=>	"한국전력기술(주)",
			"COM10"		=>	"한전KPS(주)",
			"COM11"		=>	"한국전력거래소",
			"COM12"		=>	"한전원자력연료(주)",
			"COM14"		=>	"한국발전교육원",
			"COM16"		=>	"한국해상풍력(주)",
			"COM19"		=>	"카페스 주식회사",
		);

		// case 1 : 공고부서 생략(1번째까지 수집) , case 2 : 본부(2번째)까지 수집, case 3 : 사업소 제외후 본부까지 수집,
		// case 4 : 소,처등(3번째까지 수집 단 경영관리본부 일때는 2번째까지 수집)
		// case 1  - 3 9 11 12 14 16 19 , case 2  - 1 4 8 , case 3 - 2 5 6 , case 4 - 10
		$comCase1 = array("COM03","COM09","COM11","COM12","COM14","COM16","COM19");
		$comCase2 = array("COM01","COM04","COM08");
		$comCase3 = array("COM02","COM05","COM06");
		$comCase4 = array("COM10");
		//org
		$data['org'] = $basicInfo['representativeDepartmentName'];

		if(in_array($basicInfo['companyId'],$comCase1)){
			$data['org_i'] = $company[$basicInfo['companyId']];	
		}else if(in_array($basicInfo['companyId'],$comCase2)){
			list($org_i,)=explode(' ',$basicInfo['representativeDepartmentName']);
			$data['org_i'] = $company[$basicInfo['companyId']].' '.$org_i;	
		}else if(in_array($basicInfo['companyId'],$comCase3)){
			$tmp = str_replace("사업소 ","",$basicInfo['representativeDepartmentName']);
			list($org_i,)=explode(' ',$tmp);
			$data['org_i'] = $company[$basicInfo['companyId']].' '.$org_i;			
		}else if(in_array($basicInfo['companyId'],$comCase4)){
			if(strpos($basicInfo['representativeDepartmentName'],'경영관리본부')!==false){
				list($org_i,)=explode(' ',$basicInfo['representativeDepartmentName']);
				$data['org_i'] = $company[$basicInfo['companyId']].' '.$org_i;	
			}else{
				list($org_i1,$org_i2,)=explode(' ',$basicInfo['representativeDepartmentName']);
				$data['org_i'] = $company[$basicInfo['companyId']].' '.$org_i1.' '.$org_i2;	
			}
		}

		//contract
    if($basicInfo['competitionType']=='Limited')	$data['contract'] = '20';
    else if($basicInfo['competitionType']=='Open')	$data['contract'] = '10';

		//bidcls
		if($basicInfo['bidMethod']=='Electronic')	$data['bidcls'] = '01';
		else if($basicInfo['bidMethod']=='Offline')	$data['bidcls'] = '00';		
		
		//succls
    if($basicInfo['bidType']=='LowestPrice')	$data['succls'] = '02';
    else if($basicInfo['bidType']=='QualifiedEval')	$data['succls'] = '01';
		else if($basicInfo['bidType']=='Nego') $data['succls'] = '07';
		else if($basicInfo['bidType']=='LimitedLowestPrice')	$data['succls'] = '03';
		else	$data['succls'] = '00';

		//succls2 - 규격,가격 동시입찰,2단계 동시입찰 일경우 2단계 및 규격분리입찰(06)
		if(strpos($basicInfo['bidTypeCombine'],'규격,가격 동시입찰')!==false)	$data['succls'] = '06';
		if(strpos($basicInfo['bidTypeCombine'],'2단계 동시입찰')!==false)	$data['succls'] = '06';
		//yegatype
		$data['yegatype'] = '25';
		
		//convention
		if($basicInfo['jointSupplyDemandYn'])	$data['convention'] = '2';
		else	$data['convention'] = '0';

		//prsum
		$data['presum'] = $basicInfo['presumedPrice'];
		
		//basic
		$data['basic'] = $basicInfo['estimatedPriceBasicAmount'];
		
		//pct(???)
		$data['pct'] = $basicInfo['winningPriceLimitRate'];

		//charger
		$data['charger'] = $basicInfo['representativeName'].'|'.$basicInfo['representativePhoneNo'];
		//noticedt
		$data['noticedt'] = date('Y-m-d H:i:s',strtotime($basicInfo['noticeDateTime']));		
		
		//registdt
		$data['registdt'] = date('Y-m-d H:i:s',strtotime($basicInfo['bidAttendRequestCloseDateTime']));

		//opendt
    $data['opendt'] = date('Y-m-d H:i:s',strtotime($basicInfo['beginDateTime']));

		//closedt
    $data['closedt'] = date('Y-m-d H:i:s',strtotime($basicInfo['endDateTime']));		
		
		//constdt
    $data['constdt'] = date('Y-m-d H:i:s',strtotime($basicInfo['openBidDateTime']));
		
		//state
		$data['state'] = 'N';

    $data['bid_html'] = "<pre><strong>계약조건 공시장소 : </strong><br><br>".$basicInfo['mailBidDistribution']."<br><br><strong>계약착수일 및 완료일 : </strong><br><br>".$basicInfo['contractBeginEndDate']."<br><br><strong>입찰시 제출서류 : </strong><br><br>".$basicInfo['bidAttendDocument']. "<br><br><strong>입찰참가자격 : </strong><br><br>".$basicInfo['bidAttendRestrict']."<br><br><strong>입찰보증금귀속 : </strong><br><br>".$basicInfo['bidBondBelong']."<br><br><strong>입찰무효사항: </strong><br><br>".$basicInfo['bidNullification']."<br><br><strong>입찰참가신청서류 : </strong><br><br>". $basicInfo['bidAttendRequestDocument']."<br><br><strong>추가정보제공처 : </strong><br><br>".$basicInfo['moreInformation']."<br><br><strong>기타공고사항 : </strong><br><br>".$basicInfo['etc']."</pre>";

    //품목정보
    if(is_array($bidItems)){
      foreach($bidItems as $i=>$item){
        $data['goods'][]=[
          'seq'=>$i+1,
          'gcode'=>$item['itemCode'],
          'gname'=>$item['itemName'],
          'standard'=>$item['itemSpec'],
          'unit'=>$item['itemUnit'],
          'cnt'=>$item['quantity'],
        ];
      }
    }

    $files=[];
    //자동첨부파일
    if(is_array($fileList)){
      foreach($fileList as $file){
        $filename=str_replace('#','-',$file['name']).'#http://srm.kepco.net/printDownloadAttachment.do?id='.$file['id'];
        $files[]=$filename;
      }
    }
    //수동첨부파일
    if(is_array($fileList2)){
      foreach($fileList2 as $file){
        $filename=str_replace('#','-',$file['name']).'#http://srm.kepco.net/downloadAttachment.do?id='.$file['id'];
        $files[]=$filename;
      }
    }
    $data['attchd_lnk']=join('|',$files);

		$sublocal = '';
		if(is_array($locationInfo)){			
			foreach($locationInfo as $loc){				
				if(($data['location']&pow(2,$location[$loc['areaCodeName']]))==0)	$data['location']+=pow(2,$location[$loc['areaCodeName']]);
				
				if($loc['subAreaCodeName']!==null and $loc['subAreaCodeName']!==''){
					if(strpos($sublocal,$loc['subAreaCodeName'])===false){
						$data['bid_local'][]=[
							'name'=>$this->renameLoc($loc['areaCodeName'])." ".$loc['subAreaCodeName'],
							'hname'=>$this->renameLoc($loc['areaCodeName']),
						];

						if($sublocal=='')	$sublocal=trim($loc['subAreaCodeName']);
						else	$sublocal=$sublocal.','.trim($loc['subAreaCodeName']);
						$sublocal=trim($sublocal);
					}
				}
			}			
		}

    return $data;
  }

	public function renameLoc($loc_value){
		$rename = '전국';
		
		if($loc_value=='서울')	$rename='서울특별시';
		else if($loc_value=='부산')	$rename='부산광역시';
		else if($loc_value=='광주')	$rename='광주광역시';
		else if($loc_value=='대전')	$rename='대전광역시';
		else if($loc_value=='인천')	$rename='인천광역시';
		else if($loc_value=='대구')	$rename='대구광역시';
		else if($loc_value=='울산')	$rename='울산광역시';
		else if($loc_value=='경기')	$rename='경기도';
		else if($loc_value=='강원')	$rename='강원도';
		else if($loc_value=='충북')	$rename='충청북도';
		else if($loc_value=='충남')	$rename='충청남도';
		else if($loc_value=='경남')	$rename='경상남도';
		else if($loc_value=='경북')	$rename='경상북도';
		else if($loc_value=='전북')	$rename='전라북도';
		else if($loc_value=='전남')	$rename='전라남도';
		else if($loc_value=='제주')	$rename='제주특별자치도';

		return $rename;
	}
}

