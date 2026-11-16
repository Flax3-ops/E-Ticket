<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * Entity tiket (Unit 6): kapsulasi data dengan getter/setter dan hak akses private/protected/public.
 *
 * @property string      $title
 * @property string      $status
 * @property string|null $sla_due_at
 */
class Ticket extends Entity
{
    /** Status yang masih dihitung terhadap SLA. */
    private const OPEN_STATUSES = ['Baru', 'Diproses'];

    protected $datamap = [];
    protected $casts = ['id' => 'integer', 'user_id' => 'integer', 'category_id' => 'integer', 'assigned_to' => '?integer'];

    /** Setter: judul dirapikan (spasi ganda dan tepi dibuang) sebelum disimpan. */
    protected function setTitle(string $title): static
    {
        $this->attributes['title'] = trim((string) preg_replace('/\s+/', ' ', $title));
        return $this;
    }

    /** Getter turunan: label status untuk tampilan. */
    public function getStatusLabel(): string
    {
        return $this->isOpen() ? 'Terbuka (' . $this->attributes['status'] . ')' : (string) $this->attributes['status'];
    }

    public function isOpen(): bool
    {
        return in_array($this->attributes['status'] ?? '', self::OPEN_STATUSES, true);
    }

    /** Menit tersisa menuju tenggat SLA (negatif bila terlambat); null bila tenggat tidak ada. */
    public function slaMinutesRemaining(?int $now = null): ?int
    {
        if (empty($this->attributes['sla_due_at'])) {
            return null;
        }
        return intdiv(strtotime($this->attributes['sla_due_at'] . ' UTC') - ($now ?? time()), 60);
    }

    /** Terlambat bila masih terbuka dan tenggat sudah lewat (aturan sama dengan view v_ticket_sla). */
    public function isOverdue(?int $now = null): bool
    {
        $remaining = $this->slaMinutesRemaining($now);
        return $this->isOpen() && $remaining !== null && $remaining < 0;
    }
}
