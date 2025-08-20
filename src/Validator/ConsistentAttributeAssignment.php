<?php
declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class ConsistentAttributeAssignment extends Constraint
{
    public string $messageType = 'Некорректный dataType "%type%".';
    public string $messageValue = 'Для типа "%type%" должно быть заполнено поле "%field%", остальные — пусты.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}


