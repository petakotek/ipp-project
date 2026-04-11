<?php

namespace IPP\Interpreter\Classes;

class AttributeEntity
{
    public ClassEntity $attributeClass;
    public mixed $attributeObject;
    public string $attributeSelector;

    /**
     * @param string $attributeSelector
     * @param ClassEntity $attributeClass
     * @param mixed $attributeObject
     */
    public function __construct($attributeSelector, $attributeClass, $attributeObject)
    {
        $this->attributeSelector = $attributeSelector;
        $this->attributeClass = $attributeClass;
        $this->attributeObject = $attributeObject;
    }
}
