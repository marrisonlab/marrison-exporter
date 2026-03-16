# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2024-03-16

### Added
- Sistema di aggiornamenti automatici da GitHub releases
- Interfaccia grafica completamente ridisegnata nello stile Marrison Custom Updater
- Header con gradiente scuro e branding Marrisonlab
- Card bianche con design moderno e hover effects
- Dichiarazione di compatibilità con WooCommerce HPOS (High-Performance Order Storage)
- Compatibilità con WooCommerce 9.0
- Sistema batch processing per gestire grandi esportazioni senza esaurire memoria
- Selezione dimensione batch configurabile (10-200 ordini)
- Sistema di cache ottimizzato per performance migliori

### Improved
- Gestione memoria ottimizzata con batch processing
- Interfaccia admin completamente rinnovata
- Fix per select trasparenti nel date picker
- Correzione posizionamento footer WordPress
- Migliorata gestione caratteri speciali nei valori CSV
- Stili CSS separati in file dedicato
- Supporto UTF-8 BOM per compatibilità Excel

### Fixed
- Problema esaurimento memoria con grandi quantità di ordini
- Select del date picker con sfondo trasparente
- Footer della dashboard visualizzato male
- Compatibilità con nuove funzionalità WooCommerce
- Problemi di posizionamento date picker sotto footer

### Technical
- Aggiunti hook `before_woocommerce_init` per compatibilità HPOS
- Implementata funzione `declare_wc_compatibility()`
- Sistema di esportazione diviso in funzioni modulari
- Aggiunto sistema di garbage collection automatico

## [1.0.0] - 2024-03-16

### Added
- Release iniziale del plugin
- Esportazione ordini WooCommerce in formato CSV
- Selezione range di date per filtrare ordini
- Scelta delle colonne da esportare (20 colonne disponibili)
- Interfaccia admin integrata in WooCommerce
- Gestione ottimizzata della memoria per grandi esportazioni
- Supporto per caratteri speciali e virgole nei valori
- Sistema di aggiornamenti automatici da GitHub
