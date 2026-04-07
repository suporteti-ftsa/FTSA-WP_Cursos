/*
 *  @since 1.7.5
 */
 
(function ($) {
    'use strict';

    $(function () {
        var wt_crp_upsell_banner = {
            init: function () {
                this.bannerSelector = '.wbte_crp_upsell_banner_content';
                this.initDismissButton();
                this.initCloseButton();
            },

            hideBanner: function(target){
                var $banner = $(target).closest(this.bannerSelector);
                if ($banner.length) {
                    $banner.slideUp(200);
                }
            },

            sendAjax: function(isPermanent){
                if (typeof wt_crp_upsell_banner_params === 'undefined') {
                    return;
                }
                $.post(wt_crp_upsell_banner_params.ajax_url, {
                    action: wt_crp_upsell_banner_params.action,
                    nonce: wt_crp_upsell_banner_params.nonce,
                    dismiss: isPermanent ? 1 : 0
                });
            },

            initDismissButton: function() {
                var self = this;
                $(document).on('click', '.wbte_crp_upsell_banner_dismiss', function(e) {
                    e.preventDefault();
                    self.hideBanner(this);
                    self.sendAjax(true); // permanent dismiss
                });
            },

            initCloseButton: function() {
                var self = this;
                $(document).on('click', '.wbte_crp_upsell_banner_closed', function(e) {
                    e.preventDefault();
                    self.hideBanner(this);
                    self.sendAjax(false); // hide for 7 days
                });
            }
        };

        wt_crp_upsell_banner.init();

    });
})(jQuery);