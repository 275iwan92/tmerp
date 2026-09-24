<?php

namespace App\Support;

// SQL mentah lintas-database WAJIB lewat sini, jangan menulis `tm2mst.tabel` langsung: nama koneksi tetap tm2*,
// nama databasenya dari .env (tm2* di tmerp.test, ddt* dummy di mockup.test). Tanpa ini situs dummy menulis ke data real.
final class DbName
{
    // 'tm2mst.mat_raw' -> '<database koneksi tm2mst>.mat_raw'
    public static function table(string $qualified): string
    {
        [$connection, $table] = explode('.', $qualified, 2);

        return config("database.connections.$connection.database", $connection).'.'.$table;
    }
}
