<?php

namespace App\Models;

use CodeIgniter\Database\BaseConnection;
use mysqli;
use RuntimeException;

/**
 * Akses data daftar/pencarian/ekspor tiket — VERSI RAW SQL `mysqli` (baseline v0.9, Unit 10).
 *
 * Seluruh query ditulis sebagai string SQL dengan prepared statement mysqli. Pengikatan tipe
 * (`bind_param`) dan perakitan klausa WHERE dilakukan manual di sini; versi v1.0 memigrasikannya
 * ke Query Builder CI4 dengan perilaku yang sama (lihat docs/A10-migrasi-teknologi.md).
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
        [$where, $types, $params] = $this->conditions($filters, $requesterId);
        $total = (int) $this->run('SELECT COUNT(*) AS n FROM tickets t WHERE ' . $where, $types, $params)[0]['n'];
        $sql = 'SELECT t.*, c.name AS category_name, u.name AS requester_name, p.name AS officer_name '
            . 'FROM tickets t JOIN categories c ON c.id = t.category_id JOIN users u ON u.id = t.user_id '
            . 'LEFT JOIN users p ON p.id = t.assigned_to WHERE ' . $where . ' ORDER BY t.id DESC LIMIT ?, ?';
        $rows = $this->run($sql, $types . 'ii', array_merge($params, [max(0, ($page - 1) * $perPage), $perPage]));
        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * @param array{status?: string, priority?: string, category_id?: string, q?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function export(array $filters): array
    {
        [$where, $types, $params] = $this->conditions($filters, null);
        $sql = 'SELECT t.ticket_no, t.title, t.priority, t.status, t.created_at, c.name AS category_name '
            . 'FROM tickets t JOIN categories c ON c.id = t.category_id WHERE ' . $where . ' ORDER BY t.id DESC';
        return $this->run($sql, $types, $params);
    }

    /** @return array{0: string, 1: string, 2: list<int|string>} klausa WHERE, tipe bind_param, nilai */
    private function conditions(array $filters, ?int $requesterId): array
    {
        $where = ['t.deleted_at IS NULL'];
        $types = '';
        $params = [];
        if ($requesterId !== null) {
            $where[] = 't.user_id = ?';
            $types .= 'i';
            $params[] = $requesterId;
        }
        foreach (['status', 'priority', 'category_id'] as $field) {
            $value = (string) ($filters[$field] ?? '');
            if ($value !== '') {
                $where[] = 't.' . $field . ' = ?';
                $types .= 's';
                $params[] = $value;
            }
        }
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            if (str_starts_with(strtoupper($search), 'TKT-')) {
                $where[] = 't.ticket_no LIKE ?';
                $params[] = $this->escapeLike($search) . '%';
            } elseif (mb_strlen($search) >= 3) {
                $where[] = 'MATCH(t.title) AGAINST (? IN NATURAL LANGUAGE MODE)';
                $params[] = $search;
            } else {
                $where[] = 't.title LIKE ?';
                $params[] = '%' . $this->escapeLike($search) . '%';
            }
            $types .= 's';
        }
        return [implode(' AND ', $where), $types, $params];
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    /**
     * @param list<int|string> $params
     * @return list<array<string, mixed>>
     */
    private function run(string $sql, string $types, array $params): array
    {
        // Koneksi CI4 bersifat lazy: connID bernilai false sebelum ada query pertama.
        $this->db->initialize();
        /** @var mysqli $mysqli */
        $mysqli = $this->db->connID;
        $stmt = $mysqli->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('E-DB-001 prepare gagal: ' . $mysqli->error);
        }
        if ($params !== []) {
            $stmt->bind_param($types, ...$params);
        }
        if (! $stmt->execute()) {
            throw new RuntimeException('E-DB-002 execute gagal: ' . $stmt->error);
        }
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }
}
