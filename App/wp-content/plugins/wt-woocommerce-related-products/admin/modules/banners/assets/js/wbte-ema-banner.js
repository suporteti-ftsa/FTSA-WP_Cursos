jQuery(document).ready(function($) {
    // Function to dismiss banner
    function dismissBanner($banner,$button) {
        $.ajax({
            url: wbte_ema_banner_params.ajaxurl,
            type: 'POST',
            data: {
                action: 'wbte_ema_banner_analytics_page_dismiss',
                nonce: wbte_ema_banner_params.nonce
            },
            success: function(response) {
                if (response.success) {
                    $banner.slideUp();
                }else{
                    
                    $banner.css('opacity', '1');
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                
                $banner.css('opacity', '1');
                $button.prop('disabled', false);
            }
        });
    }

    // Handle dismiss button click using event delegation
    jQuery(document).on('click', '.wbte_ema_banner_analytics_page_dismiss', function(e) {
        e.preventDefault();
   
        var $banner = jQuery(this).closest('.wbte_ema_banner_analytics_page');
        var $button = jQuery(this);
        $banner.css('opacity', '0.5');
        $button.prop('disabled', true); // Disable button to prevent multiple clicks 
        
        dismissBanner($banner, $button);
    });

});
