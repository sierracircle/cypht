$(document).ajaxSuccess(function(event, xhr, settings) {
    var response = jQuery.parseJSON(xhr.responseText);
    if (response.module_debug) {
        $(".module_list").html(response.module_debug);
    }
    if (response.hm3_debug) {
        $(".hm3_debug").html(response.hm3_debug);
    }
    if (response.imap_summary_debug) {
        $(".hm3_imap_debug").html(response.imap_summary_debug);
    }
});
