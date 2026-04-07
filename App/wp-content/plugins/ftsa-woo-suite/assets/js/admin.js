(function($){
  $(function(){
    // Hooks futuros se necessário
    $('.ftsa-toggle-all').on('change', function(){
      var target = $(this).data('target');
      $(target).prop('checked', $(this).is(':checked'));
    });
  });
})(jQuery);

