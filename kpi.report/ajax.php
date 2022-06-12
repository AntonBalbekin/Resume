<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Engine\Controller;


class CustomAjaxController extends Controller
{



    /**
     * @param string $param2
     * @param string $param1
     * @return array
     */
    /*-- Сохранить в инфоблок --*/

    
 

    public static function getTaskInfoAction($arData)
    {
        
        $arFilter=[
            "IBLOCK_ID"=>47,
            'PROPERTY_ID_ZADACHI'=>$arData
        ];
        $arSelect=['PROPERTY_STOIMOST_CHASA_OTVETSTVENNOGO','PROPERTY_ID_ZADACHI','PROPERTY_UCHTENNOE_VREMYA'];
        $entities = CIBlockElement::GetList(array(), $arFilter, false, array(), $arSelect);
        while ($item = $entities->Fetch())
        {
            $arManey[]=['maney_for_task'=>(preg_replace("/[^0-9]/",'',$item['PROPERTY_STOIMOST_CHASA_OTVETSTVENNOGO_VALUE'])/3600)*$item['PROPERTY_UCHTENNOE_VREMYA_VALUE']];
            $arResult['info'][]=$item;

        }
        $allTime=array_sum(array_column($arResult['info'],'PROPERTY_UCHTENNOE_VREMYA_VALUE'));
        $arResult['allManeyTask']=array_sum(array_column($arManey,'maney_for_task'));
        $arResult['allTime']=sprintf('%02d:%02d:%02d', $allTime/3600, ($allTime % 3600)/60, ($allTime % 3600) % 60);
        return $arResult;
    }

    public static function savetoIblockAction($arData)
    {
        //нужна проверка на наличие элемента
        $content=$arData['content'];
        $id=$arData['id'];
        $review=$arData['review'];
        $id_list = CIBlock::GetList([],['CODE'=>'REPORT_KPI'])->Fetch();
        $el = new CIBlockElement;
        $PROP = array();
        $PROP['COMMENTS'] = $content;
        $PROP['ID_TASK'] = $id;
        $PROP['REVIEW'] = $review;


        $arLoadProductArray = Array(
            "IBLOCK_ID"      => $id_list['ID'],
            "PROPERTY_VALUES"=> $PROP,
            "NAME"           => $id,
            "ACTIVE"         => "Y",
        );
        if($PRODUCT_ID = $el->Add($arLoadProductArray)) {
            return $PRODUCT_ID;
         } else {
            return $el->LAST_ERROR;
         }
        
    }


    public function updateElemAction($arData)
    {
        global $USER;
        $filter=$arData['idElem'];
        $id_list = CIBlock::GetList([],['CODE'=>'REPORT_KPI'])->Fetch();
        $arFilter=[
            "IBLOCK_ID"=>$id_list['ID'],
            'PROPERTY_ID_TASK'=>$filter
        ];
        $arSelect=['ID'];
        $entities = CIBlockElement::GetList(array(), $arFilter, false, array(), $arSelect);
        while ($item = $entities->Fetch())
        {
            $ELEMENT_ID=$item['ID'];
            CIBlockElement::SetPropertyValues($ELEMENT_ID, $id_list['ID'], 'Да', 'UCHTENA_LI_V_ZP');
        }

        $responsoble=CUser::GetByID($arData['newElemZp']['user_id'])->Fetch();

        $id_list_Zp = CIBlock::GetList([],['CODE'=>'ZP_VZAIMOR'])->Fetch();
        $el = new CIBlockElement;
        $bonusTime=sprintf('%02d:%02d:%02d', $arData['newElemZp']['bonusTime']/3600, ($arData['newElemZp']['bonusTime'] % 3600)/60, ($arData['newElemZp']['bonusTime'] % 3600) % 60);
        $PROP = array();
        $PROP['SOTRUDNIK'] = 'Бонус к зп '.$responsoble['LAST_NAME'].' '.$responsoble['NAME'];
        $PROP['KOLICHESTVO_CHASOV'] = $bonusTime;
        $PROP['SUMMA'] = $arData['newElemZp']['bonusmaney'];
        $PROP['UTVERDIL'] = $USER->GetFullName();
        $PROP['PERIOD'] =$arData['newElemZp']['period'];
        $PROP['OTCHYET_PO_ZAKRYTYM_ZADACHAM']='';
        $PROP['PRIMECHANIE']=$arData['newElemZp']['comment'];
        $PROP['NAPRAVLENIE']='Начисление';
        $PROP['OSNOVANIE']='';
        $arLoadProductArray = Array(
            "IBLOCK_ID"      => $id_list_Zp['ID'],
            "PROPERTY_VALUES"=> $PROP,
            "NAME"           => 'Бонус к зп '.$responsoble['LAST_NAME'].' '.$responsoble['NAME'],
            "ACTIVE"         => "Y",
        );
        if($PRODUCT_ID = $el->Add($arLoadProductArray)) {
            return $PRODUCT_ID;
        }
        else
        {
            return $el->LAST_ERROR;
        }

        
       
    }

}