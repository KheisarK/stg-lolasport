<?php

// Run before dependency installation, also when vendor/ does not exist yet.
$missing = array_values(array_filter(
    ['ctype', 'curl', 'dom', 'fileinfo', 'mbstring', 'openssl', 'pdo_sqlite', 'sqlite3', 'tokenizer', 'xml', 'xmlreader', 'xmlwriter', 'zip'],
    fn ($extension) => ! extension_loaded($extension),
));
if (PHP_VERSION_ID < 80400 || $missing || ! function_exists('proc_open')) {
    fwrite(STDERR, "Runtime belum sesuai: PHP 8.4+ dan ekstensi lockfile diperlukan.\n");
    if ($missing) {
        fwrite(STDERR, 'Aktifkan ekstensi: '.implode(', ', $missing)."\n");
    }
    if (! function_exists('proc_open')) {
        fwrite(STDERR, "Fungsi proc_open diperlukan Composer dan pengujian proses.\n");
    }
    fwrite(STDERR, 'php.ini aktif: '.(php_ini_loaded_file() ?: '(tidak ada)')."\n");
    exit(1);
}
echo 'Runtime sesuai: PHP '.PHP_VERSION." dan ekstensi tersedia.\n";
