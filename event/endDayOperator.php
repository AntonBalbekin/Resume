<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

CModule::IncludeModule("timeman");
use Bitrix\Timeman\Monitor\Utils\Department;
use Bitrix\Im\Model\ChatTable;
use Bitrix\Im\Model\RecentTable;
use Bitrix\Im\Model\RelationTable;
use \Bitrix\ImOpenLines\Model\SessionTable;
use Bitrix\ImOpenLines\Queue;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Wizart\CastomQueue\Model\UserQueueTable;
use Wizart\CastomQueue\Model\DepartmentPriorityTable;
use Bitrix\Pull;

$eventManager = \Bitrix\Main\EventManager::getInstance();
$eventManager->addEventHandler('timeman', 'OnAfterTMDayEnd', 'OnDayEndOperator');

function OnDayEndOperator($filds)
{
    $userId=$filds['USER_ID'];
    CModule::IncludeModule('im');
    CModule::includeModule('imopenlines');
    $params=[
        'select'=>['USER_ID'],
        'filter'=>['USER_ID'=>$userId]
    ];
    $arOperator = UserQueueTable::GetList($params)->fetchAll();
    $userDep = Department::getUserDepartments($userId);
    $par=[
        'select'=>['ID'],
        'filter'=>['UF_DEPARTMENT'=>$userDep[0]]
    ];
    $arDep=DepartmentPriorityTable::GetList($par)->FetchAll();
//оператор из этих


    if($arOperator || count($arDep)>0)
    {
        $paramsChat=[
            'select'=>['ID','SESSION_ID'=>'SESSION_TABLE.ID'],
            'filter'=>['AUTHOR_ID'=>$userId,'<=SESSION_TABLE.STATUS'=>40
            
            ],
            'runtime'=>[
                new Reference(
                    'SESSION_TABLE',
                    SessionTable::class,
                    Join::on('this.ID','ref.CHAT_ID')
                )
            ]
        ];
        $arSession=ChatTable::GetList($paramsChat)->fetchAll();

        if($arSession)
        {
            foreach ($arSession as $key => $Session)
            {
                $Queue=new Queue;
                $Queue->returnSessionToQueue($Session['SESSION_ID']);
                //SessionCheckTable::update($Session['SESSION_ID'],['DATE_QUEUE' => new DateTime(),'REASON_RETURN'=>Queue::REASON_DEFAULT]);
                SessionTable::Update($Session['SESSION_ID'],['OPERATOR_ID'=>0]);
                ChatTable::Update($Session['ID'],['AUTHOR_ID'=>0]);
                RecentTable::Delete(['ITEM_ID'=>$Session['ID'],'ITEM_TYPE'=>'L','USER_ID'=>$userId]);
                $par=[
                    'select'=>['ID'],
                    'filter'=>['CHAT_ID'=>$Session['ID'],'USER_ID'=>$userId]
                ];
                $arRel=RelationTable::getList($par)->FetchAll();
                if($arRel)
                {
                    RelationTable::delete($arRel[0]['ID']);
                }
                \CPullStack::AddByUser($userId, [
                    'module_id' => 'im',
                    'command' => 'chatHide',
                    'expiry' => 3000,
                    'params' => [
                        'dialogId' =>'chat'.$Session['ID']
                    ],
                    'extra' => \Bitrix\Im\Common::getPullExtra()
                ]);
                Pull\Event::send();
            }

        }
    }
}