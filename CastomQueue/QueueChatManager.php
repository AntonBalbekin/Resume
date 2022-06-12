<?php

namespace Wizart\CastomQueue;

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/local/vendor/autoload.php");

if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main\Loader;
use Bitrix\ImOpenLines\Model\SessionCheckTable;
use Bitrix\ImOpenLines\Model\SessionTable;
use Bitrix\Main\ORM\Query\Join;
use Wizart\CastomQueue\Model\PriorityChatTable;
use Wizart\CastomQueue\QueueUserManager;
use Bitrix\Im\Model\ChatTable;
use Bitrix\Im\Model\MessageTable;
use Bitrix\Im\Model\RecentTable;
use Bitrix\ImOpenLines\Queue;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Pull\Event;
use Wizart\CastomQueue\Model\SettingsCustomQueueTable;
Loader::includeModule('iblock');
Loader::includeModule('imopenlines');
class QueueChatManager
{
    const PRIORITY_CODE='CHAT_PRIORITY';//символьный код инфоблока
    const DEFULT_PRIORITY_ID=79927;//ид приоритета по дефулту при переездде поменять
    public function get_chat_need_operator()
    {
        //,'<=MESSAGE_TABLE.DATE_CREATE'=>date('d.m.Y H:i:s',strtotime('-600 sec'))
        $params=[
            'select'=>['SESSION_ID','AUTHOR_ID'=>'MESSAGE_TABLE.AUTHOR_ID','PRIORITY_ID'=>'CHAT_PRORITY.PRIORITY_ID','DATE_CREATE'=>'MESSAGE_TABLE.DATE_CREATE', 'MESSAGE_TABLE.MESSAGE','CHAT_TABLE.LAST_MESSAGE_ID','CHAT_ID'=>'SESSION_TABLE.CHAT_ID'],
            'filter'=>['!DATE_QUEUE'=>null,'<=MESSAGE_TABLE.DATE_CREATE'=>date('d.m.Y H:i:s',strtotime('-600 sec'))],
            'runtime'=>
            [
                new Reference (
                    'CHAT_PRORITY',
                    PriorityChatTable::class,
                    Join::on('this.SESSION_ID','ref.SESSION_ID')
                ),
                new Reference(
                    'SESSION_TABLE',
                    SessionTable::class,
                    Join::on('this.SESSION_ID','ref.ID')
                ),
                new Reference(
                    'CHAT_TABLE',
                    ChatTable::class,
                    Join::on('this.SESSION_TABLE.CHAT_ID','ref.ID')
                ),
                new Reference(
                    'MESSAGE_TABLE',
                    MessageTable::class,
                    Join::on('this.CHAT_TABLE.LAST_MESSAGE_ID','ref.ID')
                ),
            ]
        ];
        $dbChat=SessionCheckTable::GetList($params);
        while($rsChat=$dbChat->Fetch())
        {
            $data_crate=$rsChat['DATE_CREATE']->getTimestamp();
            $castomSessionFunc=new \CastomSessionFunc;
            $botDep=$castomSessionFunc->GetUserDep();
            if(!in_array($rsChat['AUTHOR_ID'],$botDep) && $data_crate >= date('d.m.Y H:i:s',strtotime('-600 sec')))
            {
            
                if(!$rsChat['PRIORITY_ID'])
                {
                    $rsChat['PRIORITY_ID']=self::DEFULT_PRIORITY_ID;
                }
                $arChat[]=$rsChat;
            }elseif (in_array($rsChat['AUTHOR_ID'],$botDep))
            {
                if(!$rsChat['PRIORITY_ID'])
                {
                    $rsChat['PRIORITY_ID']=self::DEFULT_PRIORITY_ID;
                }
                $arChat[]=$rsChat;
            }
            
        }
        $priorityVal=self::get_priority_val();
        foreach ($arChat as $key => $Chat) {
            $arChat[$key]['PRIORITY']=$priorityVal[$Chat['PRIORITY_ID']];
        }
        return $arChat;
    }

    public static function get_priority_val()
    {
        $iblocInfo=\CIBlock::GetList([],['CODE'=>self::PRIORITY_CODE])->Fetch();
        $iblockID=$iblocInfo['ID'];
        $dbPriorityList=\CIBlockElement::GetList([],['IBLOCK_ID'=>(int)$iblockID],false,false,['ID',"IBLOCK_ID", "NAME","PROPERTY_PRIORITY"]);
        while($rsPriorityList=$dbPriorityList->Fetch())
        {
            $arPriorityList[]=$rsPriorityList;
        }
        $arPriority=array_column($arPriorityList,'PROPERTY_PRIORITY_VALUE','ID');
        return $arPriority;
    }
    
    public function return_chat_queue()
    {
        $operators=QueueUserManager::get_users_operators(0);

        $params=['select'=>['QUEUE_TIME'],
                 'filter'=>['ID'=>1]         //можно сделать конфиги
        ];
        $settigs=SettingsCustomQueueTable::getList($params)->FetchAll();
        if(!$settigs[0]['QUEUE_TIME'])
        {
            $time=600;
        }
        else
        {
            $time=$settigs[0]['QUEUE_TIME'];
        }
        $params=[
            'select'=>['ID','CHAT_ID','OPERATOR_ID'],
            'filter'=>['OPERATOR_ID'=>$operators,'<=STATUS'=>25,'<=MESSAGE_TABLE.DATE_CREATE'=>date('d.m.Y H:i:s',strtotime('-'.$time.' sec'))],
            'runtime'=>
            [
                new Reference(
                    'CHAT_TABLE',
                    ChatTable::class,
                    Join::on('this.CHAT_ID','ref.ID')
                ),
                new Reference(
                    'MESSAGE_TABLE',
                    MessageTable::class,
                    Join::on('this.CHAT_TABLE.LAST_MESSAGE_ID','ref.ID')

                )
            ]
        ];
        $seesionReturnQueue = SessionTable::GetList($params)->FetchAll();


       //удалить из истории
       
        foreach ($seesionReturnQueue as  $value) {
            $Queue=new Queue;
            $Queue->returnSessionToQueue($value['ID']);
            SessionTable::Update($value['ID'],['OPERATOR_ID'=>0]);
            ChatTable::Update($value['CHAT_ID'],['AUTHOR_ID'=>0]);
            $params=[
                'select'=>['USER_ID'],
                'filter'=>['ITEM_CID'=>$value['CHAT_ID']]
            ];
            $arRecent=RecentTable::GetList($params)->FetchAll();
            foreach ($arRecent as $val) {
                RecentTable::Delete(['ITEM_ID'=>$value['CHAT_ID'],'ITEM_TYPE'=>'L','USER_ID'=>$val['USER_ID']]);
            }
            
            \CPullStack::AddByUser($value['OPERATOR_ID'], [
                'module_id' => 'im',
                'command' => 'chatHide',
                'expiry' => 3000,
                'params' => [
                    'dialogId' =>'chat'.$value['CHAT_ID']
                ],
                'extra' => \Bitrix\Im\Common::getPullExtra()
            ]);
            Event::send();

        }

        return $seesionReturnQueue;
    }

}


$queueChatManager = new QueueChatManager;
$test=$queueChatManager->get_chat_need_operator();
