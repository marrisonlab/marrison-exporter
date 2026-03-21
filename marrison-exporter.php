<?php
/**
 * Plugin Name: Marrison Exporter
 * Plugin URI: https://marrison.com/
 * Description: Plugin per esportare gli ordini di WooCommerce in formato CSV con selezione di date e colonne.
 * Version: 1.3.0
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

define('MARRISON_EXPORTER_VERSION', '1.3.0');
define('MARRISON_EXPORTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MARRISON_EXPORTER_PLUGIN_URL', plugin_dir_url(__FILE__));

class Marrison_Exporter {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'handle_export'));
        add_action('admin_init', array($this, 'handle_scheduled_settings'));
        add_action('admin_init', array($this, 'handle_bulk_email'));
        
        // Dichiara compatibilità con WooCommerce HPOS
        add_action('before_woocommerce_init', array($this, 'declare_wc_compatibility'));
        
        // Registra cron hooks
        add_action('marrison_exporter_daily_cron', array($this, 'send_daily_export'));
        add_action('marrison_exporter_weekly_cron', array($this, 'send_weekly_export'));
        add_action('marrison_exporter_monthly_cron', array($this, 'send_monthly_export'));
        
        // Setup cron schedules
        add_action('init', array($this, 'setup_cron_schedules'));
        
        // AJAX handler for recipients preview
        add_action('wp_ajax_marrison_preview_recipients', array($this, 'ajax_preview_recipients'));
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

    private function order_matches_product_filter($order, $product_filter) {
        $filter = trim((string) $product_filter);
        if ($filter === '') {
            return true;
        }

        $filter_lc = function_exists('mb_strtolower') ? mb_strtolower($filter) : strtolower($filter);
        $filter_is_numeric = ctype_digit($filter);
        $filter_product_id = $filter_is_numeric ? absint($filter) : 0;

        foreach ($order->get_items() as $item) {
            if (!is_a($item, 'WC_Order_Item_Product')) {
                continue;
            }

            if ($filter_product_id > 0) {
                $item_product_id = absint($item->get_product_id());
                $item_variation_id = absint($item->get_variation_id());
                if ($item_product_id === $filter_product_id || $item_variation_id === $filter_product_id) {
                    return true;
                }
            }

            $product = $item->get_product();
            $sku = '';
            if ($product && is_a($product, 'WC_Product')) {
                $sku = (string) $product->get_sku();
            }

            $name = (string) $item->get_name();
            $sku_lc = function_exists('mb_strtolower') ? mb_strtolower($sku) : strtolower($sku);
            $name_lc = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);

            if ($sku !== '' && $sku_lc === $filter_lc) {
                return true;
            }

            if ($name !== '' && strpos($name_lc, $filter_lc) !== false) {
                return true;
            }
        }

        return false;
    }
    
    public function add_admin_menu() {
        // Menu principale standalone
        add_menu_page(
            __('Marrison Exporter', 'marrison-exporter'),
            __('Marrison Exporter', 'marrison-exporter'),
            'manage_options',
            'marrison-exporter',
            array($this, 'admin_page'),
            'dashicons-download',
            56
        );
        
        // Sottopagina per export manuale
        add_submenu_page(
            'marrison-exporter',
            __('Export Manuale', 'marrison-exporter'),
            __('Export Manuale', 'marrison-exporter'),
            'manage_options',
            'marrison-exporter',
            array($this, 'admin_page')
        );
        
        // Sottopagina per export schedulati
        add_submenu_page(
            'marrison-exporter',
            __('Export Schedulati', 'marrison-exporter'),
            __('Export Schedulati', 'marrison-exporter'),
            'manage_options',
            'marrison-exporter-scheduled',
            array($this, 'scheduled_page')
        );
        
        // Sottopagina per invio email massivo
        add_submenu_page(
            'marrison-exporter',
            __('Invio Email Massivo', 'marrison-exporter'),
            __('Invio Email Massivo', 'marrison-exporter'),
            'manage_options',
            'marrison-exporter-bulk-email',
            array($this, 'bulk_email_page')
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        // Controlla tutti i possibili hook del plugin
        if (strpos($hook, 'marrison-exporter') === false) {
            return;
        }
        
        wp_enqueue_script('jquery-ui-datepicker');

        if (class_exists('WooCommerce')) {
            wp_enqueue_style('woocommerce_admin_styles');
            wp_enqueue_script('wc-enhanced-select');
        }
        wp_enqueue_style('marrison-exporter-admin', MARRISON_EXPORTER_PLUGIN_URL . 'assets/css/admin-style.css', array(), MARRISON_EXPORTER_VERSION);
        
        wp_add_inline_style('marrison-exporter-admin', '');
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
                        <tr>
                            <th scope="row"><label for="product_filter"><?php _e('Filtro Prodotto', 'marrison-exporter'); ?></label></th>
                            <td>
                                <?php if (class_exists('WooCommerce')): ?>
                                    <select class="wc-product-search" style="width: 400px;" id="product_filter" name="product_filter"
                                            data-placeholder="<?php esc_attr_e('Seleziona un prodotto…', 'marrison-exporter'); ?>"
                                            data-action="woocommerce_json_search_products_and_variations"
                                            data-allow_clear="true">
                                        <?php
                                        $selected_product_id = isset($_POST['product_filter']) ? absint($_POST['product_filter']) : 0;
                                        if ($selected_product_id > 0) {
                                            $product = wc_get_product($selected_product_id);
                                            if ($product && is_a($product, 'WC_Product')) {
                                                echo '<option value="' . esc_attr($selected_product_id) . '" selected="selected">' . wp_kses_post($product->get_formatted_name()) . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                <?php else: ?>
                                    <input type="text" name="product_filter" id="product_filter" 
                                           placeholder="<?php _e('ID prodotto', 'marrison-exporter'); ?>" 
                                           value="<?php echo isset($_POST['product_filter']) ? esc_attr($_POST['product_filter']) : ''; ?>">
                                <?php endif; ?>
                                <p class="description">
                                    <?php _e('Opzionale. Esporta solo gli ordini che contengono il prodotto selezionato.', 'marrison-exporter'); ?>
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

                if ($.fn.wc_product_search) {
                    $('.wc-product-search').wc_product_search();
                }
                
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
        $product_filter = !empty($_POST['product_filter']) ? absint($_POST['product_filter']) : 0;
        $export_columns = isset($_POST['export_columns']) ? array_map('sanitize_text_field', $_POST['export_columns']) : array();
        $batch_size = isset($_POST['batch_size']) ? absint($_POST['batch_size']) : 50;
        
        // Valida batch size
        if ($batch_size < 10 || $batch_size > 200) {
            $batch_size = 50;
        }
        
        if (empty($export_columns)) {
            wp_die(__('Seleziona almeno una colonna da esportare', 'marrison-exporter'));
        }
        
        $this->export_orders_to_csv($date_from, $date_to, $product_filter, $export_columns, $batch_size);
    }
    
    private function export_orders_to_csv($date_from, $date_to, $product_filter, $columns, $batch_size = 50) {
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
        $this->export_orders_batch($output, $date_from, $date_to, $product_filter, $columns, $batch_size);
        
        fclose($output);
        exit;
    }
    
    private function export_orders_batch($output, $date_from, $date_to, $product_filter, $columns, $batch_size) {
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
                if (!empty($product_filter) && !$this->order_matches_product_filter($order, $product_filter)) {
                    continue;
                }

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
    
    /**
     * Pagina per export schedulati
     */
    public function scheduled_page() {
        $logo_url = MARRISON_EXPORTER_PLUGIN_URL . 'assets/logo.svg';
        ?>
        <h1 class="wp-heading-inline" style="display:none;"></h1>
        
        <div class="mmu-header">
            <div class="mmu-header-title">
                <div class="mmu-title-text"><?php _e('Export Schedulati', 'marrison-exporter'); ?></div>
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
            
            #wpfooter {
                position: relative !important;
                margin-top: 40px !important;
            }
            
            #wpbody-content {
                padding-bottom: 65px !important;
            }
        </style>
        
        <div class="mcu-wrap">
            <form method="post" action="">
                <?php wp_nonce_field('marrison_scheduled_settings', 'marrison_scheduled_nonce'); ?>
                
                <div class="mcu-card">
                    <div class="mcu-card-header">
                        <h2 class="mcu-card-title"><span class="dashicons dashicons-clock"></span> <?php _e('Configurazione Invio Automatico', 'marrison-exporter'); ?></h2>
                    </div>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="schedule_enabled"><?php _e('Abilita Invio Automatico', 'marrison-exporter'); ?></label></th>
                            <td>
                                <label class="mcu-switch">
                                    <input type="checkbox" name="schedule_enabled" id="schedule_enabled" value="1" <?php checked(get_option('marrison_schedule_enabled', 0), 1); ?>>
                                    <span class="mcu-slider"></span>
                                </label>
                                <p class="description"><?php _e('Attiva per abilitare l\'invio automatico via email.', 'marrison-exporter'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="schedule_frequency"><?php _e('Frequenza', 'marrison-exporter'); ?></label></th>
                            <td>
                                <select name="schedule_frequency" id="schedule_frequency" class="regular-text">
                                    <option value="daily" <?php selected(get_option('marrison_schedule_frequency', 'daily'), 'daily'); ?>><?php _e('Giornaliero (ogni giorno alle 7:00)', 'marrison-exporter'); ?></option>
                                    <option value="weekly" <?php selected(get_option('marrison_schedule_frequency', 'daily'), 'weekly'); ?>><?php _e('Settimanale (ogni lunedì alle 7:00)', 'marrison-exporter'); ?></option>
                                    <option value="monthly" <?php selected(get_option('marrison_schedule_frequency', 'daily'), 'monthly'); ?>><?php _e('Mensile (primo del mese alle 7:00)', 'marrison-exporter'); ?></option>
                                </select>
                                <p class="description">
                                    <strong><?php _e('Giornaliero:', 'marrison-exporter'); ?></strong> <?php _e('Report del giorno precedente alle 7:00', 'marrison-exporter'); ?><br>
                                    <strong><?php _e('Settimanale:', 'marrison-exporter'); ?></strong> <?php _e('Report settimana precedente ogni lunedì alle 7:00', 'marrison-exporter'); ?><br>
                                    <strong><?php _e('Mensile:', 'marrison-exporter'); ?></strong> <?php _e('Report mese precedente il primo del mese alle 7:00', 'marrison-exporter'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="schedule_email"><?php _e('Email Destinatario', 'marrison-exporter'); ?></label></th>
                            <td>
                                <input type="email" name="schedule_email" id="schedule_email" class="regular-text" value="<?php echo esc_attr(get_option('marrison_schedule_email', get_option('admin_email'))); ?>" required>
                                <p class="description"><?php _e('Indirizzo email dove ricevere i report automatici.', 'marrison-exporter'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="mcu-card">
                    <div class="mcu-card-header">
                        <h2 class="mcu-card-title"><span class="dashicons dashicons-list-view"></span> <?php _e('Colonne da Esportare', 'marrison-exporter'); ?></h2>
                    </div>
                    <div class="mcu-columns-grid">
                        <?php
                        $available_columns = $this->get_available_columns();
                        $selected_columns = get_option('marrison_schedule_columns', array_keys($available_columns));
                        
                        foreach ($available_columns as $key => $label) {
                            $checked = in_array($key, $selected_columns) ? 'checked' : '';
                            echo '<label><input type="checkbox" name="schedule_columns[]" value="' . esc_attr($key) . '" ' . $checked . '> ' . esc_html($label) . '</label>';
                        }
                        ?>
                    </div>
                    <div style="margin-top: 15px;">
                        <label>
                            <input type="checkbox" id="select_all_schedule_columns"> 
                            <strong><?php _e('Seleziona/Deseleziona tutte', 'marrison-exporter'); ?></strong>
                        </label>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" name="save_scheduled_settings" class="mcu-button mcu-button-primary">
                        <span class="dashicons dashicons-saved"></span>
                        <?php _e('Salva Impostazioni', 'marrison-exporter'); ?>
                    </button>
                </div>
            </form>
            
            <?php if (get_option('marrison_schedule_enabled', 0)): ?>
            <div class="mcu-card" style="margin-top: 20px;">
                <div class="mcu-card-header">
                    <h2 class="mcu-card-title"><span class="dashicons dashicons-info"></span> <?php _e('Stato Schedulazione', 'marrison-exporter'); ?></h2>
                </div>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Prossimo Invio:', 'marrison-exporter'); ?></th>
                        <td>
                            <?php
                            $frequency = get_option('marrison_schedule_frequency', 'daily');
                            $cron_hook = 'marrison_exporter_' . $frequency . '_cron';
                            $next_run = wp_next_scheduled($cron_hook);
                            if ($next_run) {
                                echo '<strong>' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_run) . '</strong>';
                            } else {
                                echo '<em>' . __('Non schedulato', 'marrison-exporter') . '</em>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Frequenza Attiva:', 'marrison-exporter'); ?></th>
                        <td>
                            <?php
                            $frequencies = array(
                                'daily' => __('Giornaliero', 'marrison-exporter'),
                                'weekly' => __('Settimanale', 'marrison-exporter'),
                                'monthly' => __('Mensile', 'marrison-exporter')
                            );
                            echo '<strong>' . $frequencies[$frequency] . '</strong>';
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Email Destinatario:', 'marrison-exporter'); ?></th>
                        <td><strong><?php echo esc_html(get_option('marrison_schedule_email')); ?></strong></td>
                    </tr>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#select_all_schedule_columns').on('change', function() {
                $('input[name="schedule_columns[]"]').prop('checked', this.checked);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Gestisce salvataggio impostazioni schedulazione
     */
    public function handle_scheduled_settings() {
        if (!isset($_POST['save_scheduled_settings']) || !isset($_POST['marrison_scheduled_nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['marrison_scheduled_nonce'], 'marrison_scheduled_settings')) {
            wp_die(__('Verifica di sicurezza fallita', 'marrison-exporter'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Non hai i permessi necessari', 'marrison-exporter'));
        }
        
        $enabled = isset($_POST['schedule_enabled']) ? 1 : 0;
        $frequency = sanitize_text_field($_POST['schedule_frequency']);
        $email = sanitize_email($_POST['schedule_email']);
        $columns = isset($_POST['schedule_columns']) ? array_map('sanitize_text_field', $_POST['schedule_columns']) : array();
        
        update_option('marrison_schedule_enabled', $enabled);
        update_option('marrison_schedule_frequency', $frequency);
        update_option('marrison_schedule_email', $email);
        update_option('marrison_schedule_columns', $columns);
        
        $this->setup_cron_schedules();
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Impostazioni salvate!', 'marrison-exporter') . '</p></div>';
        });
    }
    
    /**
     * Setup cron schedules
     */
    public function setup_cron_schedules() {
        $enabled = get_option('marrison_schedule_enabled', 0);
        $frequency = get_option('marrison_schedule_frequency', 'daily');
        
        wp_clear_scheduled_hook('marrison_exporter_daily_cron');
        wp_clear_scheduled_hook('marrison_exporter_weekly_cron');
        wp_clear_scheduled_hook('marrison_exporter_monthly_cron');
        
        if (!$enabled) {
            return;
        }
        
        $hook = 'marrison_exporter_' . $frequency . '_cron';
        
        if (!wp_next_scheduled($hook)) {
            $next_run = $this->get_next_run_time($frequency);
            wp_schedule_event($next_run, $frequency === 'daily' ? 'daily' : 'weekly', $hook);
        }
    }
    
    /**
     * Calcola prossimo orario esecuzione
     */
    private function get_next_run_time($frequency) {
        $timezone = new DateTimeZone(wp_timezone_string());
        $now = new DateTime('now', $timezone);
        
        switch ($frequency) {
            case 'daily':
                $next = new DateTime('tomorrow 07:00:00', $timezone);
                break;
                
            case 'weekly':
                $next = new DateTime('next monday 07:00:00', $timezone);
                if ($now->format('N') == 1 && $now->format('H') < 7) {
                    $next = new DateTime('today 07:00:00', $timezone);
                }
                break;
                
            case 'monthly':
                $next = new DateTime('first day of next month 07:00:00', $timezone);
                if ($now->format('d') == 1 && $now->format('H') < 7) {
                    $next = new DateTime('today 07:00:00', $timezone);
                }
                break;
                
            default:
                $next = new DateTime('tomorrow 07:00:00', $timezone);
        }
        
        return $next->getTimestamp();
    }
    
    /**
     * Invia export giornaliero
     */
    public function send_daily_export() {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $this->send_scheduled_export($yesterday, $yesterday, 'Giornaliero');
    }
    
    /**
     * Invia export settimanale
     */
    public function send_weekly_export() {
        $last_monday = date('Y-m-d', strtotime('last monday -7 days'));
        $last_sunday = date('Y-m-d', strtotime('last sunday'));
        $this->send_scheduled_export($last_monday, $last_sunday, 'Settimanale');
    }
    
    /**
     * Invia export mensile
     */
    public function send_monthly_export() {
        $first_day = date('Y-m-01', strtotime('last month'));
        $last_day = date('Y-m-t', strtotime('last month'));
        $this->send_scheduled_export($first_day, $last_day, 'Mensile');
    }
    
    /**
     * Funzione principale invio export schedulato
     */
    private function send_scheduled_export($date_from, $date_to, $type) {
        $email = get_option('marrison_schedule_email');
        $columns = get_option('marrison_schedule_columns', array_keys($this->get_available_columns()));
        
        if (empty($email) || empty($columns)) {
            return;
        }
        
        $csv_content = $this->generate_csv_content($date_from, $date_to, $columns);
        
        if (empty($csv_content)) {
            return;
        }
        
        $subject = sprintf(__('Report %s Ordini WooCommerce - %s', 'marrison-exporter'), $type, date_i18n(get_option('date_format')));
        $message = $this->generate_email_body($date_from, $date_to, $type);
        
        $upload_dir = wp_upload_dir();
        $temp_file = $upload_dir['basedir'] . '/marrison-export-' . time() . '.csv';
        file_put_contents($temp_file, $csv_content);
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $sent = wp_mail($email, $subject, $message, $headers, array($temp_file));
        
        @unlink($temp_file);
        
        if ($sent) {
            update_option('marrison_last_export_sent', current_time('mysql'));
        }
    }
    
    /**
     * Genera contenuto CSV
     */
    private function generate_csv_content($date_from, $date_to, $columns) {
        ob_start();
        $output = fopen('php://output', 'w');
        
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        $headers = array();
        $available_columns = $this->get_available_columns();
        foreach ($columns as $column) {
            $headers[] = isset($available_columns[$column]) ? $available_columns[$column] : $column;
        }
        fputcsv($output, $headers);
        
        $this->export_orders_batch($output, $date_from, $date_to, $columns, 50);
        
        fclose($output);
        return ob_get_clean();
    }
    
    /**
     * Genera corpo email HTML
     */
    private function generate_email_body($date_from, $date_to, $type) {
        $args = array(
            'status' => array_keys(wc_get_order_statuses()),
            'date_created' => $date_from . '...' . $date_to,
            'limit' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        );
        
        $orders = wc_get_orders($args);
        $total_orders = count(wc_get_orders(array_merge($args, array('limit' => -1, 'return' => 'ids'))));
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(to right, #3f2154, #11111e); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
                .header h1 { margin: 0; font-size: 24px; }
                .info-box { background: #f6f7f7; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
                .info-box p { margin: 5px 0; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th { background: #874abd; color: white; padding: 12px; text-align: left; }
                td { padding: 10px; border-bottom: 1px solid #ddd; }
                tr:hover { background: #f6f7f7; }
                .footer { text-align: center; color: #666; font-size: 12px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>📊 Report <?php echo esc_html($type); ?> Ordini</h1>
                </div>
                
                <div class="info-box">
                    <p><strong>Periodo:</strong> <?php echo date_i18n(get_option('date_format'), strtotime($date_from)); ?> - <?php echo date_i18n(get_option('date_format'), strtotime($date_to)); ?></p>
                    <p><strong>Totale Ordini:</strong> <?php echo $total_orders; ?></p>
                    <p><strong>Data Generazione:</strong> <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format')); ?></p>
                </div>
                
                <h2>Ultimi 10 Ordini</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Ordine</th>
                            <th>Data</th>
                            <th>Cliente</th>
                            <th>Totale</th>
                            <th>Stato</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?php echo $order->get_order_number(); ?></td>
                            <td><?php echo $order->get_date_created()->date_i18n(get_option('date_format')); ?></td>
                            <td><?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?></td>
                            <td><?php echo $order->get_formatted_order_total(); ?></td>
                            <td><?php echo wc_get_order_status_name($order->get_status()); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p><strong>📎 In allegato trovi il file CSV completo con tutti gli ordini del periodo.</strong></p>
                
                <div class="footer">
                    <p>Messaggio automatico generato da Marrison Exporter</p>
                    <p>Powered by <a href="https://marrisonlab.com" style="color: #874abd;">Marrisonlab</a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    public function bulk_email_page() {
        $logo_url = MARRISON_EXPORTER_PLUGIN_URL . 'assets/logo.svg';
        ?>
        <h1 class="wp-heading-inline" style="display:none;"></h1>
        
        <div class="mmu-header">
            <div class="mmu-header-title">
                <div class="mmu-title-text"><?php _e('Invio Email Massivo', 'marrison-exporter'); ?></div>
            </div>
            <div class="mmu-header-logo">
                <?php if (file_exists(MARRISON_EXPORTER_PLUGIN_DIR . 'assets/logo.svg')): ?>
                    <img src="<?php echo esc_url($logo_url); ?>" alt="Marrison Logo">
                <?php endif; ?>
                <a href="https://marrisonlab.com" target="_blank" class="marrison-link">Powered by Marrisonlab</a>
            </div>
        </div>
        
        <?php if (!class_exists('WooCommerce')): ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php _e('Attenzione:', 'marrison-exporter'); ?></strong>
                    <?php _e('WooCommerce non è attivo. Il plugin funzionerà ma potresti avere funzionalità limitate.', 'marrison-exporter'); ?>
                </p>
            </div>
        <?php endif; ?>
        
        <div class="mcu-wrap">
            <form method="post" action="" id="bulk-email-form">
                <?php wp_nonce_field('marrison_bulk_email', 'marrison_bulk_email_nonce'); ?>
                
                <!-- Card Filtri -->
                <div class="mcu-card">
                    <div class="mcu-card-header">
                        <h2 class="mcu-card-title"><span class="dashicons dashicons-filter"></span> <?php _e('Filtri Destinatari', 'marrison-exporter'); ?></h2>
                    </div>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="email_date_from"><?php _e('Data Inizio', 'marrison-exporter'); ?></label></th>
                            <td>
                                <input type="text" name="email_date_from" id="email_date_from" class="date-range-field" 
                                       placeholder="<?php _e('YYYY-MM-DD', 'marrison-exporter'); ?>" 
                                       value="<?php echo isset($_POST['email_date_from']) ? esc_attr($_POST['email_date_from']) : ''; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="email_date_to"><?php _e('Data Fine', 'marrison-exporter'); ?></label></th>
                            <td>
                                <input type="text" name="email_date_to" id="email_date_to" class="date-range-field" 
                                       placeholder="<?php _e('YYYY-MM-DD', 'marrison-exporter'); ?>" 
                                       value="<?php echo isset($_POST['email_date_to']) ? esc_attr($_POST['email_date_to']) : ''; ?>">
                                <p class="description">
                                    <?php _e('Lascia vuoto per includere tutti gli ordini senza filtro temporale.', 'marrison-exporter'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="email_product_filter"><?php _e('Filtro Prodotto', 'marrison-exporter'); ?></label></th>
                            <td>
                                <?php if (class_exists('WooCommerce')): ?>
                                    <select class="wc-product-search" style="width: 400px;" id="email_product_filter" name="email_product_filter"
                                            data-placeholder="<?php esc_attr_e('Seleziona un prodotto…', 'marrison-exporter'); ?>"
                                            data-action="woocommerce_json_search_products_and_variations"
                                            data-allow_clear="true">
                                        <?php
                                        $selected_product_id = isset($_POST['email_product_filter']) ? absint($_POST['email_product_filter']) : 0;
                                        if ($selected_product_id > 0) {
                                            $product = wc_get_product($selected_product_id);
                                            if ($product && is_a($product, 'WC_Product')) {
                                                echo '<option value="' . esc_attr($selected_product_id) . '" selected="selected">' . wp_kses_post($product->get_formatted_name()) . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                <?php else: ?>
                                    <input type="text" name="email_product_filter" id="email_product_filter" 
                                           placeholder="<?php _e('ID prodotto', 'marrison-exporter'); ?>" 
                                           value="<?php echo isset($_POST['email_product_filter']) ? esc_attr($_POST['email_product_filter']) : ''; ?>">
                                <?php endif; ?>
                                <p class="description">
                                    <?php _e('Opzionale. Invia solo ai clienti che hanno ordinato il prodotto selezionato.', 'marrison-exporter'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <div style="margin-top: 15px; padding: 15px; background: #f0f0f1; border-radius: 4px;">
                        <button type="button" id="preview-recipients" class="button button-secondary">
                            <span class="dashicons dashicons-visibility" style="margin-top: 3px;"></span>
                            <?php _e('Anteprima Destinatari', 'marrison-exporter'); ?>
                        </button>
                        <div id="recipients-preview" style="margin-top: 10px; display: none;">
                            <strong><?php _e('Email trovate:', 'marrison-exporter'); ?></strong>
                            <div id="recipients-list" style="max-height: 150px; overflow-y: auto; background: white; padding: 10px; margin-top: 5px; border: 1px solid #ddd; border-radius: 3px;"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Card Messaggio -->
                <div class="mcu-card">
                    <div class="mcu-card-header">
                        <h2 class="mcu-card-title"><span class="dashicons dashicons-email"></span> <?php _e('Contenuto Email', 'marrison-exporter'); ?></h2>
                    </div>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="email_subject"><?php _e('Oggetto', 'marrison-exporter'); ?></label></th>
                            <td>
                                <input type="text" name="email_subject" id="email_subject" class="regular-text" 
                                       placeholder="<?php _e('Inserisci l\'oggetto dell\'email', 'marrison-exporter'); ?>" 
                                       value="<?php echo isset($_POST['email_subject']) ? esc_attr($_POST['email_subject']) : ''; ?>" 
                                       required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="email_message"><?php _e('Messaggio', 'marrison-exporter'); ?></label></th>
                            <td>
                                <?php
                                $content = isset($_POST['email_message']) ? wp_kses_post($_POST['email_message']) : '';
                                wp_editor($content, 'email_message', array(
                                    'textarea_name' => 'email_message',
                                    'textarea_rows' => 15,
                                    'media_buttons' => true,
                                    'teeny' => false,
                                    'tinymce' => true,
                                    'quicktags' => true
                                ));
                                ?>
                                <p class="description">
                                    <?php _e('Scrivi il messaggio da inviare ai destinatari.', 'marrison-exporter'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Submit Buttons -->
                <div style="margin-top: 20px;">
                    <button type="submit" name="send_test_email" class="button button-secondary" style="margin-right: 10px;">
                        <span class="dashicons dashicons-admin-users" style="margin-top: 3px;"></span>
                        <?php _e('Invia Email di Test', 'marrison-exporter'); ?>
                    </button>
                    
                    <button type="submit" name="send_bulk_email" class="mcu-button mcu-button-primary" 
                            onclick="return confirm('<?php esc_attr_e('Sei sicuro di voler inviare l\'email a tutti i destinatari filtrati?', 'marrison-exporter'); ?>');">
                        <span class="dashicons dashicons-email-alt"></span>
                        <?php _e('Invia Email Massiva', 'marrison-exporter'); ?>
                    </button>
                    
                    <p class="description" style="margin-top: 10px;">
                        <?php _e('L\'email di test verrà inviata al tuo indirizzo email amministratore. L\'invio massivo utilizzerà il campo CCN per privacy.', 'marrison-exporter'); ?>
                    </p>
                </div>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#email_date_from, #email_date_to').datepicker({
                dateFormat: 'yy-mm-dd',
                changeMonth: true,
                changeYear: true
            });

            if ($.fn.wc_product_search) {
                $('.wc-product-search').wc_product_search();
            }
            
            $('#preview-recipients').on('click', function() {
                var dateFrom = $('#email_date_from').val();
                var dateTo = $('#email_date_to').val();
                var productFilter = $('#email_product_filter').val();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'marrison_preview_recipients',
                        date_from: dateFrom,
                        date_to: dateTo,
                        product_filter: productFilter,
                        nonce: '<?php echo wp_create_nonce('marrison_preview_recipients'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#recipients-list').html('<strong>' + response.data.count + ' email uniche trovate:</strong><br>' + response.data.emails.join('<br>'));
                            $('#recipients-preview').slideDown();
                        } else {
                            alert(response.data.message || 'Errore nel recupero dei destinatari');
                        }
                    },
                    error: function() {
                        alert('Errore nella richiesta AJAX');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    public function handle_bulk_email() {
        if ((!isset($_POST['send_test_email']) && !isset($_POST['send_bulk_email'])) || !isset($_POST['marrison_bulk_email_nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['marrison_bulk_email_nonce'], 'marrison_bulk_email')) {
            wp_die(__('Security check failed', 'marrison-exporter'));
        }
        
        $required_capability = class_exists('WooCommerce') ? 'manage_woocommerce' : 'manage_options';
        if (!current_user_can($required_capability)) {
            wp_die(__('You do not have sufficient permissions', 'marrison-exporter'));
        }
        
        $date_from = !empty($_POST['email_date_from']) ? sanitize_text_field($_POST['email_date_from']) : '';
        $date_to = !empty($_POST['email_date_to']) ? sanitize_text_field($_POST['email_date_to']) : '';
        $product_filter = !empty($_POST['email_product_filter']) ? absint($_POST['email_product_filter']) : 0;
        $subject = !empty($_POST['email_subject']) ? sanitize_text_field($_POST['email_subject']) : '';
        $message = !empty($_POST['email_message']) ? wp_kses_post($_POST['email_message']) : '';
        
        if (empty($subject) || empty($message)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Oggetto e messaggio sono obbligatori.', 'marrison-exporter') . '</p></div>';
            });
            return;
        }
        
        $emails = $this->get_filtered_customer_emails($date_from, $date_to, $product_filter);
        
        if (empty($emails)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning"><p>' . __('Nessun destinatario trovato con i filtri selezionati.', 'marrison-exporter') . '</p></div>';
            });
            return;
        }
        
        if (isset($_POST['send_test_email'])) {
            $admin_email = get_option('admin_email');
            $headers = array('Content-Type: text/html; charset=UTF-8');
            
            $test_message = '<p><strong>' . __('QUESTA È UN\'EMAIL DI TEST', 'marrison-exporter') . '</strong></p>';
            $test_message .= '<p>' . sprintf(__('Destinatari trovati: %d email uniche', 'marrison-exporter'), count($emails)) . '</p>';
            $test_message .= '<hr>';
            $test_message .= $message;
            
            if (wp_mail($admin_email, '[TEST] ' . $subject, $test_message, $headers)) {
                add_action('admin_notices', function() use ($admin_email, $emails) {
                    echo '<div class="notice notice-success"><p>' . sprintf(__('Email di test inviata a %s. Destinatari trovati: %d', 'marrison-exporter'), $admin_email, count($emails)) . '</p></div>';
                });
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>' . __('Errore nell\'invio dell\'email di test.', 'marrison-exporter') . '</p></div>';
                });
            }
        } elseif (isset($_POST['send_bulk_email'])) {
            $admin_email = get_option('admin_email');
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'Bcc: ' . implode(', ', $emails)
            );
            
            if (wp_mail($admin_email, $subject, $message, $headers)) {
                add_action('admin_notices', function() use ($emails) {
                    echo '<div class="notice notice-success"><p>' . sprintf(__('Email inviata con successo a %d destinatari in CCN.', 'marrison-exporter'), count($emails)) . '</p></div>';
                });
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>' . __('Errore nell\'invio dell\'email massiva.', 'marrison-exporter') . '</p></div>';
                });
            }
        }
    }
    
    private function get_filtered_customer_emails($date_from, $date_to, $product_filter) {
        $args = array(
            'status' => array_keys(wc_get_order_statuses()),
            'limit' => -1,
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
        $emails = array();
        
        foreach ($orders as $order) {
            if (!empty($product_filter) && !$this->order_matches_product_filter($order, $product_filter)) {
                continue;
            }
            
            $email = $order->get_billing_email();
            if (!empty($email) && is_email($email)) {
                $emails[] = strtolower(trim($email));
            }
        }
        
        return array_unique($emails);
    }
    
    public function ajax_preview_recipients() {
        check_ajax_referer('marrison_preview_recipients', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permessi insufficienti', 'marrison-exporter')));
        }
        
        $date_from = !empty($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = !empty($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        $product_filter = !empty($_POST['product_filter']) ? absint($_POST['product_filter']) : 0;
        
        $emails = $this->get_filtered_customer_emails($date_from, $date_to, $product_filter);
        
        wp_send_json_success(array(
            'count' => count($emails),
            'emails' => $emails
        ));
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
    
    // Rimuovi cron schedulati
    $timestamp = wp_next_scheduled('marrison_exporter_daily_cron');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'marrison_exporter_daily_cron');
    }
    
    $timestamp = wp_next_scheduled('marrison_exporter_weekly_cron');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'marrison_exporter_weekly_cron');
    }
    
    $timestamp = wp_next_scheduled('marrison_exporter_monthly_cron');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'marrison_exporter_monthly_cron');
    }
});
