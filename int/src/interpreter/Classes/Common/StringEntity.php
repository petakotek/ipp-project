<?php

namespace IPP\Interpreter\Classes\Common;

use IPP\Interpreter\Classes\Common\NilEntity;
use IPP\Interpreter\Classes\ObjectEntity;

final class StringEntity extends ObjectEntity implements Instantiable
{
    public string $value = "";
    public function __construct(string $value = '')
    {
        parent::__construct();
        $this->value = $value;
    }
    // TBD
    /// nacteni retezce z jednoho radku vstupu a vytvoreni odpovidajici instance StringEntity
    public static function read(): StringEntity
    {
        return new StringEntity('');
    }
    /// vytiskne retezec na vystup, vraci self
    public function print(): StringEntity
    {
        echo $this->value;
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
    public static function new(): static
    {
        return new self('');
    }
    public static function from(mixed $parameter): static
    {
        return new self($parameter);
    }
}
