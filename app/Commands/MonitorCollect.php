<?php

namespace App\Commands;

use App\Services\MonitorService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class MonitorCollect extends BaseCommand
{
    protected $group = 'Tiket Layanan';
    protected $name = 'monitor:collect';
    protected $description = 'Menyimpan sampel penggunaan resource.';

    public function run(array $params)
    {
        CLI::write(json_encode((new MonitorService(db_connect()))->collect(true), JSON_UNESCAPED_UNICODE));
    }
}
