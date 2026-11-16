<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;

class BackupDb extends BaseCommand
{
    protected $group = 'Tiket Layanan';
    protected $name = 'backup:db';
    protected $description = 'Membuat cadangan database memakai mysqldump.';

    public function run(array $params)
    {
        $db = config('Database')->default;
        $backupUser = (string) env('BACKUP_DB_USER', '');
        if ($backupUser === '') {
            throw new RuntimeException('BACKUP_DB_USER harus diatur untuk akun DBA/backup.');
        }
        $backupPassword = (string) env('BACKUP_DB_PASSWORD', '');
        $bin = (string) (env('MYSQLDUMP_BIN') ?: 'D:\\xampp\\mysql\\bin\\mysqldump.exe');
        if (! is_file($bin)) {
            throw new RuntimeException('mysqldump tidak ditemukan: ' . $bin);
        }
        $directory = WRITEPATH . 'backups';
        if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
            throw new RuntimeException('Folder backup gagal dibuat.');
        }
        $file = $directory . DIRECTORY_SEPARATOR . basename($db['database']) . '-' . gmdate('Ymd-His') . '.sql';
        $defaults = tempnam(WRITEPATH, 'mysql-options-');
        $options = "[client]\nuser=" . $backupUser . "\npassword=" . $backupPassword . "\nhost=" . $db['hostname'] . "\nport=" . $db['port'] . "\n";
        file_put_contents($defaults, $options);
        try {
            $command = escapeshellarg($bin) . ' --defaults-extra-file=' . escapeshellarg($defaults) . ' --routines --triggers --single-transaction ' . escapeshellarg($db['database']) . ' > ' . escapeshellarg($file);
            exec($command, $output, $code);
            if ($code !== 0 || ! is_file($file) || filesize($file) === 0) {
                @unlink($file);
                throw new RuntimeException('mysqldump gagal dengan kode ' . $code);
            }
            CLI::write('Backup: ' . $file);
        } finally {
            @unlink($defaults);
        }
    }
}
