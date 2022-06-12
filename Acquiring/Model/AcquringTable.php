<?php

namespace Wizart\Acquiring\Model;

use Bitrix\Main\Localization\Loc,
	Bitrix\Main\ORM\Data\DataManager,
	Bitrix\Main\ORM\Fields\IntegerField,
	Bitrix\Main\ORM\Fields\StringField,
	Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\Application;
Loc::loadMessages(__FILE__);


class AcquringTable extends DataManager
{
    /**
     *  Returns DB table name for entity.
     * 
     * @return string
     */

    public static function getTableName()
	{
		return 'c_castom_acquring';
	}

    /**
	 * Returns entity map definition.
	 *
	 * @return array
	 */

    public static function GetMap()
    {
        return[
            new IntegerField(
				'ID',
				[
					'primary' => true,
					'autocomplete' => true,
					'title' => Loc::getMessage('ID')
				]
			),
            new StringField(
				'ACCOUNT_ID',
				[
					'title' => Loc::getMessage('ACCOUNT_ID')
				]
			),
            new StringField(
				'NUMBER',
				[
					'title' => Loc::getMessage('NUMBER')
				]
			),
            new StringField(
				'ZN',
				[
					'title' => Loc::getMessage('ZN')
				]
			),
            new StringField(
				'REASON_CODE',
				[
					'title' => Loc::getMessage('REASON_CODE')
				]
			),
            new StringField(
				'STATUS',
				[
					'title' => Loc::getMessage('STATUS')
				]
			),
            new StringField(
				'ERROR_CODE',
				[
					'title' => Loc::getMessage('ERROR_CODE')
				]
			),
            new StringField(
				'ERROR_MESSAGE',
				[
					'title' => Loc::getMessage('ERROR_MESSAGE')
				]
			),
            new StringField(
				'LABLE_PAY',
				[
					'title' => Loc::getMessage('LABLE_PAY')
				]
			),
            new DatetimeField(
                'DATE_PAY',
                [
                    'title' => Loc::getMessage('DATE_PAY'),
                ]
            ),
            new StringField(
				'CARD',
				[
					'title' => Loc::getMessage('CARD')
				]
			),
            new StringField(
				'CARD_TIME',
				[
					'title' => Loc::getMessage('CARD_TIME')
				]
			),
            new StringField(
				'PAYMENT',
				[
					'title' => Loc::getMessage('PAYMENT')
				]
			),
            new StringField(
				'NUM_ORDER',
				[
					'title' => Loc::getMessage('NUM_ORDER')
				]
			),
            new StringField(
				'SUM',
				[
					'title' => Loc::getMessage('SUM')
				]
			),
            new StringField(
				'ERROR',
				[
					'title' => Loc::getMessage('SUM')
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
}