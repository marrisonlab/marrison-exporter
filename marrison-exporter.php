<?php
/**
 * Plugin Name: Marrison Exporter
 * Plugin URI: https://marrison.com/
 * Description: Plugin per esportare gli ordini di WooCommerce in formato CSV con selezione di date e colonne.
 * Version: 1.1.0
 * Author: Marrison
 * Author URI: https://marrison.com/
 * License: GPL v2 or later
 * Text Domain: marrison-exporter
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 3.0
 * WC tested up to: 9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MARRISON_EXPORTER_VERSION', '1.1.0');
define('MARRISON_EXPORTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MARRISON_EXPORTER_PLUGIN_URL', plugin_dir_url(__FILE__));

class Marrison_Exporter {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'handle_export'));
        
        // Dichiara compatibilità con WooCommerce HPOS
        add_action('before_woocommerce_init', array($this, 'declare_wc_compatibility'));
    }
    
    /**
     * Dichiara compatibilità con WooCommerce HPOS (High-Performance Order Storage)
     */
    public function declare_wc_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        }
    }
    
    public function add_admin_menu() {
        // Aggiungi menu principale se WooCommerce non è attivo
        if (!class_exists('WooCommerce')) {
            add_menu_page(
                __('Marrison Exporter', 'marrison-exporter'),
                __('Marrison Exporter', 'marrison-exporter'),
                'manage_options',
                'marrison-exporter',
                array($this, 'admin_page'),
                'dashicons-export',
                30
            );
        } else {
            // Aggiungi come submenu di WooCommerce
            add_submenu_page(
                'woocommerce',
                __('Marrison Exporter', 'marrison-exporter'),
                __('Marrison Exporter', 'marrison-exporter'),
                'manage_woocommerce',
                'marrison-exporter',
                array($this, 'admin_page')
            );
        }
    }
    
    public function enqueue_admin_scripts($hook) {
        // Controlla entrambi i possibili hook
        if ('woocommerce_page_marrison-exporter' !== $hook && 'toplevel_page_marrison-exporter' !== $hook) {
            return;
        }
        
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('marrison-exporter-admin', MARRISON_EXPORTER_PLUGIN_URL . 'assets/css/admin-style.css', array(), MARRISON_EXPORTER_VERSION);
        
        wp_add_inline_style('jquery-ui-datepicker', '
            /* Stile principale datepicker */
            .ui-datepicker { 
                z-index: 9999 !important; 
                background: #ffffff !important;
                border: 1px solid #c3c4c7 !important;
                border-radius: 8px !important;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
                font-size: 13px !important;
                padding: 0 !important;
                margin-top: 2px !important;
            }
            
            /* Header datepicker */
            .ui-datepicker-header {
                background: #f6f7f7 !important;
                border: none !important;
                border-radius: 8px 8px 0 0 !important;
                color: #1d2327 !important;
                font-weight: 600 !important;
                padding: 12px 16px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
            }
            
            .ui-datepicker-title {
                color: #1d2327 !important;
                font-size: 14px !important;
                font-weight: 600 !important;
                margin: 0 !important;
            }
            
            .ui-datepicker-title select {
                background: #ffffff !important;
                border: 1px solid #c3c4c7 !important;
                border-radius: 4px !important;
                color: #1d2327 !important;
                padding: 4px 8px !important;
                font-size: 13px !important;
                margin: 0 4px !important;
                cursor: pointer !important;
                -webkit-appearance: menulist !important;
                -moz-appearance: menulist !important;
                appearance: menulist !important;
            }
            
            .ui-datepicker-title select option {
                background: #ffffff !important;
                color: #1d2327 !important;
                padding: 4px 8px !important;
            }
            
            /* Fix per tutti i select */
            select {
                background: #ffffff !important;
                color: #1d2327 !important;
            }
            
            select option {
                background: #ffffff !important;
                color: #1d2327 !important;
            }
            
            /* Calendario */
            .ui-datepicker-calendar {
                background: #ffffff !important;
                color: #1d2327 !important;
                border: none !important;
                margin: 0 !important;
            }
            
            /* Giorni della settimana */
            .ui-datepicker th {
                background: #f6f7f7 !important;
                color: #646970 !important;
                border: none !important;
                font-weight: 600 !important;
                font-size: 11px !important;
                text-transform: uppercase !important;
                padding: 8px 4px !important;
                text-align: center !important;
            }
            
            /* Celle giorni */
            .ui-datepicker td {
                background: #ffffff !important;
                border: 1px solid #f0f0f1 !important;
                padding: 0 !important;
                text-align: center !important;
            }
            
            /* Link giorni */
            .ui-datepicker td a {
                color: #0073aa !important;
                background: #ffffff !important;
                border: none !important;
                border-radius: 4px !important;
                display: block !important;
                padding: 8px !important;
                text-decoration: none !important;
                font-weight: 500 !important;
                transition: all 0.2s ease !important;
            }
            
            .ui-datepicker td a:hover {
                background: #0073aa !important;
                color: #ffffff !important;
                transform: scale(1.05) !important;
            }
            
            /* Giorno corrente */
            .ui-datepicker td.ui-datepicker-current-day a {
                background: #0073aa !important;
                color: #ffffff !important;
                font-weight: 600 !important;
                box-shadow: 0 2px 4px rgba(0,115,170,0.3) !important;
            }
            
            /* Oggi */
            .ui-datepicker td.ui-datepicker-today a {
                background: #f6f7f7 !important;
                color: #0073aa !important;
                font-weight: 600 !important;
                border: 2px solid #0073aa !important;
            }
            
            .ui-datepicker td.ui-datepicker-today a:hover {
                background: #0073aa !important;
                color: #ffffff !important;
            }
            
            /* Freccine navigazione */
            .ui-datepicker-prev, .ui-datepicker-next {
                background: #ffffff !important;
                border: 1px solid #c3c4c7 !important;
                color: #1d2327 !important;
                width: 32px !important;
                height: 32px !important;
                border-radius: 50% !important;
                cursor: pointer !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                transition: all 0.2s ease !important;
                position: relative !important;
            }
            
            .ui-datepicker-prev:hover, .ui-datepicker-next:hover {
                background: #f6f7f7 !important;
                transform: scale(1.1) !important;
            }
            
            .ui-datepicker-prev span, .ui-datepicker-next span {
                color: #1d2327 !important;
                font-size: 0 !important;
                position: absolute !important;
                top: 50% !important;
                left: 50% !important;
                transform: translate(-50%, -50%) !important;
            }
            
            .ui-datepicker-prev span:before {
                content: "‹" !important;
                font-size: 18px !important;
                color: #1d2327 !important;
            }
            
            .ui-datepicker-next span:before {
                content: "›" !important;
                font-size: 18px !important;
                color: #1d2327 !important;
            }
            
            /* Pulsante chiudi */
            .ui-datepicker button.ui-datepicker-close {
                background: #0073aa !important;
                color: #ffffff !important;
                border: none !important;
                border-radius: 4px !important;
                padding: 8px 16px !important;
                font-size: 13px !important;
                font-weight: 500 !important;
                cursor: pointer !important;
                transition: all 0.2s ease !important;
                margin: 8px 16px 16px !important;
            }
            
            .ui-datepicker button.ui-datepicker-close:hover {
                background: #005a87 !important;
                transform: translateY(-1px) !important;
                box-shadow: 0 2px 4px rgba(0,90,135,0.3) !important;
            }
            
            /* Input field migliorato */
            .date-range-field { 
                width: 160px; 
                padding: 8px 12px;
                border: 1px solid #c3c4c7;
                border-radius: 6px;
                background: #ffffff;
                font-size: 13px;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                transition: all 0.2s ease;
                box-sizing: border-box;
            }
            
            .date-range-field:focus {
                border-color: #0073aa;
                box-shadow: 0 0 0 2px rgba(0,115,170,0.2);
                outline: none;
            }
            
            .date-range-field:hover {
                border-color: #8c8f94;
            }
            
            /* Placeholder styling */
            .date-range-field::placeholder {
                color: #646970;
                opacity: 0.8;
            }
            
            /* Stile form generale */
            .export-form { 
                margin: 20px 0; 
                background: #ffffff;
                border: 1px solid #c3c4c7;
                border-radius: 8px;
                padding: 20px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }
            
            .export-form table { 
                width: 100%; 
                border-collapse: collapse;
            }
            
            .export-form td { 
                padding: 16px 12px; 
                vertical-align: top;
            }
            
            .export-form th {
                text-align: left;
                font-weight: 600;
                color: #1d2327;
                padding: 16px 12px 16px 0;
                width: 200px;
            }
            
            .export-form input[type="checkbox"] { 
                margin-right: 8px; 
                transform: scale(1.1);
            }
            
            .export-form .submit { 
                margin-top: 24px; 
                padding-top: 20px;
                border-top: 1px solid #f0f0f1;
            }
            
            .column-selection { 
                max-height: 320px; 
                overflow-y: auto; 
                border: 1px solid #c3c4c7; 
                padding: 12px; 
                border-radius: 6px;
                background: #fafafa;
            }
            
            .column-selection label {
                display: block;
                margin-bottom: 6px;
                padding: 4px 8px;
                border-radius: 4px;
                transition: background 0.2s ease;
                cursor: pointer;
            }
            
            .column-selection label:hover {
                background: #f0f0f1;
            }
            
            .column-selection input[type="checkbox"] {
                margin-right: 8px;
            }
            
            /* Stile pulsante primario */
            .button.button-primary {
                background: #0073aa;
                border: 1px solid #0073aa;
                color: #ffffff;
                border-radius: 6px;
                padding: 10px 20px;
                font-size: 14px;
                font-weight: 500;
                transition: all 0.2s ease;
                box-shadow: 0 1px 2px rgba(0,115,170,0.3);
            }
            
            .button.button-primary:hover {
                background: #005a87;
                border-color: #005a87;
                transform: translateY(-1px);
                box-shadow: 0 2px 4px rgba(0,90,135,0.4);
            }
            
            .button.button-primary:focus {
                box-shadow: 0 0 0 2px rgba(0,115,170,0.5);
                outline: none;
            }
        ');
    }
    
    public function admin_page() {
        $logo_url = MARRISON_EXPORTER_PLUGIN_URL . 'assets/logo.svg';
        ?>
        <!-- Invisible H1 to catch WordPress notifications -->
        <h1 class="wp-heading-inline" style="display:none;"></h1>
        
        <div class="mmu-header">
            <div class="mmu-header-title">
                <div class="mmu-title-text"><?php _e('Marrison Exporter', 'marrison-exporter'); ?></div>
            </div>
            <div class="mmu-header-logo">
                <?php if (file_exists(MARRISON_EXPORTER_PLUGIN_DIR . 'assets/logo.svg')): ?>
                    <img src="<?php echo esc_url($logo_url); ?>" alt="Marrison Logo">
                <?php endif; ?>
                <a href="https://marrisonlab.com" target="_blank" class="marrison-link">Powered by Marrisonlab</a>
            </div>
        </div>
        <style>
            .mmu-header {
                height: 120px;
                background: linear-gradient(to top right, #3f2154, #11111e);
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0 40px;
                margin-bottom: 20px;
                border-radius: 4px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                color: #fff;
                box-sizing: border-box;
            }
            .mmu-header-title .mmu-title-text {
                color: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
                font-size: 28px !important;
                font-weight: 600 !important;
                line-height: 1.2 !important;
            }
            .mmu-header-logo {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                justify-content: center;
            }
            .mmu-header-logo img {
                width: 180px;
                height: auto;
                display: block;
                margin-bottom: 2px;
            }
            .marrison-link {
                color: #fd5ec0 !important;
                font-size: 11px !important;
                text-decoration: none !important;
                font-weight: 400 !important;
                font-style: italic !important;
                transition: color 0.2s ease;
            }
            .marrison-link:hover {
                color: #fff !important;
                text-decoration: underline !important;
            }
            
            /* Fix footer WordPress */
            #wpfooter {
                position: relative !important;
                margin-top: 40px !important;
            }
            
            #wpbody-content {
                padding-bottom: 65px !important;
            }
            
            /* Fix date picker z-index e posizionamento */
            .ui-datepicker {
                z-index: 99999 !important;
                position: absolute !important;
                margin-top: 2px !important;
            }
            
            .ui-datepicker:before,
            .ui-datepicker:after {
                display: none !important;
            }
        </style>
        
        <?php if (!class_exists('WooCommerce')): ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php _e('Attenzione:', 'marrison-exporter'); ?></strong>
                    <?php _e('WooCommerce non è attivo. Il plugin funzionerà ma potresti avere funzionalità limitate.', 'marrison-exporter'); ?>
                </p>
            </div>
        <?php endif; ?>
        
        <div class="mcu-wrap">
            <form method="post" action="">
                <?php wp_nonce_field('marrison_export_orders', 'marrison_export_nonce'); ?>
                
                <!-- Card Date Range -->
                <div class="mcu-card">
                    <div class="mcu-card-header">
                        <h2 class="mcu-card-title"><span class="dashicons dashicons-calendar-alt"></span> <?php _e('Periodo di Esportazione', 'marrison-exporter'); ?></h2>
                    </div>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="date_from"><?php _e('Data Inizio', 'marrison-exporter'); ?></label></th>
                            <td>
                                <input type="text" name="date_from" id="date_from" class="date-range-field" 
                                       placeholder="<?php _e('YYYY-MM-DD', 'marrison-exporter'); ?>" 
                                       value="<?php echo isset($_POST['date_from']) ? esc_attr($_POST['date_from']) : ''; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="date_to"><?php _e('Data Fine', 'marrison-exporter'); ?></label></th>
                            <td>
                                <input type="text" name="date_to" id="date_to" class="date-range-field" 
                                       placeholder="<?php _e('YYYY-MM-DD', 'marrison-exporter'); ?>" 
                                       value="<?php echo isset($_POST['date_to']) ? esc_attr($_POST['date_to']) : ''; ?>">
                                <p class="description">
                                    <?php _e('Lascia vuoto per esportare tutti gli ordini senza filtro temporale.', 'marrison-exporter'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Card Colonne -->
                <div class="mcu-card">
                    <div class="mcu-card-header">
                        <h2 class="mcu-card-title"><span class="dashicons dashicons-list-view"></span> <?php _e('Colonne da Esportare', 'marrison-exporter'); ?></h2>
                    </div>
                    <div class="mcu-columns-grid">
                        <?php
                        $available_columns = $this->get_available_columns();
                        $selected_columns = isset($_POST['export_columns']) ? $_POST['export_columns'] : array_keys($available_columns);
                        
                        foreach ($available_columns as $key => $label) {
                            $checked = in_array($key, $selected_columns) ? 'checked' : '';
                            echo '<label><input type="checkbox" name="export_columns[]" value="' . esc_attr($key) . '" ' . $checked . '> ' . esc_html($label) . '</label>';
                        }
                        ?>
                    </div>
                    <div style="margin-top: 15px;">
                        <label>
                            <input type="checkbox" id="select_all_columns"> 
                            <strong><?php _e('Seleziona/Deseleziona tutte', 'marrison-exporter'); ?></strong>
                        </label>
                    </div>
                </div>
                
                <!-- Card Impostazioni -->
                <div class="mcu-card">
                    <div class="mcu-card-header">
                        <h2 class="mcu-card-title"><span class="dashicons dashicons-admin-settings"></span> <?php _e('Impostazioni Avanzate', 'marrison-exporter'); ?></h2>
                    </div>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="batch_size"><?php _e('Dimensione Batch', 'marrison-exporter'); ?></label></th>
                            <td>
                                <input type="number" name="batch_size" id="batch_size" value="50" min="10" max="200" step="10" class="small-text">
                                <p class="description">
                                    <?php _e('Numero di ordini processati per batch. Riduci se hai problemi di memoria.', 'marrison-exporter'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Submit Button -->
                <div style="margin-top: 20px;">
                    <button type="submit" name="export_orders" class="mcu-button mcu-button-primary">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Esporta Ordini in CSV', 'marrison-exporter'); ?>
                    </button>
                    <p class="description" style="margin-top: 10px;">
                        <?php _e('Per grandi quantità di dati, l\'esportazione potrebbe richiedere alcuni minuti.', 'marrison-exporter'); ?>
                    </p>
                </div>
            </form>
        </div>
            
            <script>
            jQuery(document).ready(function($) {
                // Date picker
                $('#date_from, #date_to').datepicker({
                    dateFormat: 'yy-mm-dd',
                    changeMonth: true,
                    changeYear: true
                });
                
                // Select all/deselect all columns
                $('#select_all_columns').on('change', function() {
                    var checked = $(this).prop('checked');
                    $('.column-selection input[type="checkbox"]').prop('checked', checked);
                });
            });
            </script>
        </div>
        <?php
    }
    
    private function get_available_columns() {
        return array(
            'order_id' => __('ID Ordine', 'marrison-exporter'),
            'order_number' => __('Numero Ordine', 'marrison-exporter'),
            'order_status' => __('Stato Ordine', 'marrison-exporter'),
            'order_date' => __('Data Ordine', 'marrison-exporter'),
            'customer_name' => __('Nome Cliente', 'marrison-exporter'),
            'customer_email' => __('Email Cliente', 'marrison-exporter'),
            'customer_phone' => __('Telefono Cliente', 'marrison-exporter'),
            'billing_address' => __('Indirizzo Fatturazione', 'marrison-exporter'),
            'shipping_address' => __('Indirizzo Spedizione', 'marrison-exporter'),
            'payment_method' => __('Metodo Pagamento', 'marrison-exporter'),
            'shipping_method' => __('Metodo Spedizione', 'marrison-exporter'),
            'order_total' => __('Totale Ordine', 'marrison-exporter'),
            'order_subtotal' => __('Subtotale', 'marrison-exporter'),
            'order_tax' => __('Tasse', 'marrison-exporter'),
            'order_shipping' => __('Spedizione', 'marrison-exporter'),
            'order_discount' => __('Sconto', 'marrison-exporter'),
            'products' => __('Prodotti', 'marrison-exporter'),
            'quantity' => __('Quantità', 'marrison-exporter'),
            'product_price' => __('Prezzo Prodotto', 'marrison-exporter'),
            'order_notes' => __('Note Ordine', 'marrison-exporter'),
        );
    }
    
    public function handle_export() {
        if (!isset($_POST['export_orders']) || !isset($_POST['marrison_export_nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['marrison_export_nonce'], 'marrison_export_orders')) {
            wp_die(__('Security check failed', 'marrison-exporter'));
        }
        
        // Controlla permessi appropriati
        $required_capability = class_exists('WooCommerce') ? 'manage_woocommerce' : 'manage_options';
        if (!current_user_can($required_capability)) {
            wp_die(__('You do not have sufficient permissions', 'marrison-exporter'));
        }
        
        $date_from = !empty($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = !empty($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        $export_columns = isset($_POST['export_columns']) ? array_map('sanitize_text_field', $_POST['export_columns']) : array();
        $batch_size = isset($_POST['batch_size']) ? absint($_POST['batch_size']) : 50;
        
        // Valida batch size
        if ($batch_size < 10 || $batch_size > 200) {
            $batch_size = 50;
        }
        
        if (empty($export_columns)) {
            wp_die(__('Seleziona almeno una colonna da esportare', 'marrison-exporter'));
        }
        
        $this->export_orders_to_csv($date_from, $date_to, $export_columns, $batch_size);
    }
    
    private function export_orders_to_csv($date_from, $date_to, $columns, $batch_size = 50) {
        // Aumenta limite memoria temporaneamente
        @ini_set('memory_limit', '512M');
        @set_time_limit(300); // 5 minuti
        
        $filename = 'marrison_export_orders_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        
        $output = fopen('php://output', 'w');
        
        // UTF-8 BOM per compatibilità Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Header columns
        $headers = array();
        foreach ($columns as $column) {
            $available_columns = $this->get_available_columns();
            $headers[] = isset($available_columns[$column]) ? $available_columns[$column] : $column;
        }
        fputcsv($output, $headers);
        
        // Esporta ordini in batch per evitare esaurimento memoria
        $this->export_orders_batch($output, $date_from, $date_to, $columns, $batch_size);
        
        fclose($output);
        exit;
    }
    
    private function export_orders_batch($output, $date_from, $date_to, $columns, $batch_size) {
        $page = 1;
        $has_orders = true;
        
        while ($has_orders) {
            $args = array(
                'status' => array_keys(wc_get_order_statuses()),
                'limit' => $batch_size,
                'page' => $page,
                'orderby' => 'date',
                'order' => 'DESC',
                'return' => 'objects',
            );
            
            if (!empty($date_from)) {
                $args['date_created'] = $date_from . '...';
            }
            
            if (!empty($date_to)) {
                if (!empty($date_from)) {
                    $args['date_created'] = $date_from . '...' . $date_to;
                } else {
                    $args['date_created'] = '...' . $date_to;
                }
            }
            
            $orders = wc_get_orders($args);
            
            if (empty($orders)) {
                $has_orders = false;
                break;
            }
            
            // Processa questo batch
            foreach ($orders as $order) {
                $row = array();
                
                foreach ($columns as $column) {
                    $value = $this->get_order_field_value($order, $column);
                    // Pulisci il valore per evitare problemi con il CSV
                    $value = $this->sanitize_csv_field($value);
                    $row[] = $value;
                }
                
                fputcsv($output, $row);
            }
            
            // Libera memoria
            unset($orders);
            
            $page++;
            
            // Forza garbage collection ogni 10 batch
            if ($page % 10 === 0) {
                gc_collect_cycles();
            }
        }
    }
    
    private function sanitize_csv_field($value) {
        if ($value === null || $value === '') {
            return '';
        }
        
        // Converti in stringa
        $value = (string) $value;
        
        // Rimuovi caratteri di controllo che potrebbero rompere il CSV
        $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
        
        // Normalizza i caratteri speciali
        $value = htmlspecialchars_decode($value, ENT_QUOTES);
        
        return $value;
    }
    
    private function get_order_field_value($order, $field) {
        switch ($field) {
            case 'order_id':
                return $order->get_id();
                
            case 'order_number':
                return $order->get_order_number();
                
            case 'order_status':
                return wc_get_order_status_name($order->get_status());
                
            case 'order_date':
                return $order->get_date_created()->format('Y-m-d H:i:s');
                
            case 'customer_name':
                return $order->get_formatted_billing_full_name();
                
            case 'customer_email':
                return $order->get_billing_email();
                
            case 'customer_phone':
                return $order->get_billing_phone();
                
            case 'billing_address':
                $address = $order->get_formatted_billing_address();
                return str_replace(array('<br/>', "\n", "\r"), array(', ', ' ', ' '), $address);
                
            case 'shipping_address':
                $address = $order->get_formatted_shipping_address();
                return str_replace(array('<br/>', "\n", "\r"), array(', ', ' ', ' '), $address);
                
            case 'payment_method':
                return $order->get_payment_method_title();
                
            case 'shipping_method':
                return $order->get_shipping_method();
                
            case 'order_total':
                return $order->get_total();
                
            case 'order_subtotal':
                return $order->get_subtotal();
                
            case 'order_tax':
                return $order->get_total_tax();
                
            case 'order_shipping':
                return $order->get_total_shipping();
                
            case 'order_discount':
                return $order->get_total_discount();
                
            case 'products':
                $products = array();
                foreach ($order->get_items() as $item) {
                    $products[] = $item->get_name();
                }
                return implode('; ', $products);
                
            case 'quantity':
                $quantities = array();
                foreach ($order->get_items() as $item) {
                    $quantities[] = $item->get_quantity();
                }
                return implode('; ', $quantities);
                
            case 'product_price':
                $prices = array();
                foreach ($order->get_items() as $item) {
                    $prices[] = $item->get_total();
                }
                return implode('; ', $prices);
                
            case 'order_notes':
                $notes = array();
                $order_notes = $order->get_customer_order_notes();
                foreach ($order_notes as $note) {
                    $notes[] = $note->comment_content;
                }
                return implode('; ', $notes);
                
            default:
                return '';
        }
    }
}

// Initialize plugin
new Marrison_Exporter();

// Initialize updater
require_once MARRISON_EXPORTER_PLUGIN_DIR . 'includes/class-updater.php';

// Check if WooCommerce is active
function marrison_exporter_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            ?>
            <div class="error notice">
                <p><?php _e('Marrison Exporter richiede WooCommerce per funzionare. Per favore installa e attiva WooCommerce.', 'marrison-exporter'); ?></p>
            </div>
            <?php
        });
        
        deactivate_plugins(plugin_basename(__FILE__));
    }
}

register_activation_hook(__FILE__, 'marrison_exporter_check_woocommerce');

// Hook per pulire cache aggiornamenti
register_deactivation_hook(__FILE__, function() {
    delete_transient('marrison_exporter_update_info');
});
