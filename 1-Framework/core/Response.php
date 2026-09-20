<?php
declare(strict_types=1);
namespace Jasr\Framework;

final class Response
{
    public static function json(int $status, string $message, mixed $data = null, array $errors = []): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $status < 400, 'message' => $message, 'data' => $data,
            'errors' => $errors, 'meta' => ['timestamp' => gmdate(DATE_ATOM), 'requestId' => bin2hex(random_bytes(12))]], JSON_THROW_ON_ERROR);
        exit;
    }
}
