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
include_once __DIR__ . '../../core/class-aramex-helper.php';


$plugin_dir = WP_PLUGIN_DIR . '/aramex-shipping-woocommerce';
require_once $plugin_dir . '/vendor/autoload.php';


/**
 * Controller for Printing label
 */
class Aramex_Bulk_Printlabel_Method extends Aramex_Helper
{

    /**
     * Starting method
     *
     * @return mixed|string|void
     */
    public function run()
    {
        check_admin_referer('aramex-shipment-check' . wp_get_current_user()->user_email);
        $info = $this->getInfo(wp_create_nonce('aramex-shipment-check' . wp_get_current_user()->user_email));
        $post = $this->formatPost($_POST);

        if (!session_id()) {
            session_start();
        }

        if(isset($post['bulk'])){
            $output = array();
            $selected_orders = $post['selected_orders'];
            
            $bulk_pdf = array();
            $ordersIds = explode(",", $selected_orders);

            $upload_dir = wp_upload_dir();
            $print_label_dirname = $upload_dir['basedir'] . '/print-label';
            $print_label_uploads_url = $upload_dir['baseurl'] . '/print-label';

            $pdfData = $post['pdfData'];
            $success_pdf_ID = array();
            $failed_pdf_ID = array();
            if(empty($pdfData)){
                foreach ($ordersIds as $id) {
                    $order_id = (int)$id;
                    
                    if ($order_id) {
                        //SOAP object
                        $soapClient = new SoapClient($info['baseUrl'] . 'shipping.wsdl', array('soap_version' => SOAP_1_1));
                        $awbno = array();

                        global $wpdb;
                        $comments_table = $wpdb->prefix . 'comments';
                        $commentmeta_table = $wpdb->prefix . 'commentmeta';

                        // $order_ids = 49; // Replace with the actual order ID

                        $query = $wpdb->prepare(
                            "SELECT c.*, cm.meta_key, cm.meta_value
                            FROM $comments_table AS c
                            LEFT JOIN $commentmeta_table AS cm ON c.comment_ID = cm.comment_id
                            WHERE c.comment_post_ID = %d
                            ORDER BY c.comment_ID DESC",
                            $order_id
                        );

                        $prepared_query = $wpdb->prepare($query, $post_id);
                        $history = $wpdb->get_results($prepared_query);
                       
                        $history_list = array();
                        foreach ($history as $shipment) {
                            $history_list[] = $shipment->comment_content;
                        }

                        $aramex_return_button = false;

                        if (count($history_list)) {
                            foreach ($history_list as $history) {
                                $pos = strpos($history, 'Return');
                                if ($pos) {
                                    $aramex_return_button = true;
                                    break;
                                }
                                $awbno = strstr($history, "- Order No", true);
                                $awbno = trim($awbno, "AWB No.");
                                if ($awbno != "") {
                                    $aramex_return_button = true;
                                    break;
                                }
                            }
                        }

                        $last_track = "";
                        if (count($history_list)) {
                            foreach ($history_list as $history) {
                                $awbno = strstr($history, "- Order No", true);
                                $awbno = trim($awbno, "AWB No.");
                                if (isset($awbno)) {
                                    if ((int)$awbno) {
                                        $last_track = $awbno;
                                        break;
                                    }
                                }
                                $awbno = trim($awbno, "Aramex Shipment Return Order AWB No.");
                                if (isset($awbno)) {
                                    if ((int)$awbno) {
                                        $last_track = $awbno;
                                        break;
                                    }
                                }
                            }
                        }

                        
                        if($aramex_return_button == true){
                            
                            if ($last_track) {
                               
                                $report_id = $info['clientInfo']['report_id'];
                                if (!$report_id) {
                                    $report_id = 9729;
                                }
                                $params = array(
                                    'ClientInfo' => $info['clientInfo'],
                                    'Transaction' => array(
                                        'Reference1' => $order_id,
                                        'Reference2' => '',
                                        'Reference3' => '',
                                        'Reference4' => '',
                                        'Reference5' => '',
                                    ),
                                    'LabelInfo' => array(
                                        'ReportID' => $report_id,
                                        'ReportType' => 'URL',
                                    ),
                                );
                                $params['ShipmentNumber'] = $last_track;
                                
                                    $auth_call = $soapClient->PrintLabel($params);
                                          
                                    /* bof  PDF demaged Fixes debug */

                                    if ($auth_call->HasErrors) {

                                        array_push($failed_pdf_ID,$order_id);
                                        continue;

                                    }
                                    /* eof  PDF demaged Fixes */
                                    
                                    $filepath = $auth_call->ShipmentLabel->LabelURL;

                                    if (!file_exists($print_label_dirname)) {
                                        mkdir($print_label_dirname, 0777, true);
                                    }

                                    $time = time();
                                    $pdf_filename_by_order_id = $print_label_dirname . "/".$order_id . "_" .$time.".pdf";
                              
                                    file_put_contents($pdf_filename_by_order_id, fopen($filepath, 'r'));
                                    
                                    array_push($bulk_pdf, $pdf_filename_by_order_id);
                                    array_push($success_pdf_ID,$order_id);

                            }
                        }else{
                            array_push($failed_pdf_ID,$order_id);
                        }

                    } else {
                        $this->aramex_errors()->add('error', 'This order no longer exists.');
                        $_SESSION['aramex_errors_printlabel'] = $this->aramex_errors();
                        wp_redirect(sanitize_text_field(esc_url_raw($_POST['aramex_shipment_referer'])) . '&aramexpopup/show_printlabel');
                        exit();
                    }
                }

                // create merger instance
                $pdf = new \Jurosh\PDFMerge\PDFMerger;
                foreach ($bulk_pdf as $item) {
                    $pdf->addPDF($item, 'all', 'vertical');
                }
                
                $merge_pdf_path = '';
                if(!empty($bulk_pdf)){
                    $time = time();
                    $pdf_name = $time.'.pdf';
                    $pdf->merge('file', $print_label_dirname.'/'.$pdf_name);
                    $merge_pdf = $print_label_uploads_url.'/'.$pdf_name;
                    $merge_pdf_path = $print_label_dirname.'/'.$pdf_name;
                }
                
                foreach ($bulk_pdf as $item) {
                    unlink($item);
                }

                $output = array(
                  "file_url" => $merge_pdf,
                  "file_path" => $merge_pdf_path,
                  "success_id"=> $success_pdf_ID,
                  "failed_id"=> $failed_pdf_ID,
                  "sucess" => false
                );
            }else{
                unlink($pdfData);
                
                $output = array(
                    "file_url" => '',
                    "file_path" => '',
                    "success_id"=> $success_pdf_ID,
                    "failed_id"=> $failed_pdf_ID,
                    "sucess" => true
                );
            }

            echo json_encode($output);
        }
        
       die();
    }

    /**
     * Get errors
     *
     * @return WP_Error  WP Errors
     */
    public function aramex_errors()
    {
        static $wp_error; // Will hold global variable safely
        return isset($wp_error) ? $wp_error : ($wp_error = new WP_Error(null, null, null));
    }
}