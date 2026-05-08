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
        AccommodationSchemaService::ensureSchema($this->pdo);
        VehicleSchemaService::ensureSchema($this->pdo);

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

        $vehicleDocuments = $this->pdo->query("
            SELECT
                vd.id,
                vd.expiry_date,
                vd.status,
                vd.alert_days,
                vd.mail_enabled,
                vd.notification_enabled,
                vd.document_name,
                v.vehicle_name,
                v.vehicle_number,
                vdm.name AS document_type
            FROM vehicle_documents vd
            INNER JOIN vehicles v ON v.id = vd.vehicle_id AND v.deleted_at IS NULL
            INNER JOIN vehicle_document_masters vdm ON vdm.id = vd.document_master_id
            WHERE vd.deleted_at IS NULL AND vd.expiry_date IS NOT NULL
              AND DATEDIFF(vd.expiry_date, CURDATE()) <= vd.alert_days
        ")->fetchAll();

        $accommodationDocuments = $this->pdo->query("
            SELECT
                ad.id,
                ad.expiry_date,
                ad.status,
                ad.alert_days,
                ad.mail_enabled,
                ad.notification_enabled,
                ad.document_name,
                a.accommodation_name,
                a.room_number,
                adm.name AS document_type
            FROM accommodation_documents ad
            INNER JOIN accommodations a ON a.id = ad.accommodation_id AND a.deleted_at IS NULL
            INNER JOIN accommodation_document_masters adm ON adm.id = ad.document_master_id
            WHERE ad.deleted_at IS NULL AND ad.expiry_date IS NOT NULL
              AND DATEDIFF(ad.expiry_date, CURDATE()) <= ad.alert_days
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

        foreach ($vehicleDocuments as $document) {
            $status = strtotime($document['expiry_date']) < strtotime(date('Y-m-d')) ? 'expired' : 'expiring_soon';
            $title = sprintf('%s %s', $document['document_type'], $status === 'expired' ? 'expired' : 'expiring soon');
            $message = sprintf(
                '%s for %s (%s) is %s on %s.',
                $document['document_name'],
                $document['vehicle_name'],
                $document['vehicle_number'],
                $status === 'expired' ? 'expired' : 'due',
                $document['expiry_date']
            );

            if ((int) $document['notification_enabled'] === 1
                && $this->storeNotification('vehicle_documents', (int) $document['id'], $title, $message, $status === 'expired' ? 'critical' : 'warning')
            ) {
                $createdNotifications++;
            }
        }

        foreach ($accommodationDocuments as $document) {
            $status = strtotime($document['expiry_date']) < strtotime(date('Y-m-d')) ? 'expired' : 'expiring_soon';
            $title = sprintf('%s %s', $document['document_type'], $status === 'expired' ? 'expired' : 'expiring soon');
            $message = sprintf(
                '%s for %s (room %s) is %s on %s.',
                $document['document_name'],
                $document['accommodation_name'],
                $document['room_number'],
                $status === 'expired' ? 'expired' : 'due',
                $document['expiry_date']
            );

            if ((int) $document['notification_enabled'] === 1
                && $this->storeNotification('accommodation_documents', (int) $document['id'], $title, $message, $status === 'expired' ? 'critical' : 'warning')
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
              AND subject = :subject
            LIMIT 1
        ");
        $check->execute([
            'related_table' => $table,
            'related_id' => $relatedId,
            'recipient_email' => $recipient,
            'subject' => $subject,
        ]);

        if ($check->fetch()) {
            return false;
        }

        $insert = $this->pdo->prepare("
            INSERT IGNORE INTO email_logs (related_table, related_id, recipient_email, subject, body, status)
            VALUES (:related_table, :related_id, :recipient_email, :subject, :body, 'queued')
        ");
        $insert->execute([
            'related_table' => $table,
            'related_id' => $relatedId,
            'recipient_email' => $recipient,
            'subject' => $subject,
            'body' => $body,
        ]);

        return $insert->rowCount() > 0;
    }
}
