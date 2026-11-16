<?php

namespace App\Models;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;

/**
 * Akses data daftar/pencarian/ekspor tiket — VERSI QUERY BUILDER CI4 (v1.0, hasil migrasi Unit 10).
 *
 * Menggantikan implementasi raw SQL `mysqli` v0.9: kontrak publik (paginate/export) dan perilaku
 * dipertahankan; yang berubah hanya cara akses data. Keunggulan yang dipakai: binding parameter
 * otomatis, builder kondisi yang dapat dikomposisi/di-clone, dan escape LIKE bawaan.
 * Implementasi lama disimpan sebagai pembanding uji di tests/_support/Legacy/.
 */
class TicketListQuery
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * @param array{status?: string, priority?: string, category_id?: string, q?: string} $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, ?int $requesterId, int $page, int $perPage = 20): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        // Deferred join (patch v1.1): subquery hanya memilih id halaman ini lewat indeks idx_active_list
        // (urut id menurun, berhenti setelah $perPage baris), baru kemudian JOIN ke tabel lain. Tanpa ini
        // optimizer MariaDB memulai dari `categories` (2 baris) lalu menyortir ratusan ribu baris
        // (benchmark A10: halaman pertama 2 detik pada 100.000 tiket).
        $ids = $this->db->table('tickets t')->select('t.id');
        $this->applyConditions($ids, $filters, $requesterId);
        $ids->orderBy('t.id', 'DESC')->limit($perPage, $offset);
        $rows = $this->db->newQuery()->fromSubquery($ids, 'page')
            ->select('t.*, c.name AS category_name, u.name AS requester_name, p.name AS officer_name')
            ->join('tickets t', 't.id = page.id')
            ->join('categories c', 'c.id = t.category_id')
            ->join('users u', 'u.id = t.user_id')
            ->join('users p', 'p.id = t.assigned_to', 'left')
            ->orderBy('t.id', 'DESC')
            ->get()->getResultArray();
        // Hitungan total tanpa JOIN: kondisi hanya memakai kolom tabel tickets (dilayani indeks covering).
        $counter = $this->db->table('tickets t');
        $this->applyConditions($counter, $filters, $requesterId);
        return ['rows' => $rows, 'total' => $counter->countAllResults()];
    }

    /**
     * @param array{status?: string, priority?: string, category_id?: string, q?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function export(array $filters): array
    {
        $builder = $this->db->table('tickets t')
            ->select('t.ticket_no, t.title, t.priority, t.status, t.created_at, c.name AS category_name')
            ->join('categories c', 'c.id = t.category_id');
        $this->applyConditions($builder, $filters, null);
        return $builder->orderBy('t.id', 'DESC')->get()->getResultArray();
    }

    private function applyConditions(BaseBuilder $builder, array $filters, ?int $requesterId): void
    {
        $builder->where('t.deleted_at', null);
        if ($requesterId !== null) {
            $builder->where('t.user_id', $requesterId);
        }
        foreach (['status', 'priority', 'category_id'] as $field) {
            $value = (string) ($filters[$field] ?? '');
            if ($value !== '') {
                $builder->where('t.' . $field, $value);
            }
        }
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search === '') {
            return;
        }
        // PENGECUALIAN GUIDELINE (Unit 13 elemen 3): best practice proyek adalah memakai metode Query Builder
        // (where/like) tanpa SQL mentah. Tiga kondisi di bawah memakai fragmen SQL mentah karena:
        //  1. like() Query Builder hanya meng-escape tanda kutip, BUKAN wildcard % dan _ (versi raw v0.9
        //     meng-escape keduanya; ditemukan oleh TicketListQueryEquivalenceTest). Fragmen
        //     `LIKE ... ESCAPE '!'` + escapeLikeString() menjaga perilaku yang sama.
        //  2. MATCH ... AGAINST (FULLTEXT) tidak punya API Query Builder.
        // Nilai pengguna selalu melewati escapeLikeString()/escape(), sehingga tetap aman dari injeksi.
        $like = $this->db->escapeLikeString($search);
        if (str_starts_with(strtoupper($search), 'TKT-')) {
            $builder->where("t.ticket_no LIKE '" . $like . "%' ESCAPE '!'", null, false);
        } elseif (mb_strlen($search) >= 3) {
            $builder->where('MATCH(t.title) AGAINST (' . $this->db->escape($search) . ' IN NATURAL LANGUAGE MODE)', null, false);
        } else {
            $builder->where("t.title LIKE '%" . $like . "%' ESCAPE '!'", null, false);
        }
    }
}
