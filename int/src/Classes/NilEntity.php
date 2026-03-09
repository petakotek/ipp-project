<?php

namespace IPP\Classes;

use IPP\Classes\ObjectEntity;

class NilEntity extends ObjectEntity
{
    public function asString($object): null
    {
        return null;
    }
}