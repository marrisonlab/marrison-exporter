<?php
/**
 * Plugin Name: Marrison Exporter
 * Plugin URI: https://marrison.com/
 * Description: Plugin per esportare gli ordini di WooCommerce in formato CSV con selezione di date e colonne.
 * Version: 1.0.0
 * Author: Marrison
 * Author URI: https://marrison.com/
 * License: GPL v2 or later
 * Text Domain: marrison-exporter
 * Domain Path: /languages
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MARRISON_EXPORTER_VERSION', '1.0.0');
define('MARRISON_EXPORTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MARRISON_EXPORTER_PLUGIN_URL', plugin_dir_url(__FILE__));

class Marrison_Exporter {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'handle_export'));
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
        
        wp_enqueue_style('jquery-ui-datepicker');
        wp_enqueue_script('jquery-ui-datepicker');
        
        wp_add_inline_style('jquery-ui-datepicker', '
            .ui-datepicker { 
                z-index: 1000 !important; 
                background: #fff !important;
                border: 1px solid #ddd !important;
                border-radius: 4px !important;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
            }
            .ui-datepicker-header {
                background: #f8f9fa !important;
                border: none !important;
                border-radius: 4px 4px 0 0 !important;
                color: #333 !important;
            }
            .ui-datepicker-title {
                color: #333 !important;
            }
            .ui-datepicker-calendar {
                background: #fff !important;
                color: #333 !important;
            }
            .ui-datepicker th {
                background: #f1f1f1 !important;
                color: #333 !important;
                border: none !important;
            }
            .ui-datepicker td {
                background: #fff !important;
                border: 1px solid #eee !important;
            }
            .ui-datepicker td a {
                color: #0073aa !important;
                background: #fff !important;
                border: none !important;
            }
            .ui-datepicker td a:hover {
                background: #0073aa !important;
                color: #fff !important;
            }
            .ui-datepicker td.ui-datepicker-current-day a {
                background: #0073aa !important;
                color: #fff !important;
            }
            .ui-datepicker td.ui-datepicker-today a {
                background: #f8f9fa !important;
                color: #0073aa !important;
                font-weight: bold !important;
            }
            .ui-datepicker-prev, .ui-datepicker-next {
                background: #f8f9fa !important;
                border: 1px solid #ddd !important;
                color: #333 !important;
            }
            .ui-datepicker-prev:hover, .ui-datepicker-next:hover {
                background: #e9ecef !important;
            }
            .ui-datepicker-prev span, .ui-datepicker-next span {
                color: #333 !important;
            }
            .ui-datepicker button.ui-datepicker-close {
                background: #0073aa !important;
                color: #fff !important;
                border: none !important;
                border-radius: 3px !important;
                padding: 5px 10px !important;
            }
            .ui-datepicker button.ui-datepicker-close:hover {
                background: #005a87 !important;
            }
            .date-range-field { 
                width: 150px; 
                padding: 6px 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                background: #fff;
            }
            .date-range-field:focus {
                border-color: #0073aa;
                box-shadow: 0 0 0 1px #0073aa;
            }
            .export-form { margin: 20px 0; }
            .export-form table { width: 100%; }
            .export-form td { padding: 10px; }
            .export-form input[type="checkbox"] { margin-right: 5px; }
            .export-form .submit { margin-top: 20px; }
            .column-selection { max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; }
        ');
    }
    
    public function admin_page() {
        // Mostra avviso se WooCommerce non è attivo
        if (!class_exists('WooCommerce')) {
            ?>
            <div class="wrap">
                <div class="notice notice-warning">
                    <p>
                        <strong><?php _e('Attenzione:', 'marrison-exporter'); ?></strong>
                        <?php _e('WooCommerce non è attivo. Il plugin funzionerà ma potresti avere funzionalità limitate.', 'marrison-exporter'); ?>
                    </p>
                </div>
            </div>
            <?php
        }
        
        // Mostra informazioni sulla memoria
        $memory_limit = ini_get('memory_limit');
        $memory_usage = memory_get_usage(true);
        $memory_percent = round(($memory_usage / 1024 / 1024) / (int)$memory_limit * 100, 2);
        
        ?>
        <div class="wrap">
            <h1><?php _e('Marrison Exporter - Esportazione Ordini WooCommerce', 'marrison-exporter'); ?></h1>
            
            <div class="notice notice-info">
                <p>
                    <strong><?php _e('Informazioni di sistema:', 'marrison-exporter'); ?></strong><br>
                    <?php printf(__('Memoria utilizzata: %s (%s%%)', 'marrison-exporter'), 
                        size_format($memory_usage), $memory_percent); ?><br>
                    <?php printf(__('Limite memoria: %s', 'marrison-exporter'), $memory_limit); ?>
                </p>
            </div>
            
            <div class="export-form">
                <form method="post" action="">
                    <?php wp_nonce_field('marrison_export_orders', 'marrison_export_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label><?php _e('Range di Date', 'marrison-exporter'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="date_from" id="date_from" class="date-range-field" 
                                       placeholder="<?php _e('Data inizio', 'marrison-exporter'); ?>" 
                                       value="<?php echo isset($_POST['date_from']) ? esc_attr($_POST['date_from']) : ''; ?>">
                                
                                <input type="text" name="date_to" id="date_to" class="date-range-field" 
                                       placeholder="<?php _e('Data fine', 'marrison-exporter'); ?>" 
                                       value="<?php echo isset($_POST['date_to']) ? esc_attr($_POST['date_to']) : ''; ?>">
                                
                                <p class="description">
                                    <?php _e('Seleziona un range di date per filtrare gli ordini. Lascia vuoto per esportare tutti gli ordini.', 'marrison-exporter'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label><?php _e('Colonne da Esportare', 'marrison-exporter'); ?></label>
                            </th>
                            <td>
                                <div class="column-selection">
                                    <?php
                                    $available_columns = $this->get_available_columns();
                                    $selected_columns = isset($_POST['export_columns']) ? $_POST['export_columns'] : array_keys($available_columns);
                                    
                                    foreach ($available_columns as $key => $label) {
                                        $checked = in_array($key, $selected_columns) ? 'checked' : '';
                                        echo '<label><input type="checkbox" name="export_columns[]" value="' . esc_attr($key) . '" ' . $checked . '> ' . esc_html($label) . '</label><br>';
                                    }
                                    ?>
                                </div>
                                
                                <p class="description">
                                    <label>
                                        <input type="checkbox" id="select_all_columns"> 
                                        <?php _e('Seleziona/Deseleziona tutte', 'marrison-exporter'); ?>
                                    </label>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label><?php _e('Dimensione Batch', 'marrison-exporter'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="batch_size" id="batch_size" value="50" min="10" max="200" step="10" class="small-text">
                                <p class="description">
                                    <?php _e('Numero di ordini processati per batch. Riduci se hai problemi di memoria.', 'marrison-exporter'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="export_orders" class="button button-primary" 
                               value="<?php _e('Esporta Ordini in CSV', 'marrison-exporter'); ?>">
                        <span class="description" style="margin-left: 10px;">
                            <?php _e('Per grandi quantità di dati, l\'esportazione potrebbe richiedere alcuni minuti.', 'marrison-exporter'); ?>
                        </span>
                    </p>
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
