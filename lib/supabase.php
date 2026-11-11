<?php
/**
 * lib/supabase.php
 * Pustaka koneksi HTTP REST & RPC ke database Supabase (PostgreSQL)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/SupabaseClient.php';

/**
 * Mengembalikan instance koneksi SupabaseClient
 */
function getSupabaseClient(): SupabaseClient
{
    static $instance = null;
    if ($instance === null) {
        $instance = new SupabaseClient();
    }
    return $instance;
}
