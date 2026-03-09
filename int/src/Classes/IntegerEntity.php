<?php

namespace IPP\Classes;

use IPP\Classes\ObjectEntity;

class IntegerEntity extends ObjectEntity
{
    public int $value;

    public function equalTo($object) : bool {
        return $this->value === $object;
    }
    public function greaterThan($object) : bool {
        return $this->value > $object;
    }
    public function plus($object) : int {
        return $this->value + $object;
    }
    public function minus($object) : int {
        return $this->value - $object;
    }
    public function multiplyBy($object) : int {
        return $this->value * $object;
    }
    public function divBy($object) : int {
        return $this->value / $object;
    }

    public function asString($object) : string {
        return (string) $object;
    }
    public function asInteger($object) : int {
        return (int) $object;
    }
    // TBD
    public function timesRepeat() : int {

    }
}