<?php

if (! function_exists('local_time')) {
    function local_time(?string $utc): string
    {
        if (! $utc) {
            return '—';
        }
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('Asia/Jakarta'))
            ->format('d M Y H:i') . ' WIB';
    }
}
