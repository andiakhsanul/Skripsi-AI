<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Mahasiswa = 'mahasiswa';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::cases()
        );
    }
}
