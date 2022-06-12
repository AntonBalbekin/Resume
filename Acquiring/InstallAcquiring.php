<?php 
namespace Wizart\Acquiring;

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

use Wizart\Acquiring\Model\AcquringTable;

class InstallAcquiring
{
    public static function installTable()
    {
       $arRes=AcquringTable::createTable();
       return $arRes;
    }
    
}

$install= InstallAcquiring::installTable();

d($install);