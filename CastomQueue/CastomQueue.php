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
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
require_once($_SERVER["DOCUMENT_ROOT"]."/local/vendor/autoload.php");

use Bitrix\Main\Loader;
use Wizart\CastomQueue\QueueChatManager,
    Wizart\CastomQueue\QueueUserManager;
use Bitrix\ImOpenLines\Model\SessionTable,
    Bitrix\ImOpenLines\Im;
use Bitrix\ImOpenLines\Chat;
use Bitrix\Main\Type\DateTime;
use Bitrix\Pull\Event;
use Wizart\CastomQueue\Model\ChecCronTable,
    Wizart\CastomQueue\Model\SettingsCustomQueueTable;

Loader::includeModule('imopenlines');
Loader::includeModule('pull');
Loader::includeModule('im');
class CastomQueue
{

    public function checCron()
    {
        $ChecCronTable=new ChecCronTable;
        $checTable=$ChecCronTable->getList()->FetchAll();
  
        if(!$checTable)
        {
            $ChecCronTable->add(["fields" =>['DATE_CREATE'=>new DateTime(date('d.m.Y H:i:s'))]]);
            return true;
        }
        else
        {
            if($checTable[0]['DATE_CREATE']->getTimestamp()<strtotime(date('d.m.Y H:i:s',(strtotime('-5 minute')))))
			{
			$ChecCronTable->delete($checTable[0]['ID']);
			return true;
			}
			else
			{
				 return false;
			}
        }
    }

    public function logic()
    {
        $start=$this->checCron();
        if($start)
        {
            $QueueChatManager = new QueueChatManager;
            $QueueUserManager = new QueueUserManager;
            $chatneedmanager= $QueueChatManager->get_chat_need_operator();
            $operators=$QueueUserManager->get_caunt_user_chat();
            
            if(is_array($operators))
            {
                
    
                $priority=array_column($chatneedmanager,'PRIORITY');
                array_multisort($priority, SORT_ASC,SORT_NUMERIC,  $chatneedmanager);
                $caunt=array_column($operators,'caunt');
                array_multisort($caunt, SORT_ASC,SORT_NUMERIC,  $operators);
                $params=[
                    'select'=>['LIMITATION_MAX_CHAT','MAX_CHAT'],
                    'filter'=>['ID'=>1]
                ];

                $settings=SettingsCustomQueueTable::getList($params)->fetchAll();
                if($settings[0]['LIMITATION_MAX_CHAT']=='Y')
                {
                    $maxChat=$settings[0]['MAX_CHAT'];
                }
                else
                {
                    $maxChat=200;
                }
                foreach ($chatneedmanager as $key => $value)
                {
                    
                    $array_keymap = self::array_recursive_search_key_map((int)$value['PRIORITY_ID'], $operators);
                    if($array_keymap)
                    {
                        //навешиваем чат добавляем каунт сортируем заного
                        if($operators[$array_keymap[0]]['caunt']<$maxChat)
                        {
                            if(!$value['CHAT_ID'])
                            {
                                $params=[
                                    'select'=>['CHAT_ID'],
                                    'filter'=>['ID'=>$value['SESSION_ID']]
                                ];
                                $arSession=SessionTable::GetList($params)->FetchAll();
                                $chatID=$arSession[0]['CHAT_ID'];
                                
                            }
                            else
                            {
                                $chatID=$value['CHAT_ID'];
                            }
                            if($chatID)
                            {
                                
                               
                                $operatorId=$operators[$array_keymap[0]]["ID"];
                                //file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/local/log/loc.log',print_r($operatorId,1), FILE_APPEND);
                                if($operatorId)
                                {
                                    $ChatOL = new Chat($chatID);
                                    $chat=new \CIMChat;
                                    $chat->SetOwner($chatID,$operatorId,false);
                                    $result=$ChatOL->startSession($operatorId);
                                    if($result)
                                    {
            
                                        //need push and mesage
                                    \CPullStack::AddByUser($operatorId, [
                                            'module_id' => 'im',
                                            'command' => 'chatOwner',
                                            'params' => [
                                                'chatId' => $chatID
                                            ]
                                        ]);
                                        Event::send();
            
                                        $mesId=Im::addMessage([
                                            'FROM_USER_ID' => 0,
                                            'TO_CHAT_ID' => $chatID,
                                            'MESSAGE' => 'Вы назначены ответсвенным за чат',
                                            'SYSTEM' => 'Y',
            
                                        ]);
                                        
                                        $chat->SetUnReadMessage($chatID,$mesId);
                                        $operators[$array_keymap[0]]['caunt']++;
                                        $caunt=array_column($operators,'caunt');
                                        array_multisort($caunt, SORT_ASC,SORT_NUMERIC,  $operators);
                                        unset($chatneedmanager[$key]);
                                }
         
                                }
                            }
                        }
        
                    }
            
                }
                //
                $ChecCronTable=new ChecCronTable;
                $checTable=$ChecCronTable->getList()->FetchAll();
                $ChecCronTable->delete($checTable[0]['ID']);
            }
        }



    }   
    public static function array_recursive_search_key_map($needle, $haystack) {
        foreach($haystack as $first_level_key=>$value) {
            if ($needle === $value) {
                return array($first_level_key);
            } elseif (is_array($value)) {
                $callback = self::array_recursive_search_key_map($needle, $value);
                if ($callback) {
                    return array_merge(array($first_level_key), $callback);
                }
            }
        }
        return false;
    }

}

$CastomQueue = new CastomQueue;
$CastomQueue->logic();