<?php

namespace IPP\Interpreter\Classes\Common;

use IPP\Interpreter\Classes\ObjectEntity;

class IntegerEntity extends ObjectEntity
{
    public int $value = 0;
    // konstruktor nastaveni hodnoty $value
    public function __construct(int $value){
        $this->value = $value;
    }
    /// Funkce porovna, ze je ciselna hodnota prijemce a argumentu shodna
    public function equalTo(IntegerEntity $cilovyObjekt) : bool{
        return $this->value == $cilovyObjekt->value;
    }
    public function greaterThan(IntegerEntity $cilovyObjekt) : bool{
        return $this->value > $cilovyObjekt->value;
    }
    public function plus(IntegerEntity $cilovyObjekt) : IntegerEntity{
        return new IntegerEntity($this->value + $cilovyObjekt->value);
    }
    public function minus(IntegerEntity $cilovyObjekt) : IntegerEntity{
        return new IntegerEntity($this->value - $cilovyObjekt->value);
    }
    public function multiplyBy(IntegerEntity $cilovyObjekt) : IntegerEntity{
        return new IntegerEntity($this->value * $cilovyObjekt->value);
    }
    public function divBy(IntegerEntity $cilovyObjekt) : IntegerEntity{
        return new IntegerEntity($this->value / $cilovyObjekt->value);
    }
    ///
    public function asString() : object{
        return new StringEntity((string)$this->value);
    }
    /// vraci sebe sama
    public function asInteger() : IntegerEntity{
        return $this;
    }


    // TBD
//    public function timesRepeat(){
//
//    }


}