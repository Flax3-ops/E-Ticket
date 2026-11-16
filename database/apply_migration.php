<?php

declare(strict_types=1);

/**
 * Menjalankan up()/down() sebuah migrasi pada database tertentu TANPA mencatat ke tabel `migrations`.
 * Dipakai untuk database uji beban (tiket_loadtest) yang tidak dikelola `spark migrate`.
 *
 *   php database/apply_migration.php <NamaKelasMigrasi> <up|down> [database=tiket_loadtest]
 */

define('HOMEPATH', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
define('CONFIGPATH', HOMEPATH . 'app/Config/');
define('PUBLICPATH', HOMEPATH . 'public' . DIRECTORY_SEPARATOR);
chdir(HOMEPATH);
require HOMEPATH . 'vendor/codeigniter4/framework/system/Test/bootstrap.php';

$class = $argv[1] ?? '';
$direction = $argv[2] ?? '';
$database = $argv[3] ?? 'tiket_loadtest';
if (! in_array($direction, ['up', 'down'], true) || ! str_contains($database, 'test')) {
    fwrite(STDERR, "Pemakaian: php database/apply_migration.php <Kelas> <up|down> [database *test*]\n");
    exit(1);
}
foreach (glob(HOMEPATH . 'app/Database/Migrations/*.php') as $file) {
    if (str_ends_with($file, '_' . $class . '.php')) {
        require_once $file;
    }
}
$fqcn = 'App\\Database\\Migrations\\' . $class;
if (! class_exists($fqcn)) {
    fwrite(STDERR, "Kelas migrasi tidak ditemukan: $class\n");
    exit(1);
}
$config = ['DBDriver' => 'MySQLi', 'hostname' => '127.0.0.1', 'username' => 'root', 'password' => '', 'database' => $database, 'port' => 3306, 'charset' => 'utf8mb4', 'DBCollat' => 'utf8mb4_general_ci', 'DBDebug' => true];
$migration = new $fqcn(\Config\Database::forge($config));
$migration->{$direction}();
echo "$class::$direction() selesai pada $database\n";
