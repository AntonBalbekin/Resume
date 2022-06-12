<?php 
namespace Wizart\Acquiring;
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?: realpath(__DIR__ . '/../../..');
$DOCUMENT_ROOT = $_SERVER['DOCUMENT_ROOT'];

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_UTF', true);
define('BX_CRONTAB', true);
define('BX_NO_ACCELERATOR_RESET', true);
define('NO_AGENT_CHECK', true);
define('CHK_EVENT', true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
require($_SERVER["DOCUMENT_ROOT"]."/local/components/wizart/claydpay/ReqestClaydPay.php");
use Wizart\Acquiring\Model\AcquringTable;
use Bitrix\Main\Type;

class AcquringLoad
{
    const URLCLAYD="https://api.cloudpayments.ru/payments/list";
    public static function get_last_data_download()
    {
        $params=[
            'select'=>['ID','DATE_PAY'],
            'filter'=>[],
            'order'=>['ID'=>'DESC'],
            'limit'=>1
        ];
        $resAcqur=AcquringTable::getList($params)->FetchAll();
        if(!$resAcqur)
        {
            $data=date("Y-m-d",strtotime('01.01.2021'));
        }
        else
        {
            //дата последней загрузки
            $dateDownload=$resAcqur[0]['DATE_PAY']->getTimestamp();
            $dateDown=date("Y-m-d",$dateDownload);
            $date = new \DateTime($dateDown);
            $date->add(new \DateInterval('P1D'));
            $data=date_format($date, 'Y-m-d');
        }
        
        return $data;
        
    }

    public static function get_first_data_download()
    {
        $params=[
            'select'=>['ID','DATE_PAY'],
            'filter'=>[],
            'order'=>['ID'=>'ASC'],
            'limit'=>1
        ];
        $resAcqur=AcquringTable::getList($params)->FetchAll();
            //дата последней загрузки
            $dateDownload=$resAcqur[0]['DATE_PAY']->getTimestamp();
            $dateDown=date("Y-m-d",$dateDownload);
            $date = new \DateTime($dateDown);
            $data=date_format($date, 'Y-m-d');

        return $data;
    }


    public static function get_paymans_curl($datelastdownload=0)
    {
        $Reqst = new \ReqestClaydPay;
        $aurorize = $Reqst -> getAutoriz();
        $mh = curl_multi_init();
        $chs = array();
        if($datelastdownload==0)
        {
            $datelastdownload=self::get_last_data_download();
            //проверить кусочек
            $today=date('d.m.Y');
            if(strtotime($datelastdownload)>=$today)
            {
                return false;
            }
        }
        else
        {
            $datelastdownload=$datelastdownload;
        }
        
        
        $data="Date=$datelastdownload&TimeZone=MSK";
        $url=self::URLCLAYD;
        foreach ($aurorize as $key => $value)
        {
            $chs[] = ( $ch = curl_init() );
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_USERPWD, $value['identity'] . ":" . $value['name']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_multi_add_handle( $mh, $ch );
        }
        $prev_running = $running = null;
        do{
            curl_multi_exec( $mh, $running );
            if($running != $prev_running)
            {
                $info = curl_multi_info_read( $mh );
                if(is_array( $info ) && ( $ch = $info['handle'] ))
                {
                    $respCurl[] = curl_multi_getcontent( $ch );
                }
                $prev_running = $running;
            }
        }while($running > 0);
        
        foreach ( $chs as $ch ) {
            curl_multi_remove_handle( $mh, $ch );
            curl_close( $ch );
        }
        curl_multi_close($mh);
        foreach ($respCurl as $value) {
            $resultCurl[]=json_decode($value, true);
        }
        return  $resultCurl;
    }
    
    public function set_paymans_day($date=0)
    {

        if($date!=0)
        {
            $arPaymans=self::get_paymans_curl($date);
        }
        else
        {
            $arPaymans=self::get_paymans_curl(0);
        }
        if($arPaymans)
        {
            foreach ($arPaymans as  $value) {
                if($value['Model'])
                {
                    foreach ($value['Model'] as  $value) {
                        $data=[
                            'ACCOUNT_ID'=>$value['AccountId'],
                            'NUMBER'=>$value['TransactionId'],
                            'ZN'=>'',
                            'REASON_CODE'=>$value['ReasonCode'],
                            'STATUS'=>$value['Status'],
                            'ERROR_CODE'=>$value['ReasonCode'],
                            'ERROR_MESSAGE'=>$value['CardHolderMessage'],
                            'LABLE_PAY'=>$value['CardType'],
                            'DATE_PAY'=>new Type\DateTime($value['CreatedDateIso'], 'Y-m-d H:i:s'),
                            'CARD'=>$value['CardFirstSix'].'*******'.$value['CardLastFour'],
                            'CARD_TIME'=>$value['CardExpDate'],
                            'PAYMENT'=>$value['WalletType'],
                            'NUM_ORDER'=>$value['InvoiceId'],
                            'SUM'=>$value['Amount'],
                            'ERROR'=>'',
                        ];
                        AcquringTable::add($data);
                    }
                }
            }
        }
        return $arPaymans;
    }
    public function get_res_period($date_from,$date_to,$user_id)
    {
        global $USER_FIELD_MANAGER;
        $ORIGIN_ID = $USER_FIELD_MANAGER->GetUserFieldValue('CRM_CONTACT', 'UF_CUSTOM_ORIGIN_ID',$user_id); 
        $params=[
            'select'=>['*'],
            'filter'=>['ACCOUNT_ID'=>$ORIGIN_ID,
                       '>=DATE_PAY'=>$date_from,
                       '<=DATE_PAY'=>$date_to,
            ]
        ];
        $bdAcquring=AcquringTable::getList($params);
    
        while($rsAcquring = $bdAcquring->fetch())
        {
            $resPeriod[]=[
                "number" => $rsAcquring['NUMBER'],
                "zn" => '',
                "reason_code" => $rsAcquring['REASON_CODE'],
                "status" => $rsAcquring['STATUS'],
                "error_code" => $rsAcquring['ERROR_CODE'],
                "error_message" => $rsAcquring['ERROR_MESSAGE'],
                "lable_pay" => $rsAcquring['LABLE_PAY'],
                "date" =>date('d-m-Y H:i:s',$rsAcquring['DATE_PAY']->getTimestamp()),
                "card" => $rsAcquring['CARD'],
                "card_time" => $rsAcquring['CARD_TIME'],
                "payment" => $rsAcquring['PAYMENT'],
                "num_order" => $rsAcquring['NUM_ORDER'],
                "sum" => $rsAcquring['SUM'],
                "error" => $rsAcquring,
            ];
        }
        return $resPeriod;
    }
    public function delete_paymans_day()
    {
        $firstData=self::get_first_data_download();
        //$firstData='8.05.2022';
        $date_from=date('d.m.Y H:i:s',strtotime($firstData));
        $date_to=date('d.m.Y H:i:s', strtotime($firstData.'+86399 second'));

        $params=[
            'select'=>['ID'],
            'filter'=>['>=DATE_PAY'=>$date_from,
                        '<=DATE_PAY'=>$date_to,]
        ];
        $resAcqur=AcquringTable::getList($params)->fetchAll();
        foreach ($resAcqur as  $value) {
            AcquringTable::delete($value);
        }
        return $resAcqur;
    }

    public function start_crone_payment()
    {
        //завпускаем в час ночи и забираем данные за вчера с клауд и записываем в базу
        //удаляем первый день записи
        $today=date('Y-m-d');
        $yesterday=date('Y-m-d',strtotime($today.'- 1 day'));
        $sendClaud=$this->set_paymans_day($yesterday);
        $this->delete_paymans_day();
        return $sendClaud;
    }
}

$AcquringLoad = new AcquringLoad;
$data=$AcquringLoad->set_paymans_day(0);
//$data=$AcquringLoad->delete_paymans_day();
//$data=$AcquringLoad->start_crone_payment();

//d($data);
