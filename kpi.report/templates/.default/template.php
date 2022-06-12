<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}


use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$APPLICATION->SetTitle("Отчет Kpi");




\Bitrix\UI\Toolbar\Facade\Toolbar::addFilter([
    'FILTER_ID' => $arResult['filter_id'],
    'GRID_ID' => $arResult['grid_id'],
    'FILTER' => [

        [
            'id' => 'tag', 'name' => 'Тэг','default' => true,
            'type' => 'list', 'items' => [
                                          '',
                                          '*',
                                          'Настройка',
                                          'В производство',
                                          'Спринт',
                                         ]
        ],
        [
            'id' => 'responsible', 'name' => 'Сотрудник', 'default' => true,
            'type' => 'dest_selector',
            'params' => [
                'enableDepartments' => 'Y',
                'departmentSelectDisable' => 'Y',
                'enableUsers' => 'Y',
                'siteDepartmentId' => $arResult['USER_DEPARTMENT']
            ],
        ],

        [
            'id'=>'data_close','name'=>'Дата закрытия',
            'type'=>'date'
        ],
        [
            'id'=>'review','name'=>'Ревью задачи','default' => true,
            'type' => 'list', 'items' => [
                                '',
                                'Да',
                                'Нет'
                                ]
        ],
        [
        'id'=>'in_ZP','name'=>'Учтено в ЗП','default' => true,
        'type' => 'list', 'items' => [
                                '',
                                'Да',
                                'Нет'
                                ]
        ]

    ],
    'ENABLE_LIVE_SEARCH' => true,
    'ENABLE_LABEL' => true,
]);


$download = new \Bitrix\UI\Buttons\Button([
    "color" => \Bitrix\UI\Buttons\Color::LIGHT_BORDER,
    "click" => new \Bitrix\UI\Buttons\JsCode(
        'document.location.href="/local/exportSales/SalesReport.php"'
    ),
    "text" => 'Скачать отчет'
]);

//\Bitrix\UI\Toolbar\Facade\Toolbar::addButton($download);

$APPLICATION->IncludeComponent(
    'bitrix:main.ui.grid',
    '',
    [
        'GRID_ID' => $arResult['grid_id'],
        'COLUMNS' => $arResult['columns'],
        'ROWS' => $arResult['rows'],
        'NAV_OBJECT' => $arResult['nav'],
        'AJAX_MODE' => 'Y',
        'AJAX_ID' => \CAjax::getComponentID('bitrix:main.ui.grid', '.default', ''),
        'PAGE_SIZES' => [
            ['NAME' => "1", 'VALUE' => '1'],
            ['NAME' => "5", 'VALUE' => '5'],
            ['NAME' => '10', 'VALUE' => '10'],
            ['NAME' => '20', 'VALUE' => '20'],
            ['NAME' => '50', 'VALUE' => '50'],
            ['NAME' => '100', 'VALUE' => '100']
        ],
        'AJAX_OPTION_JUMP' => 'N',
        'SHOW_CHECK_ALL_CHECKBOXES' => true,
        'SHOW_ROW_CHECKBOXES' => true,
        'SHOW_ROW_ACTIONS_MENU' => true,
        'SHOW_GRID_SETTINGS_MENU' => true,
        'SHOW_NAVIGATION_PANEL' => true,
        'SHOW_PAGINATION' => true,
        'SHOW_SELECTED_COUNTER' => true,
        'SHOW_TOTAL_COUNTER' => true,
        'TOTAL_ROWS_COUNT' => $arResult['rows_count'],
        'SHOW_PAGESIZE' => true,
        'SHOW_ACTION_PANEL' => true,
        'ALLOW_COLUMNS_SORT' => true,
        'ALLOW_COLUMNS_RESIZE' => true,
        'ALLOW_HORIZONTAL_SCROLL' => true,
        'ALLOW_SORT' => true,
        'ALLOW_PIN_HEADER' => true,


        'AJAX_OPTION_HISTORY' => 'N',
        'ACTION_PANEL'=> [ 
            'GROUPS' => [ 
                'TYPE' => [ 
                    'ITEMS' => [ 
                        [
                            'TYPE' => \Bitrix\Main\Grid\Panel\Types::BUTTON,
                            'ID'       => 'go_to_zp',
                            'TYPE'     => 'BUTTON',
                            'TEXT'     => 'Подать в зп',
                        ],
                    ], 
                ] 
            ], 
        ],
    ]
);
?>

<script>
 


    
    function popup(el) {

        let elem=el;
        console.log(elem)
        var createNew = BX.PopupWindowManager.create(`popup-message-${elem}`, null, {
            width: 450,
            height: 350,
            titleBar:'Ревью',
            content: `
                        <textarea style="width:300px; height:150px" id="my_input">  </textarea> 
                     `,
            closeIcon: {right: "20px", top: "10px" },
            closeByEsc: true,
            events: {
                onPopupClose: function () {

                }
            },
            offsetLeft: 0,
            offsetTop: 0,
            draggable: {restrict: false},
            autoHide: true,
            buttons: [
                new BX.PopupWindowButton({
                    text: 'Положительное ревью',
                    className: "ui-btn ui-btn-success",
                    events: {click: function(){
                            var content = $('#my_input').val();
                            var yes = this.className;
                            if (yes === 'ui-btn ui-btn-success')
                            {
                                var review = 'Да';
                            }
                  

                            let arData={id: elem,
                                    content: content,
                                    review: review};
                            console.log(arData)       
                            var request = BX.ajax.runComponentAction('wizart:kpi.report', 'savetoIblock',
                            {
                                mode:'ajax',
                                data: {arData}
                            }).then(function(response){
                                
 
                                createNew.close();
                                const grid = BX.Main.gridManager.getInstanceById('sales_report-grid');

                                if (grid) {
                                grid.reloadTable();
                                }

                            },function (response) {
                                console.log(response);
                            }
                            )
 
                        }}
                }),
                new BX.PopupWindowButton({
                    text: "Отрицательное ревью" ,
                    className: "webform-button-link-cancel" ,
                    events: {click: function(){
                            var content = $('#my_input').val();
                            var no = this.className;
                            if (no === 'webform-button-link-cancel')
                            {
                                var review = 'Нет';
                            }
                            let arData={id: elem,
                                    content: content,
                                    review: review};
      
                            var request = BX.ajax.runComponentAction('wizart:kpi.report', 'savetoIblock', {
                                mode:'ajax',
                                data: {arData}
                            });
                            request.then(function(json) {
                                createNew.close();
                                const grid = BX.Main.gridManager.getInstanceById('sales_report-grid');
                                console.log(grid);
                                if (grid) {
                                grid.reloadTable();
                                }
                            });
                        }}
                })
            ]
        });
        createNew.show();
    }




</script>