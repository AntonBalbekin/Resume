$(document).ready(function () {
    

    $(document).on('click','#go_to_zp_control',function(){
        const grid = BX.Main.gridManager.getInstanceById('sales_report-grid');
        let rows=grid.rows.rows;
        let activId=[];
        let error;
        $(rows).each(function( k,val ) {
            if($(val.node).attr('class')=='main-grid-row main-grid-row-body main-grid-row-checked')
            {   
                if($(val.node.cells[7]).find('span').text()!='Да')
                {
                    let taskId=$(val.node.cells[2]).find('span').text();
                    $(val.node.cells[7]).find('div').css({'backgroundColor' : 'red'});
                    alert(`Задача ${taskId} не добавленна в зврплатный отчёт`);
                    error=1;
                    return;
                }
                else
                {
                    error=0;
                }
                if($(val.node.cells[6]).find('span').text()=='')
                {
                    let taskId=$(val.node.cells[2]).find('span').text();
                    $(val.node.cells[6]).find('div').css({'backgroundColor' : 'red'});
                    alert(`У задача ${taskId} нет ревью`);
                    error=1;
                    return;
                }
                else
                {
                    error=0;
                }
            
                activId.push(val.checkbox.attributes.value.value)
            }
        })
        
        if(error==0)
        {
            getTaskInfo(activId);
        }
      
        
        
        
    })


    function getTaskInfo(activId)
    {
        arData=activId;
        var request = BX.ajax.runComponentAction('wizart:kpi.report', 'getTaskInfo',
        {
            mode:'ajax',
            data: {arData}
        }).then(function(response){
   
            popupZp(response);
        },function (response) {

        }
        )

    }

    function popupZp(response)
    {
        var allTime=response.data.allTime;
        var allManey=response.data.allManeyTask;
        const grid = BX.Main.gridManager.getInstanceById('sales_report-grid');

 

        var allManeyBonys=0;
        let bonusmaney;
        let bonusManeyAll=0;
        let bonusTime=0;
        $(response.data.info).each(function(key,index ) {
            let row =$(`[data-id="${index.PROPERTY_ID_ZADACHI_VALUE}"]`)[0];
            let content=$(row).find('p.sum_mamey').text().split('-');
            console.log(content)
            bonusTimeEl=$(row).find('input.bonus_time').val();
            bonusTime=Number(bonusTime)+Number(bonusTimeEl);
            bonusManeyAll=Number(bonusManeyAll)+Number(content[2].replace(/[^0-9]/g,""))
            bonusmaney=content[3];
            allManeyBonys=Number(allManeyBonys)+Number(bonusmaney)
        });
   
        var idElem=[];
        $(response.data.info).each(function(key,index){
            idElem.push(index.PROPERTY_ID_ZADACHI_VALUE)
            
            
        })

        let rows=grid.rows.rows;
        let user_ids=[];
        $(rows).each(function(k,v){
            if($(v.node).attr('class')=='main-grid-row main-grid-row-body main-grid-row-checked')
            { 
                if($(v.node.attributes[2]).val()>0)
                {
                    user_ids.push($(v.node.attributes[2]).val());
                    
                }
            }    
            
        })
        let user_id = jQuery.unique(user_ids);
        if(user_id.length>1)
        {
            alert('Разные пользователи в отчёте')
            return false;
        }
        console.log(user_id);
        var createZP = BX.PopupWindowManager.create("popup-zp", null, {
            width: 650,
            height: 400,
            content:`
            <div class='h-75'>
                <div class="d-flex h-25 m-1">
                    <div class="ui-ctl ui-ctl-textbox">
                        <p>Количество  часов</p>
                        <input value="${allTime}" type="text" class="ui-ctl-element" placeholder="Название">
                    </div>
                    <div class="ui-ctl ui-ctl-textbox">
                        <p>Сумма по часам</p>
                        <input  value="${allManey}" type="text" class="ui-ctl-element" placeholder="Название">
                    </div>
                    <div class="ui-ctl ui-ctl-textbox">
                        <p>Итоговая сумма</p>
                        <input value="${allManeyBonys}" type="text" class="ui-ctl-element" placeholder="Название">
                    </div>
                    <div class="ui-ctl ui-ctl-textbox">
                        <p>Период</p>
                        <input id='period_rep' type="text" class="ui-ctl-element" placeholder="Период">
                    </div>

                </div>
                <div class='h-25 m-1'>
                    <p>Комментарий</p>
                    <div class="ui-ctl ui-ctl-textarea">
                        <textarea id="commentPopUpZP" class="ui-ctl-element"></textarea>
                    </div>
                </div>    
            </div>    
            `,
            closeIcon: {right: "20px", top: "10px" },
            closeByEsc: true,
            draggable: {restrict: false},
            autoHide: true,
            buttons:[
                new BX.PopupWindowButton({
                    text:'Сохранить',
                    className: "ui-btn ui-btn-success",
                    events:{
                        click:function(){
                            let newElemZp={
                                'bonusmaney':bonusManeyAll,
                                'bonusTime':bonusTime,
                                'comment':$('#commentPopUpZP').val(),
                                'period': $('#period_rep').val(),
                                'user_id':user_id[0]
                                
                            }
                            console.log(newElemZp);
                            arData={
                                'idElem':idElem,
                                'newElemZp':newElemZp
                            };
                            console.log($('#commentPopUpZP').val());
                            var request = BX.ajax.runComponentAction('wizart:kpi.report', 'updateElem',
                            {
                                mode:'ajax',
                                data: {arData}
                            }).then(function(response){
                                console.log(response)
                                
                            },function (response) {
                                console.log(response)
                            }
                            )
                        }
                    }
                })
            ]

        })
        createZP.show();
    }


});