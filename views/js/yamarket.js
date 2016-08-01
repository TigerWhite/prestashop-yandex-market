$(document).ready(function() {
    $('input:radio[name="YAMARKET_EXPORT_TYPE"]').on('change', function(){
        if ($(this).val()=='1') {
            $('#vendor_params').slideDown();
        }
        else if ($(this).val()=='0') {
            $('#vendor_params').slideUp();
        }
    });
});