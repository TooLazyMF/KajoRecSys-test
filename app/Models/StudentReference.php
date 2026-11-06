<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id_number', 'name', 'gender', 'year', 'course', 'source_data'])]
class StudentReference extends Model
{
    public static function normalizeIdNumber(?string $idNumber): string
    {
        $idNumber = trim((string) $idNumber);

        return preg_match('/^([0-9]+)\.0+$/D', $idNumber, $matches)
            ? $matches[1]
            : $idNumber;
    }

    /** @return list<string> */
    public static function matchingIdNumbers(?string $idNumber): array
    {
        $trimmed = trim((string) $idNumber);
        $normalized = self::normalizeIdNumber($trimmed);

        return array_values(array_unique(array_filter([
            (string) $idNumber,
            $trimmed,
            $normalized,
            ctype_digit($normalized) ? $normalized.'.0' : null,
        ], 'strlen')));
    }

    protected function casts(): array
    {
        return [
            'source_data' => AsArrayObject::class,
        ];
    }
}
