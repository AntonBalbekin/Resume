$(document).ready(function () {

    $('#add_ball').on('click',function(){
        BX.ajax.runComponentAction('reninsPlus:transactionClient','getPitition',{
            mode:'class',
        }).then(function(response){
            let option=`<option value="no_petition">Выбрать причину</option>`;
            $.each(response.data, function (indexInArray, valueOfElement) { 
                option+=`<option value="${valueOfElement.ID}">${valueOfElement.UF_NAME}</option>`
            });
            var content=`<div class="popup">
            <p class="HeadPopup">Начислить баллы</p>
            <div class="popup-info"> 
                <div class='popup-info_text'>
                    <p class='popup-info_text_danger'>Внимание</p>
                    <p class='popup-info_text_info'>Лимит начисления для оператора на 1 клиента равен: 1 раз в год</p>
                </div>
                <form id='petition_form'>
                    <div id ='insertContent' class='d-flex'>
                        <select class='ren-input l-56' id ='selectPetition'>${option}</select>
                    </div>
                    <textarea class='ren-input' name="" id="comment-petition" ></textarea>
                </form>
                </div>
            </div>`;
                var popup = BX.PopupWindowManager.create("popup-add-bals",null,{
                content:content,
                width:956,
                height:510,
                closeByEsc: true,
                closeIcon: {
                     opacity: 1
                },
                overlay: {
                    // объект со стилями фона
                    backgroundColor: 'black',
                    opacity: 500
                }, 
                buttons: [
                    new BX.PopupWindowButton({
                        text: 'Начислить', // текст кнопки
                        id: 'save-btn', // идентификатор
                        className: 'btn btn-ligt', // доп. классы
                        events: {
                        click: function() {
                            let sum = $('#sumPetition').val();
                            let idPetition=$('#selectPetition').val();
                            let commemtPetition=$('#comment-petition').val();
                            let ucmId=$('#user_id').val();
                            let errorValidation=0;
                            $.each(response.data, function (indexInArray, valueOfElement) { 
                                if(idPetition==valueOfElement.ID){
                                    if(valueOfElement.UF_COMMENT==1){
                                        if(commemtPetition.length==0){
                                            alert('Комментарий обязателен')
                                            errorValidation=1
                                            return
                                        }
                                    }
                                    if(Number(sum) < Number(valueOfElement.UF_FROM )){
                                        alert(`Сумма должна быть в больше ${valueOfElement.UF_FROM}`)
                                        errorValidation=1
                                        return
                                    }
                                  
                                    if(Number(sum) >  Number(valueOfElement.UF_BY)){
                                        alert(`Сумма должна быть в меньше ${valueOfElement.UF_BY}`)
                                        errorValidation=1
                                        return
                                    }
                                    if(valueOfElement.UF_STEP){
                                        if(Number(sum)%Number(valueOfElement.UF_STEP)!=0){
                                            alert(`Сумма должна быть кратна ${valueOfElement.UF_STEP}`)
                                            errorValidation=1
                                            return
                                        }
                                    }
                                }
                            });
                            if(errorValidation==1){
                                return
                            }
                            var data=[];
                            data.push(sum,idPetition,commemtPetition,ucmId);
                            console.log(data);
                            BX.ajax.runComponentAction('reninsPlus:transactionClient','addPititionScore',{
                                mode:'class',
                                data:{data}
                            }).then(function(response){
                                console.log(response)
                                if(response.data.error){
                                    alert(response.data.error)
                                }else{
                                    alert('Баллы начисленны')
                                    popup.close();
                                }
                            }),function(response){
                                console.log(response)
                            }    
                            
                        }
                        }
                    })
                ]   
                })
            popup.show(); 
            $(document).on('change','#selectPetition',function(){
                let selectId=$(this).val()
                $.each(response.data, function (indexInArray, valueOfElement) { 
                    if(selectId==valueOfElement.ID){
                        $('#sumPetition').remove();
                        $('#insertContent').append(`<input id='sumPetition' class='ren-input' type="number" step="${valueOfElement.UF_STEP}" min="${valueOfElement.UF_FROM}" max = "${valueOfElement.UF_BY}" required placeholder="сумма от ${valueOfElement.UF_FROM} до ${valueOfElement.UF_BY}">`);
                    }
                });
            })   
            
        }),function(response){
            console.log(response)
        }
        
    })
});