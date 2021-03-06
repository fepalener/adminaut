(function ($) {
    $(document).ready(function () {
        $('input').iCheck({
            checkboxClass: 'icheckbox_flat-blue',
            radioClass: 'iradio_flat-blue'
        });

        $('body').on('collapsed.pushMenu', function () {
            Cookies.set('sidebar-collapsed', true);
        }).on('expanded.pushMenu', function () {
            Cookies.remove('sidebar-collapsed');
        });

        $("form:not(.filter) :input:visible:enabled:first").focus();
        $(document).on('focus', '.select2', function (e) {
            if (e.originalEvent) {
                $(this).siblings('select').select2('open');
            }
        });
    });

    $(document).on('click', '#moduleEntityTable a.primary', function(e){
        if(e.altKey && $(this).parents('tr').find('a.edit').length > 0) {
            e.preventDefault();
            window.location = $(this).parents('tr').find('a.edit').attr('href');
        }
    });
    

})(jQuery);
