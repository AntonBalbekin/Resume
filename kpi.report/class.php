<?php

require_once ($_SERVER["DOCUMENT_ROOT"].'/local/php_interface/lib/Common/GridComponent.php');
require_once ($_SERVER["DOCUMENT_ROOT"].'/local/php_interface/lib/Common/Helper.php');


use \Bitrix\Main\Loader;
use Bitrix\Main\UserTable;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Tasks\Internals\TaskTable;
use Bitrix\Tasks\Internals\Task\TagTable;
use Bitrix\Tasks\Internals\Task\RelatedTable;
use Bitrix\Main\UI\Extension;

Extension::load('ui.bootstrap4');


\CJSCore::Init(array("jquery"));
\Bitrix\Main\Loader::includeModule('tasks');
\Bitrix\Main\Loader::includeModule('iblock');

Class SalesReport extends GridComponent
{
    const GRID_ID = 'sales_report-grid';
    const FILTER_ID = 'sales_report_filter';
    const IBLOCK = 45;
    const CHANGE_STAGE_FIELD = 'UF_CRM_1629290307496';

    public $todayDate;
    public $oneDayDate;
    public $arTasksId;
    public $arTasksBugsId;
    public $arEntity;
    public $sourceFilter = [];
    public $userFilter = [];





    public function __construct($component = null)
    {
        parent::__construct($component);
        $this->initialOptions(self::FILTER_ID, self::GRID_ID);

    }

    public function checkPermission()
    {
        global $USER;
        $admin = $USER->IsAdmin();

    }


    public function prepareFilter()
    {
        if (!empty($this->filter))
        {
            if (!empty($this->filter['responsible']))
            {
                $userId = substr($this->filter['responsible'], 1);
 
                $filterCRM['RESPONSIBLE_ID']=$userId;
            }
            if(!empty($this->filter['data_close_datesel']))
            {
                $filterCRM['>=CLOSED_DATE']=$this->filter['data_close_from'];
                $filterCRM['<=CLOSED_DATE']=$this->filter['data_close_to'];
            }
            if(!empty($this->filter['tag']))
            {
                $tags=[
                    '',
                    '*',
                    'Настройка',
                    'В производство',
                    'Спринт',
                ];
                $filterCRM['TAGS.NAME']=$tags[$this->filter['tag']];
            }
            if(!empty($this->filter['review']))
            {
                $reviv=['0','Да','Нет'];
                $filterIbloc['PROPERTY_REVIEW']=$reviv[$this->filter['review']];
            }
            else
            {
                $filterIbloc=[];
            }
  
        }
        else
        {
            $filterCRM=[];
        }

        $filter=[$filterCRM,$filterIbloc];
        return $filter;
    }



    public function getTasks()
    {
        $filter=$this->prepareFilter();
        $filterCRM=$filter[0];

        global $USER;
        $admin = $USER->IsAdmin();
        if(!$admin)
        {
            $filterCRM['RESPONSIBLE_ID']=$USER->GetID();
        }
        $dbasks=TaskTable::GetList(
            [
                'order'=>['ID'=>'ASC'],
                'filter'=>[
                    $filterCRM,
                    'ZOMBIE'=>'N',
                    'STATUS'=>5
     
                            ],
                'select'=>['ID',
                           'TITLE',
                           'CLOSED_DATE',
                           'RESPONSIBLE_ID',
                           'CREATED_DATE',
                           'DEADLINE',
                           'TAG'=>'TAGS.NAME',
                           'END_DATE_PLAN',
                           'MARK',
                           'PARENT_ID',
                           'DEPEND'=>'DEPENDS.DEPENDS_ON_ID',
                           'USER_NAME'=>'USERS.NAME',
                           'USER_LASTNAME'=>'USERS.LAST_NAME',
                        ],
                'runtime'=>
                [
                    new Reference(
                        'TAGS',
                         TagTable::class,
                         Join::on('this.ID','ref.TASK_ID')
                    ),
                    new Reference(
                        'DEPENDS',
                         RelatedTable::class,
                         Join::on('this.ID', 'ref.TASK_ID')
                    ),
                    new Reference(
                        'USERS',
                        UserTable::class,
                        Join::on('this.RESPONSIBLE_ID','ref.ID')
                    ),
                ]
            ]
        );

          while($rsRes =  $dbasks->Fetch())
          {
            if(array_key_exists($rsRes['ID'],$arRes))
            {
                
                $arRes[$rsRes['ID']]['TAG']=$arRes[$rsRes['ID']]['TAG'].'|'.$rsRes['TAG'];
                unset($rsRes);
            }
            else
            {  
                  $arRes[$rsRes['ID']]=$rsRes;
            }  
        } 
            foreach ($arRes as $task => $row)
            {
                if($row['PARENT_ID']> 0 && strpos($row['TAG'],'баги')!==FALSE)
                {
                    $arRes[$row['PARENT_ID']]['BAGS']++;
                }
                if($row['DEADLINE'] && $row['CLOSED_DATE'])
                {
                    if($row['CLOSED_DATE']->getTimestamp()>$row['DEADLINE']->getTimestamp())
                    {
                        $arRes[$task]['BEAD_TIME']='<p style="color:red">За рамками</p>';
                        $arRes[$task]['TIME_PERCENT']=0;
                    }
                    else
                    {
                        $arRes[$task]['BEAD_TIME']='<p style="color:green">Вложились</p>';
                        $arRes[$task]['TIME_PERCENT']=30;
                    }
                }
                else
                {
                    $arRes[$task]['BEAD_TIME']='<p style="color:green">нет дедлайна или задаче ещё не закрыта</p>';
                    $arRes[$task]['TIME_PERCENT']=0;
                }
            }


          
        return $arRes;
    }

    public function getIblockZpInfo()
    {
        $arTasks=$this->getTasks();
   
     
        $arFilter=[
            "IBLOCK_ID"=>47,
            'PROPERTY_ID_ZADACHI'=>array_keys($arTasks)
        ];
        $arSelect=['PROPERTY_STOIMOST_CHASA_OTVETSTVENNOGO','PROPERTY_ID_ZADACHI','PROPERTY_UCHTENNOE_VREMYA'];
        $entities = CIBlockElement::GetList(array(), $arFilter, false, array(), $arSelect);
        while ($item = $entities->Fetch())
        {
            foreach ($arTasks as $key => $task)
            {

                if($item['PROPERTY_ID_ZADACHI_VALUE']== $task['ID'])
                {
                    
                    $arTasks[$key]['MANEY_HOURS']=(preg_replace("/[^0-9]/",'',$item['PROPERTY_STOIMOST_CHASA_OTVETSTVENNOGO_VALUE'])/3600);
                    $arTasks[$key]['SUMM_TIME_TASK']=$item['PROPERTY_UCHTENNOE_VREMYA_VALUE'];
                    $arTasks[$key]['IN_ZP']='Да';
                }
            }
            
        }
  
        return $arTasks;
    }


    public function getIblicInfo()
    {

        $arTasks=$this->getIblockZpInfo();
        
        $arSelect=['ID', 'IBLOCK_ID', 'NAME','PROPERTY_COMMENTS','PROPERTY_ID_TASK','PROPERTY_REVIEW','PROPERTY_UCHTENA_LI_V_ZP'];
        $filter=$this->prepareFilter();
        $arFilter = ["IBLOCK_ID"=> self::IBLOCK];
        $arFilInfo=$filter[1];
        if($arFilInfo)
            {
                $arFilter=array_merge($arFilInfo,$arFilter);
            }
       
        
 
        $entities = CIBlockElement::GetList(array(), $arFilter, false, array(), $arSelect);
        while ($item = $entities->Fetch())
        {
            foreach ($arTasks as $key => $task) {
               if($task['ID']==$item['PROPERTY_ID_TASK_VALUE'])
               {
                $arTasks[$key]['COMENTS_IBLOCK'] =$item['PROPERTY_COMMENTS_VALUE'];
                $arTasks[$key]['REVIEW_IBLOCK']  =$item['PROPERTY_REVIEW_VALUE'];
                $arTasks[$key]['UCHTENA_LI_V_ZP']=$item['PROPERTY_UCHTENA_LI_V_ZP_VALUE'];
               }
            }

        }
        if($arFilInfo)
        {
            foreach ($arTasks as $key => $task)
            {
                if($task['REVIEW_IBLOCK'])
                {
                    $arResultTasks[$key]=$task;
                }
            }
            return $arResultTasks;
        }



        return $arTasks;
        
    }

    public static function setColumns()
    {
        $columns = [
            ['id' => 'TASKS', 'name' => 'ID задачи', 'sort' => 'TASKS', 'default' => true],
            ['id' => 'TASK_LINK', 'name' => 'Ссылка на задачу', 'sort' => 'TASK_LINK', 'default' => true],
            ['id' => 'BUGS', 'name' => 'Баги', 'sort' => 'BUGS', 'default' => true],
            ['id' => 'TIMING', 'name' => 'Учет сроков', 'sort' => 'TIMING', 'default' => true],
            ['id' => 'TASK_REVIEW', 'name' => 'Ревью задачи', 'sort' => 'TASK_REVIEW', 'default' => true],
            ['id' => 'TASK_MANAGEMENT', 'name' => 'Ведение задачи', 'sort' => 'TASK_MANAGEMENT', 'default' => true],
            ['id' => 'ACCOUNTING', 'name' => 'Учтена ли задача в зп', 'sort' => 'ACCOUNTING', 'default' => true],
            //['id' => 'ACCOUNTING_BONUS', 'name' => 'Учтена ли бонус в зп', 'sort' => 'ACCOUNTING_BONUS', 'default' => true],
            ['id' => 'SUMM_PERCENT', 'name' => 'Сумма процентов по полям', 'sort' => 'SUMM_PERCENT', 'default' => true],
            ['id' => 'SUMM_TIME_TASK', 'name' => 'Учтенные часы в задаче', 'sort' => 'SUMM_TIME_TASK', 'default' => true],
            ['id' => 'BONUS_TIME_TASK', 'name' => 'Количество бонусных часов', 'sort' => 'BONUS_TIME_TASK', 'default' => true],
            ['id' => 'SUMM_MANEY', 'name' => 'Cумма за задачу', 'sort' => 'SUMM_MANEY', 'default' => true],
            ['id' => 'USER', 'name' => 'Сотрудник', 'sort' => 'USER', 'default' => true],
        ];
        return $columns;
    }
    public function getRows()
    {
        $rowInfo=$this->getIblicInfo();
        if($this->filter['in_ZP']==1)
        {
            foreach ($rowInfo as $key => $row)
            {
                if(mb_strtolower($row['IN_ZP'])=='да')
                {
                    $rowInf[$key]=$row;
                }
            }

        }
        elseif ($this->filter['in_ZP']==2)
        {
            foreach ($rowInfo as $key => $row)
            {
                if(mb_strtolower($row['IN_ZP'])!='да')
                {
                    $rowInf[$key]=$row;
                }
            }
        }
        else
        {
            $rowInf=$rowInfo;
        }

        foreach ($rowInf as $key => $row)
        {  
            if($row['ID'] && mb_strtolower($row['UCHTENA_LI_V_ZP'])!='да')
            {
                if($row['BAGS']>3)
                {
                    $bags='<p style="color:red">'.$row['BAGS'].'</p>';
                    $bagsPercent=0;
                }
                else
                {
                    if(!$row['BAGS'])
                    {
                        $row['BAGS']=0;
                        $bagsPercent=30;
                    }
                    $bags='<p style="color:green">'.$row['BAGS'].'</p>';
                }
                if($row['MARK']=='P')
                {
                    $marc='<p style="color:green">Положительная</p>';
                    $marcPercent=20;
                }
                elseif ($row['MARK']=='N') {
                    $marc='<p style="color:red">Отрицательная</p>';
                    $marcPercent=0;
                }
                else
                {
                    $marc='Нет оценки';
                    $marcPercent=0;
                }

                if(mb_strtolower($row['REVIEW_IBLOCK'])=='да')
                {
                    $rewPercent=20;
                }
                else
                {
                    $rewPercent=0;
                }
                $percent=$rewPercent+$marcPercent+$bagsPercent+$row['TIME_PERCENT'];
                $time=$row['SUMM_TIME_TASK'];
                $timeTasks=sprintf('%02d:%02d:%02d', $time/3600, ($time % 3600)/60, ($time % 3600) % 60);
                
                $bonusTimeSec=(($time/100)*$percent)/2;//узнать проверить
                $bonusTime=sprintf('%02d:%02d:%02d', $bonusTimeSec/3600, ($bonusTimeSec % 3600)/60, ($bonusTimeSec % 3600) % 60);
                
                $tyTime=$row['SUMM_TIME_TASK']*$row['MANEY_HOURS'];
                $tyBonus=$bonusTimeSec*$row['MANEY_HOURS'];

                $rowForSort[]=[
                    'TASKS'          => $row['ID'],
                    'TASK_LINK'      => '<a href="/company/personal/user/'.$row['RESPONSIBLE_ID'].'/tasks/task/view/'.$row['ID'].'/"> '.$row['TITLE'].'</a>',
                    'BUGS'           => $bags,
                    'TIMING'         => $row['BEAD_TIME'],
                    'TASK_REVIEW'    => $row['REVIEW_IBLOCK'],
                    'TASK_MANAGEMENT'=>$marc,
                    'ACCOUNTING'     => $row['IN_ZP'],
                    'SUMM_PERCENT'   =>$percent,
                    'SUMM_TIME_TASK' =>$timeTasks,
                    'ID'             => $row['ID'],
                    'SUMM_MANEY'     =>'<p class="sum_mamey"> за время - '.number_format($tyTime, 2, ',', ' ').'<br> бонус - '.number_format($tyBonus, 2, ',', ' ').'<br> всего - '.(number_format($tyTime+$tyBonus, 2, ',', ' ')).'</p>',
                    'BONUS_TIME_TASK'=>'<p>'.$bonusTime.' <input class="bonus_time" type="hidden" value="'.$bonusTimeSec.'"></p>',
                    'USER'           =>'<a href="#">'.$row['RESPONSIBLE_ID'].'</a> ',
                    //'ACCOUNTING_BONUS'=>$row['UCHTENA_LI_V_ZP'],
                    'user'           =>$row['RESPONSIBLE_ID']
                ];
            }    

        }

        $nav = $this->nav;

        $nav->allowAllRecords(true)
            ->setPageSize($nav->getPageSize())
            ->initFromUri();
            if ($nav->allRecordsShown()) {
                $nav_params = false;
            } else {
                $nav_params['iNumPage'] = $nav->getCurrentPage();
            }


        $rowsBefore=$this->sort($rowForSort);
        $rsDirContent = new CDBResult;
        $rsDirContent->InitFromArray($rowsBefore);
        $pageSize = $nav->getLimit() > 0 ? $nav->getLimit()  : $rsDirContent->nSelectedCount;
        $rsDirContent->NavStart($pageSize, true, $nav->getCurrentPage());
        $nav->setRecordCount($rsDirContent->selectedRowsCount());
        for ($i=$nav->getOffset();$i<$rsDirContent->NavNext();$i++) {
            $rows[] = [
                'data'=>
                [
                    'TASKS'           => $rowsBefore[$i]['ID'],
                    'TASK_LINK'       => $rowsBefore[$i]['TASK_LINK'],
                    'BUGS'            => $rowsBefore[$i]['BUGS'],
                    'TIMING'          => $rowsBefore[$i]['TIMING'],
                    'TASK_REVIEW'     => $rowsBefore[$i]['TASK_REVIEW'],
                    'TASK_MANAGEMENT' => $rowsBefore[$i]['TASK_MANAGEMENT'],
                    'ACCOUNTING'      => $rowsBefore[$i]['ACCOUNTING'],
                    'SUMM_PERCENT'    => $rowsBefore[$i]['SUMM_PERCENT'],
                    'SUMM_TIME_TASK'  => $rowsBefore[$i]['SUMM_TIME_TASK'],
                    'ID'              => $rowsBefore[$i]['ID'],
                    'SUMM_MANEY'      => $rowsBefore[$i]['SUMM_MANEY'],
                    'BONUS_TIME_TASK' => $rowsBefore[$i]['BONUS_TIME_TASK'],
                    'USER'            => $rowsBefore[$i]['USER'],
                    //'ACCOUNTING_BONUS'=> $row['ACCOUNTING_BONUS']
                ],
                'actions' => [
                    [
                        'text'    => 'Ревью',
                        'default' => true,
                        'onclick' => 'popup('.$row['ID'].')',
                        
                    ],

                ],
                'id'=>$row['ID'],
                'depth'=>$row['user']
            ];
        }



        return $rows;
    }

    public function sort($data)
    {
        
        if ($data) {
            foreach ($this->sortOptions as $field => $typeSort) {
                return Helper::arraySort($data, $field, $typeSort);
            }
        }
        return $data;
    }

 



    public function executeComponent()
    {
        try {
            $this->checkPermission();
            $this->arResult['nav'] = $this->nav;
            $this->arResult['grid_id'] = self::GRID_ID;
            $this->arResult['filter_id'] = self::FILTER_ID;
            $this->arResult['rows']=$this->getRows();
            $this->arResult['columns']=self::setColumns();
            $this->arResult['test']=$this->getTasks();
            $this->arResult['reportZp']=$this->getIblockZpInfo();
            $this->prepareFilter();
            $this->includeComponentTemplate();
            
        } catch (\Bitrix\Main\SystemException $e) {
            ShowError($e->getMessage());
        }
    }
}