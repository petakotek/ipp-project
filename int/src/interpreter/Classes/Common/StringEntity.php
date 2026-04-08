<?php

namespace IPP\Interpreter\Classes\Common;

use IPP\Interpreter\Classes\ObjectEntity;
use SplFileObject;

final class StringEntity extends ObjectEntity implements Instantiable
{
    public string $value = "";
    public function __construct(string $value = '')
    {
        parent::__construct();
        $this->value = $value;
    }
    /// vytiskne retezec na vystup, vraci self
    public function print(): StringEntity
    {
        echo stripcslashes($this->value);
        return $this;
    }
    public function equalTo(object $target): TrueEntity | FalseEntity
    {
        if ($target instanceof StringEntity) {
            if ($this->value === $target->value) {
                return TrueEntity::getInstance();
            }
        }
        return FalseEntity::getInstance();
    }
    public function asString(): StringEntity
    {
        return $this;
    }
    /// vraci novou instanci tridy IntegerEntity pokud je retezec nejake rozumne cislo, jinak NilEntity
    public function asInteger(): object
    {
        if (is_numeric($this->value)) {
            return new IntegerEntity((int)$this->value);
        }
        return new NilEntity();
    }
    /// vraci konkatenaci dvou retezcu, pokud argument neni instance StringEntity, vraci NilEntity
    public function concatenateWith(object $cilovyObjekt): object
    {
        if ($cilovyObjekt instanceof StringEntity) {
            return new StringEntity($this->value . $cilovyObjekt->value);
        }
        return new StringEntity('nil');
    }

//    public function startsWith...

    public function length(): IntegerEntity
    {
        $tmp = preg_replace('/\\\\./', '_', $this->value);
        if ($tmp != null) {
            return new IntegerEntity(strlen($tmp));
        }
        return new IntegerEntity(0);
    }
    public function isString(): TrueEntity
    {
        return TrueEntity::getInstance();
    }

    public function startsWidthEndsBefore(int $start, int $end): StringEntity
    {
        $start -= 1;
        $end -= 1;
        $length = $end - $start;
        return new StringEntity(substr($this->value, $start, $length));
    }
    public static function new(): static
    {
        return new self('');
    }
    public static function from(mixed $parameter): static
    {
        return new self($parameter);
    }
    public static function read(SplFileObject $object): static
    {
        $object->setFlags(SplFileObject::READ_CSV |
            SplFileObject::SKIP_EMPTY |
            SplFileObject::READ_AHEAD |
            SplFileObject::DROP_NEW_LINE);
        return new self($object->fgets());
    }
}
