<?php

declare(strict_types=1);

namespace Yinghua\Contracts;

interface Mailer
{
    public function send(string $recipient, string $subject, string $body): void;
}
