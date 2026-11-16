<?php

namespace App\Libraries\Resource;

interface ResourceReaderInterface
{
    /** @return array{cpu_pct:?float,ram_used_mb:?int,ram_total_mb:?int,disk_used_gb:?float,disk_total_gb:?float} */
    public function read(): array;
}
