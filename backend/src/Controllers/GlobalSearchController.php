<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use PDO;

final class GlobalSearchController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function index(Request $request): void
    {
        $query = trim((string) $request->query('q', ''));

        if ($query === '' || strlen($query) < 2) {
            Response::json(['success' => true, 'data' => []]);
            return;
        }

        $like = '%' . $query . '%';
        $results = array_merge(
            $this->searchEmployees($query, $like),
            $this->searchEmployeeDocuments($query, $like),
            $this->searchCompanyDocuments($query, $like),
            $this->searchPassports($query, $like)
        );

        Response::json(['success' => true, 'data' => array_slice($results, 0, 12)]);
    }

    private function searchEmployees(string $query, string $like): array
    {
        $statement = $this->pdo->prepare("
            SELECT e.id, e.full_name, e.employee_code, e.employee_id, e.email, c.name AS company
            FROM employees e
            LEFT JOIN companies c ON c.id = e.company_id
            WHERE e.deleted_at IS NULL
              AND (
                e.full_name LIKE :like
                OR e.employee_code LIKE :like
                OR e.employee_id LIKE :like
                OR e.email LIKE :like
                OR e.passport_number LIKE :like
              )
            ORDER BY
              CASE
                WHEN e.employee_code = :exact THEN 0
                WHEN e.employee_id = :exact THEN 1
                WHEN e.full_name = :exact THEN 2
                ELSE 3
              END,
              e.full_name ASC
            LIMIT 4
        ");
        $statement->execute([
            'like' => $like,
            'exact' => $query,
        ]);

        return array_map(static fn (array $row) => [
            'type' => 'employee',
            'type_label' => 'Employee',
            'title' => $row['full_name'],
            'subtitle' => trim(implode(' • ', array_filter([
                $row['employee_code'],
                $row['employee_id'],
                $row['company'],
            ]))),
            'route' => '/employees/' . (int) $row['id'],
        ], $statement->fetchAll());
    }

    private function searchEmployeeDocuments(string $query, string $like): array
    {
        $statement = $this->pdo->prepare("
            SELECT
                ed.document_number,
                edm.name AS document_type,
                e.full_name,
                e.employee_code
            FROM employee_documents ed
            INNER JOIN employees e ON e.id = ed.employee_id
            INNER JOIN employee_document_masters edm ON edm.id = ed.document_master_id
            WHERE ed.deleted_at IS NULL
              AND e.deleted_at IS NULL
              AND (
                ed.document_number LIKE :like
                OR edm.name LIKE :like
                OR e.full_name LIKE :like
                OR e.employee_code LIKE :like
              )
            ORDER BY
              CASE
                WHEN ed.document_number = :exact THEN 0
                WHEN edm.name = :exact THEN 1
                ELSE 2
              END,
              ed.expiry_date ASC,
              ed.id DESC
            LIMIT 4
        ");
        $statement->execute([
            'like' => $like,
            'exact' => $query,
        ]);

        return array_map(static fn (array $row) => [
            'type' => 'employee_document',
            'type_label' => 'Employee Document',
            'title' => $row['document_number'] ?: $row['document_type'],
            'subtitle' => trim(implode(' • ', array_filter([
                $row['document_type'],
                $row['full_name'],
                $row['employee_code'],
            ]))),
            'route' => '/employee-documents?search=' . rawurlencode((string) ($row['document_number'] ?: $row['full_name'])),
        ], $statement->fetchAll());
    }

    private function searchCompanyDocuments(string $query, string $like): array
    {
        $statement = $this->pdo->prepare("
            SELECT
                cd.document_name,
                cd.document_number,
                cdm.name AS document_type,
                c.name AS company
            FROM company_documents cd
            INNER JOIN company_document_masters cdm ON cdm.id = cd.document_master_id
            LEFT JOIN companies c ON c.id = cd.company_id
            WHERE cd.deleted_at IS NULL
              AND (
                cd.document_name LIKE :like
                OR cd.document_number LIKE :like
                OR cdm.name LIKE :like
                OR c.name LIKE :like
              )
            ORDER BY
              CASE
                WHEN cd.document_number = :exact THEN 0
                WHEN cd.document_name = :exact THEN 1
                ELSE 2
              END,
              cd.expiry_date ASC,
              cd.id DESC
            LIMIT 4
        ");
        $statement->execute([
            'like' => $like,
            'exact' => $query,
        ]);

        return array_map(static fn (array $row) => [
            'type' => 'company_document',
            'type_label' => 'Company Document',
            'title' => $row['document_number'] ?: $row['document_name'],
            'subtitle' => trim(implode(' • ', array_filter([
                $row['document_name'],
                $row['document_type'],
                $row['company'],
            ]))),
            'route' => '/company-documents?search=' . rawurlencode((string) ($row['document_number'] ?: $row['document_name'])),
        ], $statement->fetchAll());
    }

    private function searchPassports(string $query, string $like): array
    {
        $statement = $this->pdo->prepare("
            SELECT
                pr.passport_number,
                pr.current_status,
                e.full_name,
                e.employee_code,
                c.name AS company
            FROM passport_records pr
            INNER JOIN employees e ON e.id = pr.employee_id
            LEFT JOIN companies c ON c.id = e.company_id
            WHERE e.deleted_at IS NULL
              AND (
                pr.passport_number LIKE :like
                OR e.full_name LIKE :like
                OR e.employee_code LIKE :like
              )
            ORDER BY
              CASE
                WHEN pr.passport_number = :exact THEN 0
                WHEN e.employee_code = :exact THEN 1
                ELSE 2
              END,
              pr.updated_at DESC
            LIMIT 4
        ");
        $statement->execute([
            'like' => $like,
            'exact' => $query,
        ]);

        return array_map(static fn (array $row) => [
            'type' => 'passport',
            'type_label' => 'Passport',
            'title' => $row['passport_number'],
            'subtitle' => trim(implode(' • ', array_filter([
                $row['full_name'],
                $row['employee_code'],
                $row['company'],
                $row['current_status'],
            ]))),
            'route' => '/passports?search=' . rawurlencode((string) $row['passport_number']),
        ], $statement->fetchAll());
    }
}
