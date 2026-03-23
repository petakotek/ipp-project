<?php

namespace IPP\Interpreter\Classes\Common;

use IPP\Interpreter\Classes\Common\NilEntity;
use IPP\Interpreter\Classes\ObjectEntity;

final class StringEntity extends ObjectEntity implements Instantiable
{
    public string $value = '';
    public function __construct(string $value = ''){
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
    public function print() : StringEntity{
        echo $this->value;
        return $this;
    }
    public function equalTo(StringEntity $cilovyObjekt) : bool {
        return $this->value == $cilovyObjekt->value;
    }
    public function asString() : StringEntity{
        return $this;
    }
    /// vraci novou instanci tridy IntegerEntity pokud je retezec nejake rozumne cislo, jinak NilEntity
    public function asInteger() : object{
        if(is_numeric($this->value)){
            return new IntegerEntity((int)$this->value);
        }
        return new NilEntity();
    }
    /// vraci konkatenaci dvou retezcu, pokud argument neni instance StringEntity, vraci NilEntity
    public function concatenateWith(object $cilovyObjekt) : object{
        if ($cilovyObjekt instanceof StringEntity){
            return new StringEntity($this->value . $cilovyObjekt->value);
        }
        return new NilEntity();
    }

//    public function startsWith...

    public function length() : IntegerEntity{
        return new IntegerEntity(strlen($this->value));
    }

    public static function new(): static
    {
        return new self('');
    }
    public static function from(mixed $parameter) : static{
        return new self($parameter);
    }

}