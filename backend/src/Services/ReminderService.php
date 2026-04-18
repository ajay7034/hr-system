<?php

namespace App\Services;

use PDO;

final class ReminderService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function generate(): array
    {
        $createdNotifications = 0;
        $queuedEmails = 0;

        $employeeDocuments = $this->pdo->query("
            SELECT ed.id, ed.expiry_date, ed.status, ed.alert_days, ed.mail_enabled, ed.notification_enabled, e.full_name, e.email, edm.name AS document_type
            FROM employee_documents ed
            INNER JOIN employees e ON e.id = ed.employee_id
            INNER JOIN employee_document_masters edm ON edm.id = ed.document_master_id
            WHERE ed.deleted_at IS NULL AND ed.expiry_date IS NOT NULL
              AND DATEDIFF(ed.expiry_date, CURDATE()) <= ed.alert_days
        ")->fetchAll();

        $companyDocuments = $this->pdo->query("
            SELECT cd.id, cd.expiry_date, cd.status, cd.alert_days, cd.mail_enabled, cd.notification_enabled, cd.document_name, cdm.name AS document_type
            FROM company_documents cd
            INNER JOIN company_document_masters cdm ON cdm.id = cd.document_master_id
            WHERE cd.deleted_at IS NULL AND cd.expiry_date IS NOT NULL
              AND DATEDIFF(cd.expiry_date, CURDATE()) <= cd.alert_days
        ")->fetchAll();

        foreach ($employeeDocuments as $document) {
            $status = strtotime($document['expiry_date']) < strtotime(date('Y-m-d')) ? 'expired' : 'expiring_soon';
            $title = sprintf('%s %s', $document['document_type'], $status === 'expired' ? 'expired' : 'expiring soon');
            $message = sprintf('%s document for %s is %s on %s.', $document['document_type'], $document['full_name'], $status === 'expired' ? 'expired' : 'due', $document['expiry_date']);

            if ((int) $document['notification_enabled'] === 1
                && $this->storeNotification('employee_documents', (int) $document['id'], $title, $message, $status === 'expired' ? 'critical' : 'warning')
            ) {
                $createdNotifications++;
            }

            if ((int) $document['mail_enabled'] === 1
                && !empty($document['email'])
                && $this->queueEmail('employee_documents', (int) $document['id'], $document['email'], $title, $message)
            ) {
                $queuedEmails++;
            }
        }

        foreach ($companyDocuments as $document) {
            $status = strtotime($document['expiry_date']) < strtotime(date('Y-m-d')) ? 'expired' : 'expiring_soon';
            $title = sprintf('%s %s', $document['document_type'], $status === 'expired' ? 'expired' : 'expiring soon');
            $message = sprintf('%s is %s on %s.', $document['document_name'], $status === 'expired' ? 'expired' : 'due', $document['expiry_date']);

            if ((int) $document['notification_enabled'] === 1
                && $this->storeNotification('company_documents', (int) $document['id'], $title, $message, $status === 'expired' ? 'critical' : 'warning')
            ) {
                $createdNotifications++;
            }
        }

        return [
            'notifications_created' => $createdNotifications,
            'emails_queued' => $queuedEmails,
        ];
    }

    private function storeNotification(string $table, int $relatedId, string $title, string $message, string $severity): bool
    {
        $check = $this->pdo->prepare("
            SELECT id
            FROM notifications
            WHERE related_table = :related_table
              AND related_id = :related_id
              AND is_read = 0
            LIMIT 1
        ");
        $check->execute([
            'related_table' => $table,
            'related_id' => $relatedId,
        ]);

        $existing = $check->fetch();

        if ($existing) {
            $update = $this->pdo->prepare("
                UPDATE notifications
                SET title = :title,
                    message = :message,
                    severity = :severity
                WHERE id = :id
            ");
            $update->execute([
                'title' => $title,
                'message' => $message,
                'severity' => $severity,
                'id' => $existing['id'],
            ]);

            return false;
        }

        $insert = $this->pdo->prepare("
            INSERT INTO notifications (notification_type, title, message, related_table, related_id, severity)
            VALUES (:notification_type, :title, :message, :related_table, :related_id, :severity)
        ");
        $insert->execute([
            'notification_type' => $table,
            'title' => $title,
            'message' => $message,
            'related_table' => $table,
            'related_id' => $relatedId,
            'severity' => $severity,
        ]);

        return true;
    }

    private function queueEmail(string $table, int $relatedId, string $recipient, string $subject, string $body): bool
    {
        $check = $this->pdo->prepare("
            SELECT id
            FROM email_logs
            WHERE related_table = :related_table
              AND related_id = :related_id
              AND recipient_email = :recipient_email
              AND DATE(created_at) = CURDATE()
            LIMIT 1
        ");
        $check->execute([
            'related_table' => $table,
            'related_id' => $relatedId,
            'recipient_email' => $recipient,
        ]);

        if ($check->fetch()) {
            return false;
        }

        $insert = $this->pdo->prepare("
            INSERT INTO email_logs (related_table, related_id, recipient_email, subject, body, status)
            VALUES (:related_table, :related_id, :recipient_email, :subject, :body, 'queued')
        ");
        $insert->execute([
            'related_table' => $table,
            'related_id' => $relatedId,
            'recipient_email' => $recipient,
            'subject' => $subject,
            'body' => $body,
        ]);

        return true;
    }
}
