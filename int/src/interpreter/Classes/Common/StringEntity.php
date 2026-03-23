<?php

namespace IPP\Interpreter\Classes\Common;

use IPP\Interpreter\Classes\Common\NilEntity;
use IPP\Interpreter\Classes\ObjectEntity;

class StringEntity extends ObjectEntity
{
    public string $value = '';
    public function __construct(string $value){
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
}