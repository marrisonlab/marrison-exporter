<?php
/**
 * Debug script per verificare il plugin Marrison Exporter
 */

// Includi WordPress
$wp_load_path = dirname(__FILE__, 4) . '/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once $wp_load_path;
} else {
    die('WordPress non trovato. Questo script deve essere eseguito in un ambiente WordPress.');
}

echo "<h1>Debug Marrison Exporter</h1>";

// Verifica se il plugin è attivo
if (is_plugin_active('marrison-exporter/marrison-exporter.php')) {
    echo "<p style='color: green;'>✅ Plugin Marrison Exporter è attivo</p>";
} else {
    echo "<p style='color: red;'>❌ Plugin Marrison Exporter non è attivo</p>";
}

// Verifica WooCommerce
if (class_exists('WooCommerce')) {
    echo "<p style='color: green;'>✅ WooCommerce è attivo</p>";
} else {
    echo "<p style='color: orange;'>⚠️ WooCommerce non è attivo</p>";
}

// Verifica permessi utente corrente
if (current_user_can('manage_options')) {
    echo "<p style='color: green;'>✅ Utente corrente ha permessi manage_options</p>";
} else {
    echo "<p style='color: red;'>❌ Utente corrente NON ha permessi manage_options</p>";
}

if (current_user_can('manage_woocommerce')) {
    echo "<p style='color: green;'>✅ Utente corrente ha permessi manage_woocommerce</p>";
} else {
    echo "<p style='color: orange;'>⚠️ Utente corrente NON ha permessi manage_woocommerce</p>";
}

// Verifica menu
global $menu;
echo "<h2>Menu di amministrazione disponibili:</h2>";
echo "<ul>";
foreach ($menu as $menu_item) {
    if (isset($menu_item[0]) && !empty($menu_item[0])) {
        echo "<li>" . esc_html($menu_item[0]) . "</li>";
    }
}
echo "</ul>";

// Verifica submenu
global $submenu;
echo "<h2>Submenu WooCommerce:</h2>";
if (isset($submenu['woocommerce'])) {
    echo "<ul>";
    foreach ($submenu['woocommerce'] as $submenu_item) {
        echo "<li>" . esc_html($submenu_item[0]) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: orange;'>⚠️ Menu WooCommerce non trovato</p>";
}

// Test classe plugin
if (class_exists('Marrison_Exporter')) {
    echo "<p style='color: green;'>✅ Classe Marrison_Exporter è caricata</p>";
} else {
    echo "<p style='color: red;'>❌ Classe Marrison_Exporter non è caricata</p>";
}

// Mostra hook attivi
echo "<h2>Hook admin_menu registrati:</h2>";
global $wp_filter;
if (isset($wp_filter['admin_menu'])) {
    echo "<pre>";
    print_r($wp_filter['admin_menu']);
    echo "</pre>";
} else {
    echo "<p>Nessun hook admin_menu trovato</p>";
}
?>
