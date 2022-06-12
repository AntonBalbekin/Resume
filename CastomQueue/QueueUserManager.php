<?php

namespace Wizart\CastomQueue;

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main\Loader;
use Wizart\CastomQueue\Model\UserQueueTable;
use Wizart\CastomQueue\Model\DepartmentPriorityTable;
use Bitrix\ImOpenLines\Model\SessionTable;
Loader::includeModule('timeman');


class QueueUserManager
{

    public static function get_user_departament($operatorsWork=1)
    {
        
        $par=[
            'select'=>['UF_DEPARTMENT','PRIORITY_ID'],
            'filter'=>[]
        ];
        $dep=DepartmentPriorityTable::GetList($par)->fetchAll();
        foreach ($dep as  $v) {
            $dbUserDep=\CIntranetUtils::GetDepartmentEmployees($v['UF_DEPARTMENT'],false,false,'Y',['ID']);
            while($rsUserDep=$dbUserDep->Fetch())
            {
                $UserDep[]=$rsUserDep['ID'];
            }
            $arUserDep[$v['PRIORITY_ID']]=$UserDep;
            unset($UserDep);
        }
        foreach ($arUserDep as $key => $value) {
            
            foreach ($value as $k =>$v) {
                $TimemanUser = new \CTimeManUser($v);
                if($operatorsWork==1)
                {
                    if($TimemanUser->isDayOpen() && !$TimemanUser->isDayExpired())
                    {
                        $operatorInWork[] = $v;
                    }
                }
                else
                {
                    $operatorInWork[] = $v;
                }    
               
            }
            $operatorDepWorc[$key]=$operatorInWork;
            unset($operatorInWork);
        }
        
        foreach ($operatorDepWorc as $key => $value) {
            foreach ($value as  $v) {
                $result[]=['ID'=>$v,'UF_PRIORITY_CHAT'=>[$key]];
            }
            
        }
        return $result;
        
    }

    public static function get_users_operators($operatorsWork=1)
    {
        //нужны будут юзеры из приоритетов
 
        

        $params=[
            'select'=>['USER_ID'],
            'filter'=>[]
        ];
        $arOperator = UserQueueTable::GetList($params)->fetchAll();

        if($operatorsWork==1)
        {
            $userOperatorWork=self::user_work($arOperator);
            $usdep=self::get_user_departament(1);
            d($userOperatorWork);
            d($usdep);
            if($usdep)
            {
                foreach($usdep as $v)
                {
                    $resUsDep[]=$v['ID'];
                }
            }
            if($userOperatorWork && $usdep)
            {
                $result=array_merge($resUsDep,$userOperatorWork);
            }
            elseif(!$userOperatorWork && $resUsDep)
            {
                $result =$resUsDep;
            }
            elseif($userOperatorWork && !$resUsDep)
            {
                d($userOperatorWork);
                $result=$userOperatorWork;
            }
            

        }
        else
        {
           
            $usdep=self::get_user_departament(0);
            if($usdep)
            {
                foreach($usdep as $v)
                {
                    $resUsDep[]=$v['ID'];
                }
                $arOperators=array_unique(array_column($arOperator,'USER_ID'));
                if($arOperators && $resUsDep)
                {
                    $result=array_merge($resUsDep,$arOperators);
                }elseif(!$arOperators && $resUsDep)
                {
                    $result=$resUsDep;
                }elseif($arOperators && !$resUsDep)
                {
                    $result=$arOperators;
                }
                $result=array_values(array_unique($result));
            }
            else
            {
                $result=array_unique(array_column($arOperator,'USER_ID'));
            }
 
            

            
        }
        return $result;
    }

    public static function user_work($userOperator)
    {
        foreach($userOperator as $userId)
        {
            
            $TimemanUser = new \CTimeManUser($userId['USER_ID']);
            $userSettings = $TimemanUser->GetSettings();
            //!$TimemanUser->isDayPaused()
            if($TimemanUser->isDayOpen() && !$TimemanUser->isDayExpired())
            {
                $operatorInWork[] = $userId['USER_ID'];
            }
        }
               return $operatorInWork;
    }

    public function get_operators_priority()
    {
        $userOperator = self::get_users_operators(1);
        
        if($userOperator) 
        {
            $stringOperator=implode('|',$userOperator);
            $filter=[
                'ID'=>$stringOperator
            ];
            $params=[
                'SELECT'=>['UF_PRIORITY_CHAT'],
                'FIELDS'=>['ID']
            ];
            $dbUser=\CUser::GetList([],[],$filter,$params);
            while($rsUser=$dbUser->Fetch())
            {
                $arUserPriority[]=$rsUser;
            }
            $arDepUser=self::get_user_departament();
           
            if($arDepUser)
            {
                $merg=array_merge($arDepUser,$arUserPriority);
               
                $caunt=array_count_values(array_column($merg,'ID'));
                
                foreach($caunt as $key =>$value)
                {
                    if($value>1)
                    {
                        foreach($merg as $k=>$v)
                        {
                            if($v['ID']==$key)
                            {
                                $temp[$key][]=$v['UF_PRIORITY_CHAT'];
                                unset($merg[$k]);
                            }
                        }
                    }
                }
    
                foreach($temp as $k=>$v)
                {
                        $res[$k]=['ID'=>$k,'UF_PRIORITY_CHAT'=>call_user_func_array('array_merge', $v)];
                }
                if($res && $merg)
                {
                    $arUserPriority=array_merge($res,$merg);
                }elseif($res && !$merg)
                {
                    $arUserPriority=$res;
                }
                elseif(!$res && $merg)
                {
                    $arUserPriority=$merg;
                }
                
                
                
            }
            else
            {
                $arUserPriority=$arUserPriority;
            }

 
            return  $arUserPriority;
        }
        else
        {
            $arDepUser=self::get_user_departament();
            if($arDepUser)
            {
                $caunt=array_count_values(array_column($arDepUser,'ID'));
                
                foreach($caunt as $key =>$value)
                {
                    if($value>1)
                    {
                        foreach($arDepUser as $k=>$v)
                        {
                            if($v['ID']==$key)
                            {
                                $temp[$key][]=$v['UF_PRIORITY_CHAT'];
                                unset($merg[$k]);
                            }
                        }
                    }
                }
                foreach($temp as $k=>$v)
                {
                        $res[$k]=['ID'=>$k,'UF_PRIORITY_CHAT'=>call_user_func_array('array_merge', $v)];
                }
                return $res;
            }
            else
            {
                return false;
            }

        }

    }
    public static function get_caunt_user_chat()
    {
        $operatorPriority=self::get_operators_priority();
        if($operatorPriority)
        {
            $arOperator=array_column($operatorPriority,'ID');
        
            $params=
            [
                'select'=>['OPERATOR_ID'],
                'filter'=>['OPERATOR_ID'=>$arOperator,'<=STATUS'=>25]
            ];
            $sessionUserOperator=SessionTable::GetList($params)->FetchAll();
            $cauntUser=array_count_values(array_column($sessionUserOperator,'OPERATOR_ID'));
            foreach($operatorPriority as $key => $operator)
            {
                $operatorPriority[$key]['caunt']=$cauntUser[$operator['ID']];
                if($cauntUser[$operator['ID']]==null)
                {
                    $operatorPriority[$key]['caunt']=0;
                }
            }
            return $operatorPriority;
        }
        else
        {
            return 'Нет оператор';
        }

    }
}




