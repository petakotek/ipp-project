<?php

namespace IPP\Interpreter\Classes;

use IPP\Interpreter\InputModel\Method;

class ClassEntity
{
    public string $className;
    public string $parentClassName;
    /** @var array<Method> $methods*/
    public ?array $methods;
    public ?ClassEntity $parentClassDefined;

    /**
     * @param string $identif
     * @param string $parent
     * @param array<Method> $methods
     */
    public function __construct(string $identif, string $parent, array $methods, ?ClassEntity $parentClassDefined)
    {
        $this->className = $identif;
        $this->parentClassName = $parent;
        $this->methods = $methods;
        $this->parentClassDefined = $parentClassDefined;
    }
}
