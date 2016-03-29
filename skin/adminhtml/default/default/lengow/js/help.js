(function( $ ) {
    $(function() {

        $('#lengow-container').hide();
        $('<iframe id="lengow-iframe">', {
            id:  'lengow-iframe',
            frameborder: 0,
            scrolling: 'no'
        }).appendTo('#lengow-iframe-container');
        $('#lengow-iframe').show();

        var sync_iframe = document.getElementById('lengow-iframe');
        if (sync_iframe) {
            sync_iframe.onload = function () {
                $.ajax({
                    method: 'POST',
                    data: {action: 'get_sync_data', form_key: FORM_KEY},
                    dataType: 'json',
                    success: function (data) {
                        var targetFrame = document.getElementById("lengow-iframe").contentWindow;
                        targetFrame.postMessage(data, '*');
                    }
                });
            };
            //sync_iframe.src = 'http://cms.v3-inte.poney.io/help';
            sync_iframe.src = '/skin/adminhtml/default/default/lengow/temp/help.html';
        }
    });
})(lengow_jquery);
