<?php
declare(strict_types=1);

namespace App\Validator;

use App\Entity\ProductAttributeAssignment;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ConsistentAttributeAssignmentValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ConsistentAttributeAssignment) {
            throw new UnexpectedTypeException($constraint, ConsistentAttributeAssignment::class);
        }
        if (!$value instanceof ProductAttributeAssignment) {
            return;
        }

        $map = [
            'string'  => 'stringValue',
            'text'    => 'textValue',
            'int'     => 'intValue',
            'decimal' => 'decimalValue',
            'bool'    => 'boolValue',
            'date'    => 'dateValue',
            'json'    => 'jsonValue',
        ];

        $type = $value->getDataType();
        if (!isset($map[$type])) {
            $this->context->buildViolation($constraint->messageType)->setParameter('%type%', (string)$type)->addViolation();
            return;
        }

        $filled = [];
        foreach ($map as $t => $prop) {
            $getter = 'get' . ucfirst($prop);
            $val = $value->$getter();
            if ($val !== null && $val !== [] && $val !== '') {
                $filled[] = [$t, $prop];
            }
        }

        $requiredProp = $map[$type];
        $getter = 'get' . ucfirst($requiredProp);
        $requiredFilled = $value->$getter() !== null && $value->$getter() !== '' && $value->$getter() !== [];

        if (!$requiredFilled || count($filled) !== 1) {
            $this->context->buildViolation($constraint->messageValue)
                ->setParameter('%type%', $type)
                ->setParameter('%field%', $requiredProp)
                ->addViolation();
        }
    }
}


