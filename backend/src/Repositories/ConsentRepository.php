<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use Yinghua\Support\Secrets;

class ConsentRepository
{
    public function __construct(private readonly Database $database) {}

    public function save(string $memberId, string $version, string $ipHash): void
    {
        foreach (['terms', 'privacy'] as $document) {
            $this->database->execute(
                'INSERT INTO member_consents (id,member_id,document_key,document_version,source,ip_hash) VALUES (:id,:member,:document,:version,\'registration\',:ip)',
                [
                    'id' => Secrets::uuid(),
                    'member' => $memberId,
                    'document' => $document,
                    'version' => $version,
                    'ip' => $ipHash,
                ],
            );
        }
    }
}
