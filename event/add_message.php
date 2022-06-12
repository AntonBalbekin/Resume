<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main\EventManager;
use Bitrix\ImOpenLines\Model\SessionTable;
use Bitrix\ImOpenLines\Queue;
use Bitrix\Im\Model\ChatTable;
use Wizart\CastomQueue\Model\PriorityChatTable;
use Wizart\CastomQueue\QueueUserManager;
require_once($_SERVER["DOCUMENT_ROOT"] . "/local/lib/CastomQueue/CastomSessionFunc.php");



EventManager::getInstance()->addEventHandler(
    'im',
    'OnAfterMessagesAdd',
    ['CastomAfterMessagesAdd','CastomMessagesAdd']
);

class CastomAfterMessagesAdd
{
    const DEFULT_PRIORITY=55;
    function CastomMessagesAdd($id,$filds)
    {
        
        if($filds['MESSAGE']!='[b]Контакт прикреплен к диалогу[/b]')
        {
            if(!isset($filds['SYSTEM']) || $filds['SYSTEM']=='N')//тут проверить
            {
                $sesionFunc = new CastomSessionFunc;
                $arUserDep=$sesionFunc->GetUserDep();
                $QueueUserManager=new QueueUserManager;
                $arOperators=$QueueUserManager->get_users_operators(0);
                $autorOperator=in_array($filds['AUTHOR_ID'],$arOperators);
                if(in_array($filds['CHAT_AUTHOR_ID'],$arUserDep) && $filds['CHAT_AUTHOR_ID']!=$filds['AUTHOR_ID'] && $autorOperator==FALSE  && $filds['AUTHOR_ID']!=0)
                {  
                    
                    $AturChat=$filds['CHAT_AUTHOR_ID'];
                    $arChatEntityData=explode('|',$filds['CHAT_ENTITY_DATA_1']);
                    $sessionId=$arChatEntityData[5];
                    //file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/local/loc.log', print_r($filds,1), FILE_APPEND);
                    $messageForLia=[
                        'message'=>$filds['MESSAGE'],
                        'file'   =>$filds['FILES']
                    ];
                    if($arChatEntityData[1]=='CONTACT')
                    {
                        $contactID=$arChatEntityData[2];

                        $arUser=CUser::GetList([],[],['ID'=>$filds['AUTHOR_ID'],'CHECK_PERMISSIONS'=>'N'])->Fetch();
                        //запрос в лиа
                        $sendResult=$sesionFunc->SendLia((string)$filds['AUTHOR_ID'],$messageForLia,$arUser['NAME'],$contactID);
                        
                        foreach ($sendResult['events'] as  $value) {
                            //проверять всевозможные events
                            if($value['type']=='category')
                            {   
                                $arprioritetId=$value['params']['category'];

                                //записываем в таблицу 

                                $CastomSessionFunc = new CastomSessionFunc;
                                $CastomSessionFunc->setPrioritySession($arprioritetId,$sessionId,$filds['CHAT_ID']);
    

                            }
                            $Mesage=$value['params']['text'];
                            if($value['type']=='terminate')
                            {
                                //$Mesage='Переключаю Вас на другого оператора';
                                //флаг на скидывание очереди
                                $resetQueue=1;
                            }
                            if($value['type']=='tag')
                            {
                                //обновляем тэги 
                                $tag=$value['params']['tag'];
                                $CastomSessionFunc->set_tag($sessionId,$filds['CHAT_ID'],$tag);
                            }
                            $ar=array(
                                "TO_CHAT_ID" => $filds['CHAT_ID'], // ID чата
                                "FROM_USER_ID" => $AturChat, // ID пользователя состоящего в чате
                                "MESSAGE" =>$Mesage, 
                            );
                            CIMChat::AddMessage($ar);
                            SessionTable::Update($sessionId,['STATUS'=>40]);
                        }
                        //file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/local/loc.log', print_r($sendResult,1), FILE_APPEND);
                    }
                    else
                    {

                        //запрос в лиа 
                        $sendResult=$sesionFunc->SendLia((string)$filds['AUTHOR_ID'],$messageForLia);
                        foreach ($sendResult['events'] as  $value) {
                            if($value['type']=='category')
                            {   
                                
                                $arprioritetId=$value['params']['category'];

                                //записываем в таблицу 

                                $CastomSessionFunc = new CastomSessionFunc;
                                $CastomSessionFunc->setPrioritySession($arprioritetId,$sessionId,$filds['CHAT_ID']);
                            }
                            $Mesage=$value['params']['text'];
                            if($value['type']=='terminate')
                            {
                                //флаг на скидывание очереди
                                $resetQueue=1;
                            }

                            if($value['type']=='tag')
                            {
                                //обновляем тэги 
                                $tag=$value['params']['tag'];
                                $CastomSessionFunc->set_tag($sessionId,$filds['CHAT_ID'],$tag);
                
                            }

                            $ar=array(
                                "TO_CHAT_ID" => $filds['CHAT_ID'], // ID чата
                                "FROM_USER_ID" => $AturChat, // ID пользователя состоящего в чате
                                "MESSAGE" =>$Mesage, 
                            );
                            
                            CIMChat::AddMessage($ar);
                            SessionTable::Update($sessionId,['STATUS'=>40]);
                        }
                        //file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/local/loc.log', print_r($sendResult,1), FILE_APPEND);
                    }
                   /* file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/local/log/loc.log', print_r($sendResult,1), FILE_APPEND);*/
                    
                    if($resetQueue==1)
                    {
                        //скидываем в очередь обновляем таблицу 
                        $Queue=new Queue;
                        $Queue->returnSessionToQueue($sessionId);

                        SessionTable::Update($sessionId,['OPERATOR_ID'=>0]);
                        ChatTable::Update($filds['CHAT_ID'],['AUTHOR_ID'=>0]);
                        //проверяем утановлен ли приоритет сессии если нет устанавливаем дефултный

                        $sessionpriority=CastomSessionFunc::getSessionPriority($sessionId);
                        if($sessionpriority==false)
                        {
                            $dbSessionsPriority = CIBlockElement::GetList(["SORT" => "ASC"],["IBLOCK_CODE"=> "CHAT_PRIORITY","PROPERTY_LIA_ASSOCIATION"=>self::DEFULT_PRIORITY],false,false,["ID", "NAME","PROPERTY_PRIORITY"]);
                            while($rsSessionsPriority= $dbSessionsPriority->Fetch())
                            {
                                $arSessionsPriority[]=$rsSessionsPriority;
                            }
                            $prioritetId=$arSessionsPriority[0]['ID'];
                            //установит приоритет
                            $allfunctions = new PriorityChatTable;
                            $allfunctions::add(['fields'=>['SESSION_ID'=>$sessionId,'PRIORITY_ID'=>(int)$prioritetId,'CHAT_ID'=>$filds['CHAT_ID']]]);
                        }
                    }
                }
            }
        }    
        
    }

}