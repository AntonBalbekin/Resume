<div class="content-item">
    <div class="d-flex content-go-back">
        <img src="/local/templates/RenPlus/img/svg/back.svg" alt="назад">
        <a href="/callCenter/clients/">Ко всем пользователям</a>
    </div>
    
<p class="card-title"><?=$_GET['name'];?></p> 
<div class="d-flex justify-content-between client_info_head">
    <div class="client_info-blok">
        <p class="client_info-title">Общие балы</p>
        <p class="client_info-info"><?=$arResult['balans']['client_balance_out']+$arResult['balans']['client_balance']?></p>
    </div>
    <div class="client_info-blok">
        <p class="client_info-title">Внешние балы</p>
        <p class="client_info-info"><?=$arResult['balans']['client_balance_out']?></p>
    </div>
    <div class="client_info-blok">
        <p class="client_info-title">Идентификатор</p>
        <p class="client_info-info"><?=$arResult['balans']['client_ucm_id']?></p>
    </div>
    <div class="client_info-blok">
        <p class="client_info-title">Номер телефона</p>
        <p class="client_info-info"><?=$_GET['phone']?></p>
    </div>
    <div class="client_info-blok">
        <p class="client_info-title">Электронная почта</p>
        <p class="client_info-info"><?=$_GET['email']?></p>
    </div>
</div>
<div class="bals_grid">
    <div class="d-flex">
        <p class="card-title">Транзакции</p>
        <button id="add_ball" class="add_bals">Начислить баллы</button>
    </div>
    <input type="hidden" id="user_id" value="<?=$_GET['ucm-id']?>">
    <div class="grid_block">

    <?

    
    $APPLICATION->IncludeComponent('bitrix:main.ui.filter', '', [ 
        'FILTER_ID' => $arResult['grid_id'], 
        'GRID_ID'   => $arResult['grid_id'], 
        'FILTER'    => $arResult['filter'],
        'ENABLE_LIVE_SEARCH' => true, 
        'CONFIG'=>[
            'AUTOFOCUS'=>false,
        ],
        'ENABLE_LABEL' => true 
    ]);

    $APPLICATION->IncludeComponent(
        'bitrix:main.ui.grid',
        '.default',
        [
            'GRID_ID' => $arResult['grid_id'],
            'COLUMNS' => $arResult['columns'],
            'ROWS'    => $arResult['rows'],
          //  'NAV_OBJECT' => $arResult['navi'],
            'AJAX_MODE' => 'Y',
            'AJAX_ID' => \CAjax::getComponentID('bitrix:main.ui.grid', '.default', ''),
            'PAGE_SIZES' => [
                ['NAME' => "1",  'VALUE' => '1'],
                ['NAME' => "5",  'VALUE' => '5'],
                ['NAME' => '10', 'VALUE' => '10'],
                ['NAME' => '20', 'VALUE' => '20'],
                ['NAME' => '50', 'VALUE' => '50'],
                ['NAME' => '100','VALUE' => '100']
            ],
            'AJAX_OPTION_JUMP' => 'N',
            'SHOW_CHECK_ALL_CHECKBOXES' => false,
            'SHOW_ROW_CHECKBOXES' => false,
            'SHOW_ROW_ACTIONS_MENU' => true,
            'SHOW_GRID_SETTINGS_MENU' => false,
            'SHOW_NAVIGATION_PANEL' => true,
            'SHOW_PAGINATION' => true,
            'SHOW_SELECTED_COUNTER' => false,
            'SHOW_TOTAL_COUNTER' => true,
          //  'TOTAL_ROWS_COUNT' => $arResult['rows_count'],
            'SHOW_PAGESIZE' => true,
            'SHOW_ACTION_PANEL' => true,
            'ALLOW_COLUMNS_SORT' => true,
            'ALLOW_COLUMNS_RESIZE' => true,
            'ALLOW_HORIZONTAL_SCROLL' => true,
            'ALLOW_SORT' => true,
            'ALLOW_PIN_HEADER' => true,


            'AJAX_OPTION_HISTORY' => 'N',
        ]
    );
    ?>
    </div>
</div>
</div>
<?
d($arResult,$_GET);
