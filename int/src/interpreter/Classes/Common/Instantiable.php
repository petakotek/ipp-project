<?php

namespace IPP\Interpreter\Classes\Common;

interface Instantiable
{
    public static function new(): static;
    public static function from(mixed $parameter): static;
}
