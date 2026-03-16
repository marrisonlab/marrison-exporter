=== Marrison Exporter ===
Contributors: marrison
Tags: woocommerce, export, orders, csv, batch, memory, hpos
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin per esportare gli ordini di WooCommerce in formato CSV con selezione di date e colonne. Ottimizzato per gestire grandi quantità di dati senza esaurire la memoria.

== Description ==

Marrison Exporter è un plugin WordPress professionale che permette di esportare facilmente gli ordini di WooCommerce in formato CSV con funzionalità avanzate e interfaccia moderna.

Caratteristiche principali:
* **Esportazione in CSV**: Export completo degli ordini WooCommerce in formato CSV compatibile Excel
* **Selezione range di date**: Filtra gli ordini per periodo di tempo specifico
* **Scelta colonne personalizzate**: Seleziona solo le colonne che ti servono
* **Gestione memoria ottimizzata**: Processa ordini in batch per evitare esaurimento memoria
* **Interfaccia moderna**: Design professionale nello stile Marrison Custom Updater
* **Aggiornamenti automatici**: Ricevi aggiornamenti direttamente da GitHub
* **Compatibilità HPOS**: Pienamente compatibile con WooCommerce High-Performance Order Storage

== Installation ==

1. Scarica il file del plugin
2. Carica la cartella `marrison-exporter` nella directory `/wp-content/plugins/` del tuo sito WordPress
3. Attiva il plugin dalla pagina "Plugin" in WordPress
4. Assicurati che WooCommerce sia installato e attivato

== Usage ==

1. Vai nel menu **WooCommerce** → **Marrison Exporter**
2. Seleziona un range di date (opzionale)
3. Scegli le colonne che vuoi esportare
4. Configura la dimensione del batch se necessario
5. Clicca su "Esporta Ordini in CSV"
6. Il file CSV verrà scaricato automaticamente

== Performance Features ==

* **Batch Processing**: Processa ordini in batch di 50 (configurabile 10-200)
* **Memory Management**: Gestione automatica della memoria con garbage collection
* **Large Datasets Support**: Supporta esportazioni di migliaia di ordini
* **Optimized Queries**: Query ottimizzate per performance superiori

== Columns Available ==

Il plugin permette di esportare le seguenti colonne:
* ID Ordine
* Numero Ordine
* Stato Ordine
* Data Ordine
* Nome Cliente
* Email Cliente
* Telefono Cliente
* Indirizzo Fatturazione
* Indirizzo Spedizione
* Metodo Pagamento
* Metodo Spedizione
* Totale Ordine
* Subtotale
* Tasse
* Spedizione
* Sconto
* Prodotti
* Quantità
* Prezzo Prodotto
* Note Ordine

== Changelog ==

= 1.1.0 =
* **NUOVA INTERFACCIA**: Design completamente rinnovato nello stile Marrison Custom Updater
* **HPOS COMPATIBILITY**: Piena compatibilità con WooCommerce 9.0 e HPOS
* **MEMORY OPTIMIZATION**: Sistema batch processing per gestire grandi esportazioni
* **AUTO UPDATES**: Sistema di aggiornamenti automatici da GitHub releases
* **UI IMPROVEMENTS**: Card moderne, header gradiente, e design professionale
* **BUG FIXES**: Risolti problemi con select trasparenti e footer dashboard
* **PERFORMANCE**: Migliorata gestione memoria e cache ottimizzata

= 1.0.0 =
* Release iniziale
* Esportazione ordini WooCommerce in CSV
* Selezione range di date
* Scelta colonne personalizzate
* Interfaccia admin integrata in WooCommerce

== Upgrade Notice ==

= 1.1.0 =
Aggiornamento importante con nuova interfaccia grafica, compatibilità HPOS e miglioramenti performance. Consigliato per tutti gli utenti.

== Screenshots ==

1. Dashboard principale con card moderne e design professionale
2. Selezione colonne in grid responsive
3. Configurazione avanzata con batch size
4. Esportazione in corso con indicatori di progresso

== Frequently Asked Questions ==

**Q: Il plugin funziona con WooCommerce HPOS?**
A: Sì, il plugin dichiara piena compatibilità con WooCommerce High-Performance Order Storage.

**Q: Posso esportare migliaia di ordini?**
A: Sì, il sistema batch processing gestisce esportazioni di qualsiasi dimensione senza esaurire la memoria.

**Q: Il plugin riceve aggiornamenti automatici?**
A: Sì, il plugin si aggiorna automaticamente quando vengono rilasciate nuove versioni su GitHub.

**Q: È compatibile con PHP 8.x?**
A: Sì, il plugin richiede PHP 7.4+ e funziona perfettamente con PHP 8.x.

== Support ==

Per supporto e assistenza:
* Documentazione completa nel repository GitHub
* Issues e feature request su GitHub
* Supporto via Marrisonlab

== License ==

Questo plugin è distribuito con licenza GPL v2 o successiva.
