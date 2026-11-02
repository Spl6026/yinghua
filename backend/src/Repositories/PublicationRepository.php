<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

class PublicationRepository
{
    public function __construct(private readonly Database $database) {}

    public function begin(string $generation): int
    {
        $this->database->execute(
            "INSERT INTO content_publications (generation,status) VALUES (:generation,'pending')",
            ['generation' => $generation],
        );

        return (int) $this->database->pdo->lastInsertId();
    }

    public function finish(int $id, bool $success): void
    {
        $this->database->execute(
            'UPDATE content_publications SET status=:status,error_code=:error,completed_at=UTC_TIMESTAMP() WHERE id=:id',
            ['id' => $id,'status' => $success ? 'succeeded' : 'failed','error' => $success ? null : 'RENDER_FAILED'],
        );
    }
}
