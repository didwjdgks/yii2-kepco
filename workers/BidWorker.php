<?php
namespace kepco\workers;

use yii\helpers\Json;
use DateTime;
use kepco\models\BidKey;
use kepco\components\func;

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
			[
        'action'=>'smartsuit.ui.etnajs.pro.rfx.sp.BidDetailController',
        'data'=>[$this->id],
        'method'=>"findLicenseCodeData",
        'tid'=>198,
        'type'=>"rpc",
      ],
      [
        'action'=>'smartsuit.ui.etnajs.cmmn.CommonController',
        'method'=>'getAttachments',
        'tid'=>82,
        'type'=>'rpc',
        'data'=>[
          [
            'groupId'=>$this->id,
            'limit'=>100,
            'page'=>1,
            'start'=>0,
          ],
         ],
      ],
    ];
    $res=$this->post('/router',['json'=>$post_data]);
    foreach($res as $row){
      switch($row['method']){
        case 'findBidBasicInfo': $basicInfo=$row['result']; break;
        case 'getFileItemList': $fileList=$row['result']; break;
        case 'findLicenseCodeData': $licenseInfo=$row['result']; break;
        case 'getAttachments' : $fileList2=$row['result']; break;
      }    
		}
		
		$bidtype = $basicInfo['itemType'];
		
		//whereis
		$data['whereis']	= '03';											
		
		//bidtype,bidview
		if($bidtype=='Construction'){											
			$data['bidtype']	= 'con';
			$data['bidview']	= 'con';
		}else if($bidtype=='Service'){
			$data['bidtype']	= 'ser';
			$data['bidview']	= 'ser';
		}else if($bidtype=='Product'){
			$data['bidtype']	= 'per';
			$data['bidview']	= 'per';
		}		
		
		//syscode
		if($basicInfo['purchaseType']=='ConstructionService'){
			$data['syscode'] = 'KBC';						
			$data['bidtype']	= 'con';
			$data['bidview']	= 'con';
		}else if($basicInfo['purchaseType']=='Product'){
			$data['syscode'] = 'KPBC';
			$data['bidtype']	= 'per';
			$data['bidview']	= 'per';						
		}

		if(preg_match('/([A-Z0-9]{3})(\d{2})(\d{5})/',$basicInfo['no'],$m)){
			$data['old_notinum'] = $m[1].'-'.$m[2].'-'.$m[3];
		}
		$data['notinum'] = $basicInfo['no'].'-'.$basicInfo['revision'];
		
		//chasu
		$data['orgcode_y'] = $basicInfo['revision'];

		//constnm
		$data['constnm'] = $basicInfo['name'];
	
		
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
		//org
		$data['org'] = $basicInfo['representativeDepartmentName'];

		$data['org_i'] = $company[$basicInfo['companyId']].' '.$basicInfo['representativeDepartmentName'];
		
		
		//�����������
	/*	if($basicInfo['noticeType']=='New' || $basicInfo['noticeType']=='OnceMore'){
			$data['bidproc'] = 'B';
			//bidid ����						
			$now = new DateTime();
			$hbidid = substr($now->format('YmdHis'),2);						
			$tbidid = str_pad(mt_rand(0,999),3,'0',STR_PAD_LEFT); // str_pad()�� 3�ڸ� �����
			$data['bidid'] = $hbidid.$tbidid.'-00-00-01';
			
		}
		else if($basicInfo['noticeType']=='Correct'){
			$data['bidproc'] = 'M';
			
			$prev=$bidkey=BidKey::find() ->where("notinum='{$data['notinum']}' or notinum='{$data['old_notinum']}'")
          ->andWhere(['whereis'=>'03'])
          ->orderBy('bidid desc')
          ->limit(1)->one();
			
			$old_bidid = $prev->bidid;
			$data['old_bidid'] = $old_bidid;
			if(preg_match('/([A-Z0-9]{15})-(\d{2})-(\d{2})-(\d{2})/',$old_bidid,$m)){
				$m[2] = (int)$m[2]+1;
				if($m[2] < 10)	$m[2] = '0'.(string)$m[2];
				else $m[2] = (string)$m[2];
				$data['bidid'] = $m[1].'-'.$m[2].'-'.$m[3].'-'.$m[4];
			}
		}
		else if($basicInfo['noticeType']=='Cancel'){
			$data['bidproc'] = 'C';
			$prev=$bidkey=BidKey::find() ->where("notinum='{$data['notinum']}' or notinum='{$data['old_notinum']}'")
          ->andWhere(['whereis'=>'03'])
          ->orderBy('bidid desc')
          ->limit(1)->one();
			
			$old_bidid = $prev->bidid;
			$data['old_bidid'] = $old_bidid;
			if(preg_match('/([A-Z0-9]{15})-(\d{2})-(\d{2})-(\d{2})/',$old_bidid,$m)){
				$m[2] = (int)$m[2]+1;
				if($m[2] < 10)	$m[2] = '0'.(string)$m[2];
				else $m[2] = (string)$m[2];
				$data['bidid'] = $m[1].'-'.$m[2].'-'.$m[3].'-'.$m[4];
			}
		// �������� ó��
		}else if($basicInfo['noticeType']=='ReBidding'){
			$data['bidproc'] = 'R';
			$prev=$bidkey=BidKey::find() ->where("notinum='{$data['notinum']}' or notinum='{$data['old_notinum']}'")
          ->andWhere(['whereis'=>'03'])
          ->orderBy('bidid desc')
          ->limit(1)->one();
			
			$old_bidid = $prev->bidid;
			$data['old_bidid'] = $old_bidid;
			//$old_bidid = '16080111KEP6091-00-00-01';
			if(preg_match('/([A-Z0-9]{15})-(\d{2})-(\d{2})-(\d{2})/',$old_bidid,$m)){
				$m[3] = (int)$m[3]+1;
				if($m[3] < 10)	$m[3] = '0'.(string)$m[3];
				else $m[3] = (string)$m[3];
				$data['bidid'] = $m[1].'-'.$m[2].'-'.$m[3].'-'.$m[4];
			}
		}*/

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
		
		//license
		$code = $data['bidtype'].'code';
		$data[$code] = $licenseInfo['licenseQualificationCode'];

		//yegatype
		$data['yegatype'] = '25';
		
		//convention
		if($basicInfo['jointSupplyDemandYn'])	$data['convention'] = '2';
		
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

    $files=[];


    if($data['bidtype'] == 'pur'){
      
      $data['bid_html'] = "계약조건 공시장소 : <br>".$basicInfo['mailBidDistribution']."<br><br>"."계약착수일 및 완료일 : <br>".$basicInfo['contractBeginEndDate']."<br><br>"."입찰시 제출서류 : <br>".$basicInfo['bidAttendDocument']. "<br><br>"."입찰참가자격 : <br>".$basicInfo['bidAttendRestrict']."<br><br>"."입찰보증금귀속 : <br>".$basicInfo['bidBondBelong']."<br><br>"."입찰무효사항: <br>".$basicInfo['bidNullification']."<br><br>"."입찰참가신청서류 : <br>". $basicInfo['bidAttendRequestDocument']."<br><br>"."추가정보제공처 : <br>".$basicInfo['moreInformation']."<br><br>"."기타공고사항 : <br>".$basicInfo['etc'];

			if ($fileList2 != NULL){
				 foreach($fileList2 as $file){
					 //$file['name'] = iconv('euc-kr','utf-8',$file['name']);
					 //$file['name'] = iconv('utf-8','euc-kr',$file['name']);
					 $filename=$file['name'].'#http://srm.kepco.net/DownloadAttachment.do?id='.$file['id'];
					 $files[]=$filename;
					}
			 $att_lnk1 =join('|',$files);
			}

			$data['attchd_lnk'] = $att_lnk1;

		}else{
			if ($fileList != NULL){
				 foreach($fileList as $file){
					 //$file['name'] = iconv('euc-kr','utf-8',$file['name']);
					 //$file['name'] = iconv('utf-8','euc-kr',$file['name']);
					 $filename=$file['name'].'#http://srm.kepco.net/DownloadAttachment.do?id='.$file['id'];
					 $files[]=$filename;
					}
			 $att_lnk1 =join('|',$files);
			}

			$data['attchd_lnk'] = $att_lnk1;
			//bidcomment
			$data['bidcomment'] = $basicInfo['etc']; // or $data['bidcomment']=$basicInfo['bidAttendRestrict'];
		}

    return $data;
  }

/*	public function createBidid($str) {
		$now = new DateTime();
		$fbidid = $now->format('YmdH');
		$sbidid = $str;
		$tbidid = rand(0,9999);

		$bidid = $fbidid.$sbidid.$tbidid;
		
		return $bidid;
	}
*/
}
