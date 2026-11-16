<?php

namespace App\Services;

use App\Libraries\Notifier\DatabaseNotifier;
use CodeIgniter\Database\BaseConnection;
use DomainException;
use RuntimeException;

class TicketService
{
    /** Pemohon boleh membuka ulang tiket Selesai paling lama 7 x 24 jam setelah resolved_at (SRS 4.2.1). */
    public const REOPEN_WINDOW_SECONDS = 7 * 86400;

    private DatabaseNotifier $notifier;
    private ActivityLogger $activity;

    public function __construct(private readonly BaseConnection $db)
    {
        $this->notifier = new DatabaseNotifier($db);
        $this->activity = new ActivityLogger($db);
    }

    public static function canView(array $ticket, int $userId, string $role): bool
    {
        if ($ticket['deleted_at'] !== null) {
            return $role === 'admin';
        }
        return $role === 'admin' || $role === 'petugas' || (int) $ticket['user_id'] === $userId;
    }

    /** Aturan edit isi tiket (SRS 4.2.2): Admin semua tiket aktif; Pemohon hanya tiket sendiri berstatus Baru. */
    public static function canEdit(array $ticket, int $userId, string $role): bool
    {
        if ($ticket['deleted_at'] !== null) {
            return false;
        }
        return $role === 'admin' || ($role === 'pemohon' && (int) $ticket['user_id'] === $userId && $ticket['status'] === 'Baru');
    }

    public static function canParticipate(array $ticket, int $userId, string $role): bool
    {
        return $role === 'admin' || $ticket['deleted_at'] === null && ( ($role === 'pemohon' && (int) $ticket['user_id'] === $userId) || ($role === 'petugas' && (int) $ticket['assigned_to'] === $userId));
    }

    public function create(int $userId, array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $day = gmdate('Y-m-d');
        $this->db->transBegin();
        try {
            $this->db->query('INSERT INTO ticket_sequences (ticket_date, last_no) VALUES (?, 1) ON DUPLICATE KEY UPDATE last_no = last_no + 1', [$day]);
            $seq = (int) $this->db->query('SELECT last_no FROM ticket_sequences WHERE ticket_date = ? FOR UPDATE', [$day])->getRowArray()['last_no'];
            if ($seq > 9999) {
                throw new DomainException('Batas 9.999 tiket per hari tercapai.');
            }
            $ticketNo = 'TKT-' . gmdate('Ymd') . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $this->db->table('tickets')->insert([
                'ticket_no' => $ticketNo,
                'user_id' => $userId,
                'category_id' => $data['category_id'],
                'title' => $data['title'],
                'description' => $data['description'],
                'priority' => $data['priority'],
                'status' => 'Baru',
                'sla_due_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $id = (int) $this->db->insertID();
            foreach ($this->db->table('users')->select('id')->whereIn('role', ['petugas', 'admin'])->where('is_active', 1)->get()->getResultArray() as $recipient) {
                $this->notifier->notify((int) $recipient['id'], 'ticket_new', 'Tiket baru ' . $ticketNo, '/tickets/' . $id);
            }
            $this->activity->write($userId, 'ticket_created', 'tickets', $id);
            $this->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function take(int $ticketId, int $officerId): void
    {
        $this->db->transBegin();
        try {
            $this->db->table('tickets')->where('id', $ticketId)->where('status', 'Baru')->where('assigned_to', null)->where('deleted_at', null)->update([
                'assigned_to' => $officerId,
                'status' => 'Diproses',
                'status_changed_by' => $officerId,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
            if ($this->db->affectedRows() !== 1) {
                throw new DomainException('Tiket sudah diambil atau tidak tersedia.');
            }
            $ticket = $this->find($ticketId);
            $this->notifier->notify((int) $ticket['user_id'], 'ticket_assigned', 'Tiket ' . $ticket['ticket_no'] . ' sedang diproses.', '/tickets/' . $ticketId);
            $this->activity->write($officerId, 'ticket_taken', 'tickets', $ticketId);
            $this->commit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function changeStatus(int $ticketId, int $actorId, string $role, string $to, string $note): void
    {
        $ticket = $this->find($ticketId);
        if (! $ticket || $ticket['deleted_at']) {
            throw new DomainException('Tiket tidak ditemukan.');
        }
        $from = $ticket['status'];
        $allowed = ($from === 'Diproses' && $to === 'Selesai' && $role === 'petugas' && (int) $ticket['assigned_to'] === $actorId)
            || ($from === 'Selesai' && $to === 'Diproses' && $role === 'pemohon' && (int) $ticket['user_id'] === $actorId && strtotime((string) $ticket['resolved_at']) >= time() - self::REOPEN_WINDOW_SECONDS)
            || ($from === 'Selesai' && $to === 'Ditutup' && ($role === 'admin' || ($role === 'pemohon' && (int) $ticket['user_id'] === $actorId)))
            || ($role === 'admin' && $from !== 'Ditutup' && in_array($to, ['Baru', 'Diproses', 'Selesai'], true) && $from !== $to);
        if (! $allowed || trim($note) === '') {
            throw new DomainException('Transisi tidak diizinkan atau catatan belum diisi.');
        }
        $now = gmdate('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            $this->db->table('tickets')->where('id', $ticketId)->where('status', $from)->update([
                'status' => $to,
                'status_changed_by' => $actorId,
                'resolved_at' => $to === 'Selesai' ? $now : ($to === 'Diproses' ? null : $ticket['resolved_at']),
                'updated_at' => $now,
            ]);
            if ($this->db->affectedRows() !== 1) {
                throw new DomainException('Status tiket berubah. Muat ulang halaman.');
            }
            $this->db->table('comments')->insert(['ticket_id' => $ticketId, 'user_id' => $actorId, 'body' => $note, 'created_at' => $now, 'updated_at' => $now]);
            $recipient = $actorId === (int) $ticket['user_id'] ? (int) ($ticket['assigned_to'] ?: 0) : (int) $ticket['user_id'];
            if ($recipient > 0) {
                $this->notifier->notify($recipient, 'ticket_status', 'Status ' . $ticket['ticket_no'] . ': ' . $to, '/tickets/' . $ticketId);
            }
            $this->activity->write($actorId, 'ticket_status_' . strtolower($to), 'tickets', $ticketId);
            $this->commit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function changePriority(int $ticketId, int $actorId, string $priority, string $reason): void
    {
        if (! in_array($priority, ['Rendah', 'Sedang', 'Tinggi', 'Kritis'], true) || trim($reason) === '') {
            throw new DomainException('Prioritas atau alasan tidak valid.');
        }
        $ticket = $this->find($ticketId);
        if (! $ticket || $ticket['deleted_at']) {
            throw new DomainException('Tiket tidak ditemukan.');
        }
        $hours = (int) $this->db->query('SELECT fn_sla_hours(?) AS hours_value', [$priority])->getRowArray()['hours_value'];
        $due = gmdate('Y-m-d H:i:s', strtotime($ticket['created_at'] . ' UTC') + $hours * 3600);
        $now = gmdate('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            $this->db->table('tickets')->where('id', $ticketId)->update(['priority' => $priority, 'sla_due_at' => $due, 'updated_at' => $now]);
            $this->db->table('comments')->insert(['ticket_id' => $ticketId, 'user_id' => $actorId, 'body' => 'Prioritas diubah dari ' . $ticket['priority'] . ' ke ' . $priority . '. Alasan: ' . $reason, 'created_at' => $now, 'updated_at' => $now]);
            $this->activity->write($actorId, 'ticket_priority_changed', 'tickets', $ticketId);
            $this->commit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** F-TKT-12: Admin menetapkan atau memindahkan penugasan dengan alasan tercatat. */
    public function assign(int $ticketId, int $adminId, int $officerId, string $reason): void
    {
        if (trim($reason) === '') {
            throw new DomainException('Alasan penugasan wajib diisi.');
        }
        $officer = $this->db->table('users')->where('id', $officerId)->where('role', 'petugas')->where('is_active', 1)->get()->getRowArray();
        $ticket = $this->find($ticketId);
        if (! $officer || ! $ticket || $ticket['deleted_at'] || $ticket['status'] === 'Ditutup') {
            throw new DomainException('Tiket atau petugas tidak valid.');
        }
        $now = gmdate('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            $update = ['assigned_to' => $officerId, 'status_changed_by' => $adminId, 'updated_at' => $now];
            if ($ticket['status'] === 'Baru') {
                $update['status'] = 'Diproses';
            }
            $this->db->table('tickets')->where('id', $ticketId)->update($update);
            $this->db->table('comments')->insert(['ticket_id' => $ticketId, 'user_id' => $adminId, 'body' => 'Penugasan dipindahkan ke ' . $officer['name'] . '. Alasan: ' . $reason, 'created_at' => $now, 'updated_at' => $now]);
            $this->notifier->notify($officerId, 'ticket_assigned', 'Anda ditugaskan pada ' . $ticket['ticket_no'], '/tickets/' . $ticketId);
            $this->activity->write($adminId, 'ticket_reassigned', 'tickets', $ticketId);
            $this->commit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function find(int $id): ?array
    {
        return $this->db->table('tickets')->where('id', $id)->get()->getRowArray();
    }

    private function commit(): void
    {
        if (! $this->db->transStatus()) {
            throw new RuntimeException('Transaksi database gagal.');
        }
        $this->db->transCommit();
    }
}
