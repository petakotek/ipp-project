<?php

namespace IPP\Interpreter\Classes;

class AttributeEntity
{
    public ClassEntity $attributeClass;
    public mixed $attributeObject;
    public string $attributeSelector;

    public function __construct($attributeSelector, $attributeClass, $attributeObject)
    {
        $this->attributeSelector = $attributeSelector;
        $this->attributeClass = $attributeClass;
        $this->attributeObject = $attributeObject;
    }
}
