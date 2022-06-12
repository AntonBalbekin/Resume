<?php

namespace Wizart\CastomQueue\Model;

use Bitrix\Main\Localization\Loc,
    Bitrix\Main\ORM\Data\DataManager,
    Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\Application;
Loc::loadMessages(__FILE__);

/**
 * Class DepartmentPriorityTable
 *
 * Fields:
 * <ul>
 * <li> ID int mandatory
 * <li> UF_DEPARTMENT int mandatory
 * <li> PRIORITY_ID int mandatory
 * </ul>
 *
 * @package Bitrix\Castom
 **/
class PriorityChatTable extends DataManager
{
    /**
     * Returns DB table name for entity.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'c_castom_prioriry_chat_table';
    }

    /**
     * Returns entity map definition.
     *
     * @return array
     */
    public static function getMap()
    {
        return [
            new IntegerField(
                'ID',
                [
                    'primary' => true,
                    'autocomplete' => true,
                    'title' => Loc::getMessage('QUEUE_OPERATORS_ENTITY_ID_FIELD')
                ]
            ),
            new IntegerField(
                'PRIORITY_ID',
                [
                    'required' => true,
                    'title' => Loc::getMessage('QUEUE_OPERATORS_ENTITY_USER_ID_FIELD')
                ]
            ),
            new IntegerField(
                'SESSION_ID',
                [
                    'required' => true,
                    'title' => Loc::getMessage('QUEUE_OPERATORS_ENTITY_USER_ID_FIELD')
                ]
            ),
            new IntegerField(
                'CHAT_ID',
                [
                    'required' => true,
                    'title' => Loc::getMessage('QUEUE_OPERATORS_ENTITY_USER_ID_FIELD')
                ]
            ),

        ];
    }


    public static function createTable()
    {

        $connection = Application::getInstance()->getConnection();

        if (!$connection->isTableExists(static::getTableName())) {
            static::getEntity()->createDbTable();
            return true;
        } else {
            return false;
        }


    }

    public static function dropTable()
    {
        $connection = Application::getInstance()->getConnection();

        $connection->dropTable(static::getTableName());
        return true;
    }


    public static function deleteAll()
    {
        if(!is_callable(array(get_parent_class(__CLASS__), "deleteAll"))){
            $connection = Application::getInstance()->getConnection();
            $connection->truncateTable(static::getTableName());
        } else {
            return parent::deleteAll();
        }
    }

}