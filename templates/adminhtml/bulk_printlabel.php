<?php
/*
Plugin Name:  Aramex Shipping WooCommerce
Plugin URI:   https://aramex.com
Description:  Aramex Shipping WooCommerce plugin
Version:      1.0.0
Author:       aramex.com
Author URI:   https://www.aramex.com/solutions-services/developers-solutions-center
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  aramex
Domain Path:  /languages
*/
?>
<?php
        /**
         *  Render "Bulk" form
         *
         * @return string Template
         */
function aramex_display_bulk_printlabel_in_admin()
{
    $get_userdata = get_userdata(get_current_user_id());
    if (!$get_userdata->allcaps['edit_shop_order'] || !$get_userdata->allcaps['read_shop_order'] || !$get_userdata->allcaps['edit_shop_orders'] || !$get_userdata->allcaps['edit_others_shop_orders']
        || !$get_userdata->allcaps['publish_shop_orders'] || !$get_userdata->allcaps['read_private_shop_orders']
        || !$get_userdata->allcaps['edit_private_shop_orders'] || !$get_userdata->allcaps['edit_published_shop_orders']) {
        return false;
    } ?>
    

        </div>
    </div>
    <script type="text/javascript">
        jQuery.noConflict();
        (function ($) {
            $(document).ready(function () {
				$('.page-title-action').first().after("<a class=' page-title-action' style='margin-left:15px;' id='bulk_print_label'><?php echo esc_html__('Bulk Print Label',
                    'aramex'); ?> </a>");    
            });
            $(document).ready(function () {
                
                $("#bulk_print_label").click(function () {
                    aramexsend();
                });

                $("#aramex_shipment_creation_submit_id").click(function () {
                    aramexsend();
                });

                
            });

            function aramexredirect() {
                window.location.reload(true);
            }

            function aramexsend(pdfData) {
                var selected = [];
                var str = $("#massform").serialize();
                $('.type-shop_order input:checked').each(function () {
                    selected.push($(this).val());
                });
                if (selected.length === 0) {
                    alert("<?php echo esc_html__('Please select orders', 'aramex'); ?>");
                    $('.aramex_loader').css("display","none");
                  

                }else{
                    // var _wpnonce = "<?php echo esc_js(wp_create_nonce('aramex-shipment-nonce' . wp_get_current_user()->user_email)); ?>";

                    <!-- alert("Selected say(s) are: " + selected.join(", ")); -->
                    var order_ids = selected.join(", ");

                    var postData = {
            			action: 'the_aramex_bulk_printlabel',
            			bulk: "bulk_printlabel",
                        pdfData: pdfData,
                        selected_orders : order_ids,
                        _wpnonce :  "<?php echo esc_attr(wp_create_nonce('aramex-shipment-check' . wp_get_current_user()->user_email)); ?>"
            		};

                    jQuery.post(ajaxurl, postData, function(request) {

                        var responce = JSON.parse(request);
                        var pdfData = responce.file_path;

                        if(pdfData !== ''){
                    
                            success_id = responce.success_id;
                            failed_id = responce.failed_id;

                            if(success_id.length !== 0 && failed_id.length !== 0){
                                alert("Success Id's: "+responce.success_id + " Falied Id's: "+responce.failed_id);
                            }else if(success_id.length !== 0 && failed_id.length == 0){
                                alert("Success Id's: "+responce.success_id);
                            }else if(success_id.length == 0 && failed_id.length !== 0){
                                alert("Falied Id's: "+responce.failed_id);
                            }

                            window.location.href = responce.file_url;
                            
                            <!-- Repeate function call for delete generated pdf -->
                            aramexsend(pdfData);
                        }else{
                            alert("Falied Id's: "+responce.failed_id);
                        }
                        
                    });
               
            		  
                }  
            }
        })(jQuery);
    </script>
<?php 
} ?>