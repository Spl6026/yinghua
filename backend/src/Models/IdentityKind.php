<?php

declare(strict_types=1);

namespace Yinghua\Models;

enum IdentityKind: string
{
    case Member = 'member';
    case Admin = 'admin';

    public function table(): string
    {
        return $this === self::Admin ? 'admin_users' : 'members';
    }

    public function foreignKey(): string
    {
        return $this === self::Admin ? 'admin_id' : 'member_id';
    }
}
