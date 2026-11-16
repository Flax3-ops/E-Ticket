<?php

namespace App\Libraries\Notifier;

interface NotifierInterface
{
    public function notify(int $userId, string $type, string $message, ?string $link = null): void;
}
