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
    ];
    $res=$this->post('/router',['json'=>$post_data]);
    foreach($res as $row){
      switch($row['method']){
        case 'findBidBasicInfo': $basicInfo=$row['result']; break;
        case 'getFileItemList': $fileList=$row['result']; break;
				case 'findLicenseCodeData': $licenseInfo=$row['result']; break;
      }    
		}
		
		$bidtype = $basicInfo['itemType'];
		
		//����Խñ���ڵ�
		$data['whereis']	= '03';											
		
		//������,����з�
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
		
		//�����ȣ
		if($basicInfo['purchaseType']=='ConstructionService'){
			if(preg_match('/([A-Z0-9]{3})(\d{2})(\d{5})/',$basicInfo['no'],$m)){
				$data['old_notinum'] = $m[1].'-'.$m[2].'-'.$m[3];
			}
		}else if($basicInfo['purchaseType']=='Product'){
			if(preg_match('/(\d{4})(\d{6})/',$basicInfo['no'],$m)){
				$data['old_notinum'] = $m[1].'-'.$m[2];
			}
		}
		$data['notinum'] = $basicInfo['no'];
		$data['notinum_ex'] = $basicInfo['revision'];
		//�����
		$data['constnm'] = $basicInfo['name'];
	
		//���ֱ��
		$data['org'] = $basicInfo['representativeDepartmentName'];
		$data['org_i'] = $basicInfo['representativeDepartmentName'];
		
		//�Խñ�� str(bidid ������ ����)
		$orgstr = 'KEP';
		//�����������
		if($basicInfo['noticeType']=='New' || $basicInfo['noticeType']=='OnceMore'){
			$data['bidproc'] = 'B';
			//bidid ����						
			$now = new DateTime();
			$fbidid = substr($now->format('YmdH'),2);			
			//$tbidid = rand(0,9999);				
			$tbidid = str_pad(mt_rand(0,9999),4,'0',STR_PAD_LEFT); // str_pad()�� 4�ڸ� �����
			$data['bidid'] = $fbidid.$orgstr.$tbidid.'-00-00-01';
			
			//$maxno=$this->module->db->createCommand("select max([[no]]) from {{bid_key}}")->queryScalar();
		}
		else if($basicInfo['noticeType']=='Correct'){
			$data['bidproc'] = 'M';
			
			$prev=$bidkey=BidKey::find() ->where("notinum='{$data['notinum']}' or notinum='{$data['old_notinum']}'")
          ->andWhere(['whereis'=>'03'])
          ->orderBy('bidid desc')
          ->limit(1)->one();
			
			$old_bidid = $prev->bidid;
			if(preg_match('/([A-Z0-9]{15})-(\d{2})-(\d{2})-(\d{2})/',$old_bidid,$m)){
				$m[2] = (int)$m[2]++;
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
			if(preg_match('/([A-Z0-9]{15})-(\d{2})-(\d{2})-(\d{2})/',$old_bidid,$m)){
				$m[2] = (int)$m[2]++;
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
			//$old_bidid = '16080111KEP6091-00-00-01';
			if(preg_match('/([A-Z0-9]{15})-(\d{2})-(\d{2})-(\d{2})/',$old_bidid,$m)){
				$m[3] = (int)$m[3]+1;
				if($m[3] < 10)	$m[3] = '0'.(string)$m[3];
				else $m[3] = (string)$m[3];
				$data['bidid'] = $m[1].'-'.$m[2].'-'.$m[3].'-'.$m[4];
			}else{
				$data['bidid'] = '123456789012345-00-00-01';
			}
		}

		//�����
    if($basicInfo['competitionType']=='Limited')	$data['contract'] = '20';
    else if($basicInfo['competitionType']=='Open')	$data['contract'] = '10';

		//�������
		if($basicInfo['bidMethod']=='Electronic')	$data['bidcls'] = '01';
		else if($basicInfo['bidMethod']=='Offline')	$data['bidcls'] = '00';		
		
		//�������
    if($basicInfo['bidType']=='LowestPrice')	$data['succls'] = '02';
    else if($basicInfo['bidType']=='QualifiedEval')	$data['succls'] = '01';
		else if($basicInfo['bidType']=='Nego') $data['succls'] = '07';
		else if($basicInfo['bidType']=='LimitedLowestPrice')	$data['succls'] = '03';
		else	$data['succls'] = '00';
		
		//�����ڵ�
		$code = $data['bidtype'].'code';
		$data[$code] = $licenseInfo['licenseQualificationCode'];

		//�������޿���
		if($basicInfo['jointSupplyDemandYn'])	$data['opt'] = pow(2,8);

		//��������
		$data['presum'] = $basicInfo['presumedPrice'];
		
		//���񰡰� ���ʱݾ�
		$data['basic'] = $basicInfo['estimatedPriceBasicAmount'];
		
		//������(Ȯ��ġ�� ����)
		$data['pct'] = $basicInfo['winningPriceLimitRate'];

		//����Խ��Ͻ�
		$data['noticedt'] = date('Y-m-d H:i:s',strtotime($basicInfo['noticeDateTime']));		
		
		//������ϸ����Ͻ�
		$data['registdt'] = date('Y-m-d H:i:s',strtotime($basicInfo['bidAttendRequestCloseDateTime']));		

		//����������
    $data['opendt'] = date('Y-m-d H:i:s',strtotime($basicInfo['beginDateTime']));

		//����������
    $data['closedt'] = date('Y-m-d H:i:s',strtotime($basicInfo['endDateTime']));		
		
		//�����Ͻ�
    $data['constdt'] = date('Y-m-d H:i:s',strtotime($basicInfo['beginDateTime']));
		
		//�������
		$data['state'] = 'N';

		//���������ڰ�
		$data['bidcomment'] = $basicInfo['etc']; // or $data['bidcomment']=$basicInfo['bidAttendRestrict'];
		
    $files=[];
    foreach($fileList as $file){
      $filename=$file['name'];
      $files[]=$filename;
    }
    $data['attchd_lnk']=join('|',$files);

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
