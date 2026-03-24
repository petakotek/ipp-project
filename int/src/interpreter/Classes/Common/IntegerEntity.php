<?php

namespace IPP\Interpreter\Classes\Common;

use IPP\Interpreter\Classes\BlockEntity;
use IPP\Interpreter\Classes\ObjectEntity;
use IPP\Interpreter\Exception\ErrorCode;
use IPP\Interpreter\Exception\InterpreterError;

final class IntegerEntity extends ObjectEntity implements Instantiable
{
    public int $value = 0;
    // konstruktor nastaveni hodnoty $value
    public function __construct(int $value = 0)
    {
        parent::__construct();
        $this->value = $value;
    }
    /// Funkce porovna, ze je ciselna hodnota prijemce a argumentu shodna
    public function equalTo(object $cilovyObjekt): TrueEntity | FalseEntity
    {
        if ($cilovyObjekt instanceof IntegerEntity) {
            if ($this->value == $cilovyObjekt->value) {
                return TrueEntity::getInstance();
            }
        }
        return FalseEntity::getInstance();
    }
    public function greaterThan(IntegerEntity $cilovyObjekt): TrueEntity | FalseEntity
    {
        if ($this->value > $cilovyObjekt->value) {
            return TrueEntity::getInstance();
        }
        return FalseEntity::getInstance();
    }
    public function plus(IntegerEntity $cilovyObjekt): IntegerEntity
    {
        return new IntegerEntity($this->value + $cilovyObjekt->value);
    }
    public function minus(IntegerEntity $cilovyObjekt): IntegerEntity
    {
        return new IntegerEntity($this->value - $cilovyObjekt->value);
    }
    public function multiplyBy(IntegerEntity $cilovyObjekt): IntegerEntity
    {
        return new IntegerEntity($this->value * $cilovyObjekt->value);
    }
    public function divBy(IntegerEntity $cilovyObjekt): IntegerEntity
    {
        if ($cilovyObjekt->value == 0) {
            // deleni nulou vede na chybu 53
            throw new InterpreterError(ErrorCode::INT_INVALID_ARG);
        }
        return new IntegerEntity(intdiv($this->value, $cilovyObjekt->value));
    }
    public function asString(): object
    {
        return new StringEntity((string)$this->value);
    }
    /// vraci sebe sama
    public function asInteger(): IntegerEntity
    {
        return $this;
    }

    public function isNumber(): TrueEntity
    {
        return TrueEntity::getInstance();
    }

    // TBD
    public function timesRepeat(int $index): IntegerEntity
    {
        return new IntegerEntity($index);
    }

    public static function new(): static
    {
        return new self(0);
    }
    public static function from(mixed $parameter): static
    {
        return new self($parameter);
    }
}
