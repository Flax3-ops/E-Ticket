<?php

namespace App\Services;

use App\Libraries\Notifier\DatabaseNotifier;
use CodeIgniter\Database\BaseConnection;

class AlertService
{
    public function __construct(private readonly BaseConnection $db, private readonly ?MonitorService $monitor = null)
    {
    }

    public function check(): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $conditions = [];
        $critical = $this->db->table('tickets')->select('id, ticket_no, assigned_to')->where('priority', 'Kritis')->where('status', 'Baru')->where('assigned_to', null)->where('deleted_at', null)->where('created_at <', gmdate('Y-m-d H:i:s', time() - 1800))->get()->getResultArray();
        foreach ($critical as $ticket) {
            $conditions['critical_unassigned:ticket:' . $ticket['id']] = ['rule' => 'critical_unassigned', 'object' => 'ticket:' . $ticket['id'], 'message' => $ticket['ticket_no'] . ' belum diambil lebih dari 30 menit', 'recipients' => $this->staffIds()];
        }
        foreach ($this->db->table('v_ticket_sla')->where('is_overdue', 1)->get()->getResultArray() as $item) {
            $ticket = $this->db->table('tickets')->select('assigned_to')->where('id', $item['id'])->get()->getRowArray();
            $recipients = $this->adminIds();
            if ($ticket && $ticket['assigned_to']) {
                $recipients[] = (int) $ticket['assigned_to'];
            }
            $conditions['sla_overdue:ticket:' . $item['id']] = ['rule' => 'sla_overdue', 'object' => 'ticket:' . $item['id'], 'message' => $item['ticket_no'] . ' melewati SLA', 'recipients' => array_unique($recipients)];
        }
        $metrics = ($this->monitor ?? new MonitorService($this->db))->collect();
        $limits = $this->settings();
        $ratios = [
            'cpu' => $metrics['cpu_pct'],
            'ram' => $metrics['ram_total_mb'] ? $metrics['ram_used_mb'] / $metrics['ram_total_mb'] * 100 : null,
            'disk' => $metrics['disk_total_gb'] ? $metrics['disk_used_gb'] / $metrics['disk_total_gb'] * 100 : null,
            'db' => $metrics['db_max_connections'] ? $metrics['db_connections'] / $metrics['db_max_connections'] * 100 : null,
        ];
        foreach ($ratios as $name => $value) {
            if ($value !== null && $value > (float) ($limits[$name . '_limit'] ?? 100)) {
                $conditions['resource:' . $name] = ['rule' => 'resource', 'object' => $name, 'message' => strtoupper($name) . ' melewati batas atas (' . round($value, 1) . '%)', 'recipients' => $this->adminIds()];
            }
        }
        if ($metrics['status'] === 'unavailable') {
            $conditions['reader:os'] = ['rule' => 'reader', 'object' => 'os', 'message' => 'Pembaca metrik OS tidak tersedia', 'recipients' => $this->adminIds()];
        }
        foreach ($this->recentErrors() as $key => $message) {
            $conditions['app_error:' . $key] = ['rule' => 'app_error', 'object' => $key, 'message' => $message, 'recipients' => $this->adminIds()];
        }

        $notifier = new DatabaseNotifier($this->db);
        foreach ($conditions as $key => $condition) {
            $this->db->transBegin();
            try {
                $active = $this->db->table('alerts')->where('active_key', $key)->get()->getRowArray();
                if (! $active) {
                    $this->db->table('alerts')->insert(['rule_code' => $condition['rule'], 'object_key' => $condition['object'], 'active_key' => $key, 'level' => 'critical', 'message' => $condition['message'], 'occurrences' => 1, 'first_seen_at' => $now, 'last_seen_at' => $now, 'last_notified_at' => $now, 'normal_checks' => 0, 'created_at' => $now, 'updated_at' => $now]);
                    foreach ($condition['recipients'] as $userId) {
                        $notifier->notify((int) $userId, 'alert', $condition['message'], '/alerts');
                    }
                } else {
                    $repeat = strtotime($now) - strtotime($active['last_notified_at']) >= 900;
                    $this->db->table('alerts')->where('id', $active['id'])->update(['occurrences' => (int) $active['occurrences'] + 1, 'last_seen_at' => $now, 'last_notified_at' => $repeat ? $now : $active['last_notified_at'], 'normal_checks' => 0, 'updated_at' => $now]);
                    if ($repeat) {
                        foreach ($condition['recipients'] as $userId) {
                            $notifier->notify((int) $userId, 'alert_reminder', $condition['message'], '/alerts');
                        }
                    }
                }
                $this->db->transCommit();
            } catch (\Throwable $e) {
                $this->db->transRollback();
                log_message('error', 'E-ALT-001 {message}', ['message' => $e->getMessage()]);
            }
        }
        foreach ($this->db->table('alerts')->where('resolved_at', null)->get()->getResultArray() as $active) {
            if (isset($conditions[$active['active_key']])) {
                continue;
            }
            $checks = (int) $active['normal_checks'] + 1;
            if ($checks < 2) {
                $this->db->table('alerts')->where('id', $active['id'])->update(['normal_checks' => $checks, 'updated_at' => $now]);
                continue;
            }
            $this->db->table('alerts')->where('id', $active['id'])->update(['active_key' => null, 'normal_checks' => $checks, 'resolved_at' => $now, 'updated_at' => $now]);
            foreach ($this->recipientsFor($active) as $userId) {
                $notifier->notify($userId, 'alert_resolved', 'Pulih: ' . $active['message'], '/alerts');
            }
        }
        return ['active' => count($conditions), 'checked_at' => $now];
    }

    private function settings(): array
    {
        $result = [];
        foreach ($this->db->table('settings')->get()->getResultArray() as $row) {
            $result[$row['key']] = $row['value'];
        }
        return $result;
    }

    private function adminIds(): array
    {
        return array_map('intval', array_column($this->db->table('users')->select('id')->where('role', 'admin')->where('is_active', 1)->get()->getResultArray(), 'id'));
    }

    private function staffIds(): array
    {
        return array_map('intval', array_column($this->db->table('users')->select('id')->whereIn('role', ['petugas', 'admin'])->where('is_active', 1)->get()->getResultArray(), 'id'));
    }

    private function recipientsFor(array $alert): array
    {
        if ($alert['rule_code'] === 'critical_unassigned') {
            return $this->staffIds();
        }
        $ids = $this->adminIds();
        if ($alert['rule_code'] === 'sla_overdue') {
            $ticketId = (int) substr($alert['object_key'], 7);
            $ticket = $this->db->table('tickets')->select('assigned_to')->where('id', $ticketId)->get()->getRowArray();
            if ($ticket && $ticket['assigned_to']) {
                $ids[] = (int) $ticket['assigned_to'];
            }
        }
        return array_unique($ids);
    }

    private function recentErrors(): array
    {
        $file = WRITEPATH . 'logs/log-' . gmdate('Y-m-d') . '.log';
        if (! is_file($file)) {
            return [];
        }
        $lines = array_slice(file($file, FILE_IGNORE_NEW_LINES) ?: [], -200);
        $errors = [];
        foreach ($lines as $line) {
            if (preg_match('/^(ERROR|CRITICAL) - ([0-9-]{10} [0-9:]{8}) --> (.+)$/', $line, $m) && strtotime($m[2] . ' UTC') >= time() - 120) {
                $message = mb_substr($m[3], 0, 180);
                $errors[substr(hash('sha256', $message), 0, 24)] = 'Error aplikasi: ' . $message;
            }
        }
        return $errors;
    }
}
