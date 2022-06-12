<?php
namespace Wizart\CastomQueue;
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
require_once($_SERVER["DOCUMENT_ROOT"] . "/local/lib/CastomQueue/CastomSessionFunc.php");

use Bitrix\Im\Model\ChatTable;
use Bitrix\Im\Model\MessageTable;
use Bitrix\ImOpenLines\Im;
use Bitrix\ImOpenLines\Chat;
use Bitrix\ImOpenLines\Model\SessionTable;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\ImOpenLines\Queue;
use Bitrix\ImOpenLines\Session;
use Wizart\CastomQueue\Model\SettingsCustomQueueTable;

if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
//получить чаты с операторами ботами и датой последнего сообщение больше 10 минут
//если автор бот закрыть чат если автор пользователь скинуть в очередь
class CloseBotChat
{
    const DEFULT_TAG=56427;//при переезде проверить
    public function close_chat()
    {
        $sesionFunc = new \CastomSessionFunc;
        $arUserDep=$sesionFunc->GetUserDep();

        $params=['select'=>['CLOSE_CHATS_TIME'],
        'filter'=>['ID'=>1]         //можно сделать конфиги
        ];
        $settigs=SettingsCustomQueueTable::getList($params)->FetchAll();
        if(!$settigs[0]['CLOSE_CHATS_TIME'])
        {
        $time=1200;
        }
        else
        {
        $time=$settigs[0]['CLOSE_CHATS_TIME'];
        }

        $params=[
            'select'=>['ID','CHAT_ID','STATUS','MESSAGE_AUTOR'=>'MASSAGE_TABLE.AUTHOR_ID'],
            'filter'=>['OPERATOR_ID'=>$arUserDep,'<=MASSAGE_TABLE.DATE_CREATE'=>date('d.m.Y H:i:s',strtotime('-'.$time.' sec'))],
            'runtime'=>[
                new Reference(
                    'CHAT_TABLE',
                    ChatTable::class,
                    Join::on('this.CHAT_ID','ref.ID')
                ),
                new Reference(
                    'MASSAGE_TABLE',
                    MessageTable::class,
                    Join::on('this.CHAT_TABLE.LAST_MESSAGE_ID','ref.ID')
                )
            ]

        ];
        $dbSession=SessionTable::getList($params);
        $Queue=new Queue;
        while($rsSession = $dbSession->Fetch())
        {
            if($rsSession['STATUS']<=25)
            {
                $Queue->returnSessionToQueue($rsSession['ID']);
                SessionTable::Update($rsSession['ID'],['OPERATOR_ID'=>0]);
                ChatTable::Update($rsSession['CHAT_ID'],['AUTHOR_ID'=>0]);
            }
            elseif($rsSession['STATUS']==40)
            {
                $prioritetId=QueueChatManager::DEFULT_PRIORITY_ID;
                $closeSession=1;
                $sesionFunc->setPrioritySession($prioritetId,$rsSession['ID'],$rsSession['CHAT_ID'],$closeSession);

                $sesionFunc->set_tag($rsSession['ID'],$rsSession['CHAT_ID'],self::DEFULT_TAG,$closeSession);
                //проверить есть ли тэги и приоритет
                $mesId=Im::addMessage([
                    'FROM_USER_ID' => 0,
                    'TO_CHAT_ID' => $rsSession['CHAT_ID'],
                    'MESSAGE' => 'Чат завершён',
                    'SYSTEM' => 'Y',

                ]);
                $chat=new Chat($rsSession['CHAT_ID']);
                $session = new Session();
                $session->loadByArray($rsSession,1,$chat);
                $session->finish();

            }


        }

       
        
    }

}

$CloseBotChat=new CloseBotChat;
$CloseBotChat->close_chat();