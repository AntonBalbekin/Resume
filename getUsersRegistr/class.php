<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
use Bitrix\Main\Engine\Contract\Controllerable;
use App\Domain\Renplus\Repository\ClientsRepository;
use App\Domain\Renplus\Service\Clients;


use Renplus\Helpers\HLHelpers;

\Bitrix\Main\UI\Extension::load('ui.buttons'); 
\Bitrix\Main\UI\Extension::load("ui.bootstrap4");
\CJSCore::Init(['jqery','popup']);

class getUsersRegistr extends CBitrixComponent implements Controllerable
{

    const GRID_ID = 'transaction-grid';

    const CODE_ACTION='sorry';

    public $navi;

    public $count;

    public $client;

    public function configureActions()
    {
        return 
        [
            'getPitition' => [ // Проверяем код
                'prefilters' => [],
            ],
            'addPititionScore'=>[
                'prefilters' => [],
            ]
        ];
    }

    public function checkTransaktioin($ucmId){
        $sql="SELECT ren_plus_score.ID,ren_plus_score.UF_CREATE_DATE
            FROM ren_plus_score
            LEFT JOIN ren_plus_stocks 
            ON ren_plus_score.UF_ID_STOCKS=ren_plus_stocks.ID
            WHERE ren_plus_score.UF_CLIENT_UCM_ID='$ucmId'
            AND ren_plus_stocks.UF_CODE='sorry'
            AND ren_plus_score.UF_CREATE_DATE > DATE_SUB(NOW(),INTERVAL 360 DAY)
        ";
        $connection = Bitrix\Main\Application::getConnection();
        $dbRes=$connection->query($sql);
        while ($rsRes = $dbRes->fetch()) {
            $arResult[]=$rsRes;
        }
        if($arResult){
            return['error'=>'в этог году уже начислены'];
        }else{
            return ['add'];
        }
    }

    public function getPititionAction(){
        $query=HLHelpers::getInstance()->getClassHLforName('RenPlusPetition')::query();
        $query->setSelect(['*']);
        $dbResult=$query->exec(); 
        while($rsResult=$dbResult->fetch()){
            $arResult[]=$rsResult;
        }  
        return $arResult;  
    }

    public function addPititionScoreAction($data){
        $chekTransaction=$this->checkTransaktioin($data[3]);
        if($chekTransaction['error']){
            return $chekTransaction;
        }else{
            $clients=new Clients;
            $params=[
                'code_name'=>self::CODE_ACTION,
                'client_ucm_id'=>$data[3],
                'client_show'=>false,
                'wp'=>$data[0]
             ];
            $result=$clients->addBalls($params);
            if($result['response']===0){
                $ScorePetition=HLHelpers::getInstance()->getClassHLforName('RenPlusScorePetition');
                $ScorePetition::add(['UF_PETITION_ID'=>$data[1],'UF_PETITION_COMMENT'=>$data[2],'UF_UCM_ID'=>$data[3],'UF_SCORE_ID'=>$result['id']]);
            }
        }

        return $result;
    }

    public static function setColumns()
    {
        $columns = [
            ['id' => 'ID',                  'name' => 'ID',                 'sort' => 'ID',                 'default' => true],
            ['id' => 'UF_SUM',              'name' => 'Сумма',              'sort' => 'UF_SUM',             'default' => true],
            ['id' => 'UF_CREATE_DATE',      'name' => 'Дата добавления',    'sort' => 'UF_CREATE_DATE',     'default' => true],
            ['id' => 'STOCK_NAME',          'name' => 'Название акции',     'sort' => 'STOCK_NAME',         'default' => true],
            ['id' => 'CODE_COUPONE',        'name' => 'Промокод',           'sort' => 'CODE_COUPONE',       'default' => true],
            ['id' => 'STATUS_COUPONE',      'name' => 'Статус купона',      'sort' => 'STATUS_COUPONE',     'default' => true],
            ['id' => 'UF_POLISE_ID',        'name' => 'Номер полисв',       'sort' => 'UF_POLISE_ID',       'default' => true],
            ['id' => 'UF_STATUS_SCORE',     'name' => 'Статус транзакции',  'sort' => 'UF_STATUS_SCORE',    'default' => true]

        ];
        return $columns;
    }

    public  function setfilter(){
        $arFilter=[
            ['id' => 'UF_CREATE_DATE',       'name' => 'Дата добавления','type'=>'date'], 
            ['id'=>  'UF_STATUS_CLIENT',     'name' => 'Статус',   'type'=>'list','items'=>[0=>'Начисление',1=>'Списание']],
            ['id'=>  'CODE_COUPONE',         'name'=>  'Промокод']
    ];
    return $arFilter;
    }

    public function getUserBalans()
    {
        
        $this->client=new ClientsRepository;
        $ucm=['client_ucm_id'=>$this->arParams['ucm_id']];
        $arTransaction=$this->client->getUserBalance($ucm,null,false);
        return  $arTransaction;
    }

    public function getUserTransaction()
    {
        $query=HLHelpers::getInstance()->getClassHLforName('RenPlusSCORE')::query();
        $query->setSelect(['ID','UF_SUM','UF_CREATE_DATE',
                           'UF_STATUS_SCORE','UF_CLIENT_UCM_ID',
                           'STOCK_NAME'=>'STOKTABLE.UF_NAME',
                           'OFFERS_NAME'=>'OFFERSTABLE.UF_NAME',
                           'STATUS_COUPONE'=>'COUPONETABLE.UF_STATUS_COUPON',
                           'CODE_COUPONE'=>'COUPONETABLE.UF_CODE_COUPONE'
                           ])
               ->setFilter(['UF_CLIENT_UCM_ID'=>$this->arParams['ucm_id']])
               ->registerRuntimeField(
                'STOKTABLE',
                [
                    'data_type'=>HLHelpers::getInstance()->getClassHLforName('RenPlusSTOCKS'),
                    'reference'=>['this.UF_ID_STOCKS'=>'ref.ID']
                ]
               )
               ->registerRuntimeField(
                'OFFERSTABLE',
                [
                    'data_type'=>HLHelpers::getInstance()->getClassHLforName('RenPlusPartnerOffers'),
                    'reference'=>['this.UF_ID_OFFERS'=>'ref.ID']
                ]
               )
               ->registerRuntimeField(
                'COUPONETABLE',
                [
                    'data_type'=>HLHelpers::getInstance()->getClassHLforName('RenPlusCOUPONS'),
                    'reference'=>['this.UF_CODE_COUPONE'=>'ref.ID']
                ]
               )
               ;   
        $dbResult=$query->exec(); 
        while($rsResult=$dbResult->fetch()){
            $arResult[]=$rsResult;
        }  
        return $arResult;         
    }

    public function getRows(){
        $arResult=$this->getUserTransaction();
        foreach($arResult as $key => $value){
            $rows[]=[
                'data'=>
                [
                    'ID'              => $value['ID'],
                    'UF_SUM'          => $value['UF_SUM'],
                    'UF_CREATE_DATE'  => $value['UF_CREATE_DATE'],
                    'STOCK_NAME'      => $value['STOCK_NAME'],
                    'CODE_COUPONE'    => $value['CODE_COUPONE'],
                    'STATUS_COUPONE'  => $value['STATUS_COUPONE'],
                    'UF_POLISE_ID'    => $value['UF_POLISE_ID'],
                    'UF_STATUS_SCORE' => $value['UF_STATUS_SCORE'],
                                
                ]
            ];
        }
        return $rows;
    }

    public function executeComponent()
    {
        try{
            $this->arResult['grid_id'] = self::GRID_ID;
            $this->arResult['rows']=$this->getRows();
            $this->arResult['columns']=self::setColumns();
            $this->arResult['balans']= $this->getUserBalans();
            $this->includeComponentTemplate();   
        }catch(\Bitrix\Main\SystemException $e){
            ShowError($e->getMessage());
        }
   
    }
}