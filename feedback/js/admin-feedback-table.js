(function($){
    $(document).ready(function(){
        
        $(document).on("click", ".more-details-link", function (event) {
            event.preventDefault();
        
            var itemId = $(this).data("id");
        
                  
                   if ($("#popup-box").length === 0) {
                       $("body").append(`<div id="popup-box" class="popup-container"> <div class="popup-content">
                         <select id="popup-select">
                              <option value="default" selected>Server Info</option>
                                <option value="plugin">Plugins Info</option>
                             <option value="theme">Themes Info</option>
                          </select>
                
                           <div id="table-wrapper">
                              <table id="table-container">
                                 <tr>
                                     <td id="loader-cell"  style=" text-align: center;">
                                        <div class="cpfm-loader"></div>
                                   </td>
                                </tr>
                                </table>
                            </div>
        
                          <button id="close-popup">Close</button>
                           </div></div>`); 
                    } else {
                     $("#popup-box").show();
                    }

                        $(document).off("change").on("change", "#popup-select", function () {
                            let selectedValue = $(this).val();
                            sendAjaxRequest(selectedValue, itemId);
                        });
                        let defaultSelectedValue = $("#popup-select").val();
                        sendAjaxRequest(defaultSelectedValue, itemId);
        });     
        
        function sendAjaxRequest(selectedValue, itemId) {
            $.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "cpfm_get_extra_data",
                    value: selectedValue,
                    item_id: itemId,
                    nonce: ajax_object.nonce,
                },
                success: function (response) {

                        let data = JSON.parse(response);
                        $('#table-container').html('');
                        $("#table-container").html(data.html);
                  
                },
                error: function (error) {
                    console.log("AJAX Error:", error);
                },
            });
        }
        
        $(document).on('click', function(event) {

            if($(event.target).is('#close-popup') || !$(event.target).closest('#popup-box, .more-details-link').length){
                $('#popup-box').fadeOut(300, function () {
                    $(this).remove();
                });
            }            
        });
        
    });
})(jQuery);
