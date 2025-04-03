(function($){
    $(document).ready(function(){
    
        $(document).on("click", ".more-details-link", function (event) {

            event.preventDefault();
        
            var itemId = $(this).data("id");
            
            if ($("#popup-box").length === 0) {

                $("body").append(`<div id="popup-box" class="popup-container"> <div class="popup-content">
                <div class="cpfm-loader"></div>
                </div> <button id="close-popup">Close</button></div>`);

            } else {

                $("#popup-box").show();
            }

            let defaultSelectedValue = $("#popup-select").val();
            sendAjaxRequest(defaultSelectedValue, itemId);

       
                    
        });     
        $(document).on("change", "#popup-select", function () {
            var itemId = $(this).data("id");
            let selectedValue = $(this).val();
            sendAjaxRequest(selectedValue, itemId);
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
                    $(".popup-content").html(data.html);
                  
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
