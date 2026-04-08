<?php

namespace IPP\Interpreter\Classes;

use SplFileObject;

/**
 * Třídy Program je vytvářena pouze jednou při startu programu
 * Má v sobě strukturu $stack, která udržuje v sobě pole lokálních proměnných
 */
class ProgramInterface
{
    /**
     * @var array<mixed> $stack
     */
    public array $stack = [];
    public int $itemsCount = 0;

    public ?SplFileObject $file;
    public bool $fileEntered = false;
    public ClassEntity $actualClass;

    public ?ClassEntity $selfClass;

    public function __construct(ClassEntity $actualClass, ?SplFileObject $file)
    {
        $this->actualClass = $actualClass;
        if ($file != null) {
            $this->fileEntered = true;
        }
        $this->selfClass = null;
        $this->file = $file;
    }

    /**
     * Funkce vlozi pole promennych na zasobnik
     * @param array<mixed> $item
     * @return void
     */
    public function push(array $item): void
    {
        $this->itemsCount++;
        // pridani polozky na zacatek pole
        array_unshift($this->stack, $item);
    }

    public function pop(): mixed
    {
        if ($this->itemsCount === 0) {
            return null;
        }
        $this->itemsCount--;
        return array_shift($this->stack);
    }

    public function top(): mixed
    {
        if ($this->itemsCount != 0) {
            return current($this->stack);
        }
        return array();
    }

    public function isEmpty(): bool
    {
        return $this->itemsCount <= 0;
    }

    /**
     * Pokud promenna existuje, tak ji najde v nadblocich
     * @param string $variableName
     * @return mixed
     */
    public function searchForVariable($variableName): mixed
    {
        $index = 0;
        while ($index <= $this->itemsCount) {
            $localArray = $this->stack[$index];
            if (array_key_exists($variableName, $localArray)) {
                return $localArray[$variableName];
            }
            $index++;
        }
        return array();
    }

    /**
     * Aktualizuje vsechny promenne stejneho nazvu v nadblocich
     * @param $variableName string Nazev promenne, ktera bude vyhledana v bloku a nablocich
     * a bude aktualizovana
     * @param $value mixed prommene, ktera bude aktualizovana
     * @return void funkce nic nevraci, pouze dela svou praci
     */
    public function updateVariableContextInUpperBlocks(string $variableName, mixed $value): void
    {
        $index = 0;
        while ($index <= $this->itemsCount - 1) {
            $localArray = $this->stack[$index];
            if (array_key_exists($variableName, $localArray)) {
                $localArray[$variableName] = $value;
                $this->stack[$index] = $localArray;
            }
            $index++;
        }
    }
}
