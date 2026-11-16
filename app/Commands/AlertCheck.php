<?php

namespace App\Commands;

use App\Services\AlertService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class AlertCheck extends BaseCommand
{
    protected $group = 'Tiket Layanan';
    protected $name = 'alert:check';
    protected $description = 'Memeriksa kondisi alert dan pemulihan.';

    public function run(array $params)
    {
        CLI::write(json_encode((new AlertService(db_connect()))->check(), JSON_UNESCAPED_UNICODE));
    }
}
