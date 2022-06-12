<?



if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main\Loader;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Application;
use Bitrix\Main\Entity;
use Bitrix\Main\ORM\Fields\IntegerField;
use Wizart\CastomQueue\Model\PriorityChatTable;
Loader::includeModule('intranet');
Loader::includeModule('imopenlines');
Loader::includeModule('im');
Loader::includeModule('crm');
Loader::includeModule('iblock');

class CastomSessionFunc
{

    const DEP_BOT=56;//бот департамент//при переезде менять
    public function GetUserDep($botDep=self::DEP_BOT)
    {

        $dbUserDep=CIntranetUtils::GetDepartmentEmployees($botDep,false,false,'Y',[]);
        while($rsUserDep=$dbUserDep->Fetch())
        {
            $arUserDep[]=$rsUserDep['ID'];
        }
        return $arUserDep;
    }

     public function SendLia($user_id,$Message,$UserName=null,$contactID=null)
    {
        $key='UMvaTUm5WhIEWYE5txmwzrcrAyJoBpUVXEGFdgexHbM8tDSBL4RnJ8sFm4UucMG1';
        $url="https://app.lia.chat/api/v1/handling/event?key=$key";
        
        if(!empty($Message['message']) && empty($Message['file']))
        {
            $tipe='text';
            $params='text';
            $text=$Message['message'];
        }elseif(empty($Message['message']) && !empty($Message['file']))
        {
            $tipe='image';
            $params='url';
            $text='https://example.com/file.jpg';
        }

        $httpClient = new HttpClient();
        $httpClient->setHeader('Content-Type', 'application/json', true);
        $response = $httpClient->post($url,json_encode(
            array(
                'user_id'=>$user_id,
                'event'=>
                    ['type'=>$tipe,
                        'params'=>[$params=>$text]
                    ],
                    'facts'=>
                    [
                        'first_name'      =>$UserName,
                        'contact_id'      =>$contactID
                    ]
            )));
        $sendResult=json_decode($response,true);
        //проверка на ошибки нужна сдесь
        return $sendResult;
    }

    public static function getSessionPriority($sessionId)
    {
        $allfunctions = new PriorityChatTable;
        $param=[
            'select'=>['ID','PRIORITY_ID'],
            'filter'=>['SESSION_ID'=>$sessionId]
        ];
        $sessionPriority=$allfunctions->getList($param)->fetchAll();
        if($sessionPriority)
        {
            return $sessionPriority;
        }
        else
        {
            return false;
        }
    }

    public function setPrioritySession($arprioritetId,$sessionId,$chatId,$closeSession=0)
    {
        $dbSessionsPriority = CIBlockElement::GetList(["SORT" => "ASC"],["IBLOCK_CODE"=> "CHAT_PRIORITY","PROPERTY_LIA_ASSOCIATION"=>$arprioritetId],false,false,["ID", "NAME","PROPERTY_PRIORITY"]);
        while($rsSessionsPriority= $dbSessionsPriority->Fetch())
        {
            $arSessionsPriority[]=$rsSessionsPriority;
        }
        $prioritetId=$arSessionsPriority[0]['ID'];
        if($prioritetId)
        {
            $sessionPriority=self::getSessionPriority($sessionId);
            $allfunctions = new PriorityChatTable;
            if($sessionPriority==false)
            {
                $allfunctions::add(['fields'=>['SESSION_ID'=>$sessionId,'PRIORITY_ID'=>(int)$prioritetId,'CHAT_ID'=>$chatId]]);
            }
            else
            {
                if($prioritetId!=$sessionPriority[0]['PRIORITY_ID'])
                {
                    if($closeSession==0)
                    {
                        $data=["fields"=>['PRIORITY_ID'=>$prioritetId]];
                        $allfunctions::update($sessionPriority[0]['ID'],$data);
                    }

                }
            }
        }
    }

    public function set_tag($sessionId,$chatID,$tag,$closeSession=0)
    {
        $iblock=CIBlock::GetList([],['CODE'=>'chat_mode_seve'])->Fetch();
        $rsElement = CIBlockElement::GetList([],[
            "ACTIVE"    => "Y",
            'IBLOCK_ID'=>$iblock['ID'],
            'PROPERTY_dialog_id'=>$sessionId
        ],
        false,
        false,
        ["ID", "NAME", "IBLOCK_ID","PROPERTY_mutagen_id"]
        );
        while($arElement = $rsElement->fetch())
        {
            $arRes[]=$arElement;
        }
        $el=new CIBlockElement;
        if($arRes)
        {
            $arFilds=[
                "PROPERTY_VALUES"=>[
                    'dialog_id'=>$sessionId,
                    'chat_id'=>$chatID,
                    'mutagen_id'=>$tag
                    ]
            ];
            if($closeSession==0)
            {
                $el->Update($arRes[0]['ID'],$arFilds);
            }
            
        }
        else
        {
            $arEl=[
                "IBLOCK_ID"      => $iblock['ID'],
                "PROPERTY_VALUES"=> [
                    'chat_id'=>$chatID,
                    'dialog_id'=>$sessionId,
                    'mutagen_id'=>$tag
                ],
                "NAME"           => "Элемент",
                "ACTIVE"         => "Y",  
            ];
            if($PRODUCT_ID =  $el->Add($arEl))
            {
                //file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/local/log/loc.log',print_r($PRODUCT_ID,1), FILE_APPEND);
            }
            else
            {
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/local/log/loc.log',print_r($el->LAST_ERROR,1), FILE_APPEND);
            }
            
        }
    }

}