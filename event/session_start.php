<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main\EventManager;
use Bitrix\ImOpenLines\Model\SessionTable;
               

require_once($_SERVER["DOCUMENT_ROOT"] . "/local/lib/CastomQueue/CastomSessionFunc.php");

EventManager::getInstance()->addEventHandler(
    'imopenlines',
    'OnSessionStart',
    ['StartChatSession','SessionStart']
);

class StartChatSession
{
    function SessionStart(\Bitrix\Main\Event $event)
    {
        $eventParams = $event->getParameters();
        if($eventParams['SESSION']['MODE']!='output')
        {    $sesionFubc = new CastomSessionFunc;
            $arUserDep=$sesionFubc->GetUserDep();
            //получить чаты ботов и выбрать у кого меньше всего
            $arParams=[
                'select'=>['ID','OPERATOR_ID'],
                'filter'=>['OPERATOR_ID'=>$arUserDep],
                'order'=>[],
                'limit'=>'',
            ];
            
            $dbSesionOperator=SessionTable::GetList($arParams);
                while($rsSesionOperator= $dbSesionOperator->Fetch())
                {
                    $arSesionOperator[]=$rsSesionOperator;
                }
            $arCaunt=[];
            foreach($arUserDep as $v)
            {
                if(array_search($v,$arSesionOperator[1]))
                {
                    foreach($arSesionOperator as $val)
                    {
                        if($v==$val['OPERATOR_ID'])
                        {
                        
                            $arCaunt[$v]+=1;
                        }
                    
                    }
                }
                else
                {	
                    $arCaunt[$v]=0;
                }
            }
            $operatorId=array_keys($arCaunt,min($arCaunt))[0];

            //file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/local/log/lfild.log', print_r($eventParams,1), FILE_APPEND);
            $chatID=$eventParams['SESSION']['CHAT_ID'];
            $userID=$operatorId;
            $chat = new \CIMChat(0);
            $chat->AddUser($chatID, $userID, null, true, true);
            $chat->SetOwner($chatID,$userID,false);
            
            //отправлять в lia старт сессии?
            return new \Bitrix\Main\EventResult(
            \Bitrix\Main\EventResult::SUCCESS,
            $eventParams
            );
        }    
     
    }
}

?>