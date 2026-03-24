<?php

/**
 * This module contains the main logic of the interpreter.
 *
 * IPP: You must definitely modify this file. Bend it to your will.
 *
 * Author: Ondrej Ondryas <iondryas@fit.vut.cz>
 * Author:
 *
 * AI usage notice: The template author used OpenAI Codex to create the implementation of this
 *                  module based on its Python counterpart.
 */

declare(strict_types=1);

namespace IPP\Interpreter;

use DOMDocument;
use DOMElement;
use IPP\Interpreter\Classes\BlockEntity;
use IPP\Interpreter\Classes\ObjectEntity;
use IPP\Interpreter\Classes\Common\FalseEntity;
use IPP\Interpreter\Classes\Common\IntegerEntity;
use IPP\Interpreter\Classes\Common\NilEntity;
use IPP\Interpreter\Classes\Common\StringEntity;
use IPP\Interpreter\Classes\Common\TrueEntity;
use IPP\Interpreter\Exception\ErrorCode;
use IPP\Interpreter\Exception\InterpreterError;
use IPP\Interpreter\InputModel\Arg;
use IPP\Interpreter\InputModel\Block;
use IPP\Interpreter\InputModel\ClassDef;
use IPP\Interpreter\InputModel\Expr;
use IPP\Interpreter\InputModel\Literal;
use IPP\Interpreter\InputModel\Method;
use IPP\Interpreter\InputModel\Parameter;
use IPP\Interpreter\InputModel\Program;
use IPP\Interpreter\InputModel\Variable;
use IPP\Interpreter\InputModel\XmlValidationException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SplFileObject;

/**
 * The main interpreter class, responsible for loading the source file and executing the program.
 */
class Interpreter
{
    private LoggerInterface $logger;
    private ?Program $currentProgram = null;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Reads the source SOL-XML file and stores it as the target program for this interpreter.
     * If any program was previously loaded, it is replaced by the new one.
     *
     * IPP: If you wish to run static checks on the program before execution, this is a good
     *      place to call them from.
     */
    public function loadProgram(string $sourceFilePath): void
    {
        $this->logger->info('Opening source file: {source_file}', ['source_file' => $sourceFilePath]);

        $xmlDocument = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            if ($xmlDocument->load($sourceFilePath) !== true) {
                throw new InterpreterError(
                    ErrorCode::INT_XML,
                    'Error parsing input XML'
                );
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $rootElement = $xmlDocument->documentElement;
        if (!$rootElement instanceof DOMElement) {
            throw new InterpreterError(ErrorCode::INT_STRUCTURE, 'Invalid SOL-XML structure');
        }

        try {
            $this->currentProgram = Program::fromXml($rootElement);
        } catch (XmlValidationException $e) {
            throw new InterpreterError(ErrorCode::INT_STRUCTURE, 'Invalid SOL-XML structure', $e);
        }
    }

    /**
     * Executes the currently loaded program, using the provided input stream as standard input.
     */
    public function execute(?SplFileObject $inputIo): void
    {
        if ($this->currentProgram === null) {
            throw new InterpreterError(ErrorCode::INT_OTHER, 'No program is loaded.');
        }

        $isMainClass = false;
        $parentClassExists = false;
        // procházení všech tříd a nalezení třídy Main pro start programu
        foreach ($this->currentProgram->classes as $class) {
            if ($class->name == "Main" && $class->parent != "") {
                $parentClass = $class->parent;
                // pokud je to Object, tak urcite existuje
                if ($class->parent == "Object") {
                    $parentClassExists = true;
                // jinak vyhledam v poli trid
                } elseif (array_key_exists($parentClass, $this->currentProgram->classes)) {
                    $parentClassExists = true;
                }
                if (!$parentClassExists) {
                    throw new InterpreterError(ErrorCode::SEM_UNDEF);
                }
                if ($parentClass != $class->name) {
                    $isMainClass = true;
                    $this->parseRunMethod($class->methods);
                } else {
                    throw new InterpreterError(ErrorCode::SEM_ERROR);
                }
            }
        }

        if (!$isMainClass) {
            // chybi trida Main | chyba 31
            throw new InterpreterError(ErrorCode::SEM_MAIN);
        }

        // testy kvality :
        // xkotekp00@LAPTOP-T421UQ1R:~/rocnik2/ipp/project/int$ vendor/bin/phpstan analyse src/
    }

    /**
     * Funkce na zpracování metody Run, odtud startuje hlavní chování programu
     * @param array<Method> $methods
     */
    public function parseRunMethod(array $methods): void
    {
        $lokalniPromenne = [];

        $isRunMethod = false;
        foreach ($methods as $method) {
            if ($method->selector == "run") {
                $isRunMethod = true;
                // zpracovani vsech prirazeni
                foreach ($method->block->assigns as $assign) {
                    if ($assign->target->name == "_") {
                        $this->parseExpression($assign->expr, $methods, $lokalniPromenne);
                    } else {
                        $target = $assign->target->name;
                        $lokalniPromenne[$target] = $this->parseExpression($assign->expr, $methods, $lokalniPromenne);
                    }
                }
            }
        }
//        var_dump($lokalniPromenne);
        if (!$isRunMethod) {
            // chybi metoda run
            throw new InterpreterError(ErrorCode::SEM_MAIN);
        }
    }
    // funkce zjisti jestli se jedna o literal, zaslani zpravy a podle toho pracuje

    /**
     * @param Expr $expr
     * @param array<Method> $methods
     * @param array<mixed> $lokalniPromenne
     */
    public function parseExpression(Expr $expr, array $methods, array &$lokalniPromenne): object
    {
        if ($expr->literal != null) {   // kdyz vyrazem je literal
            return $this->assignTo($expr->literal, $lokalniPromenne);
        } elseif ($expr->block != null) {   // kdyz vyrazem bude block
            return $this->assignTo($expr->block, $lokalniPromenne);
        } elseif ($expr->send != null) {
            // receiver je nejaky vyraz
            $receiver = $expr->send->receiver;
            $selector = $expr->send->selector;
            $arguments = $expr->send->args;
            return $this->sendMsg($receiver, $selector, $arguments, $methods, $lokalniPromenne);
        } elseif ($expr->variable != null) {
            // vraci Variable pokud je to self
            $returnObject = $this->assignTo($expr->variable, $lokalniPromenne);
            return $returnObject;
        }
        return new ObjectEntity();
    }
    // funkce vraci vytvoreny objekt

    /***
     * @param object $object
     * @param array<string,mixed> $lokalniPromenne
     */
    public function assignTo(object $object, array &$lokalniPromenne): object
    {
        if ($object instanceof Literal) {
            if ($object->classId == "Integer") {
                return new IntegerEntity((int)$object->value);
            }
            if ($object->classId == "String") {
                return new StringEntity($object->value);
            }
            if ($object->classId == "True") {
                return TrueEntity::getInstance();
            }
            if ($object->classId == "False") {
                return FalseEntity::getInstance();
            }
            if ($object->classId == "Nil") {
                return new NilEntity();
            }
            if ($object->classId == "class") {
                switch ($object->value) {
                    case "Integer":
                        return new IntegerEntity();
                    case "String":
                        return new StringEntity();
                    case "Nil":
                        return new NilEntity();
                    case "True":
                        return TrueEntity::getInstance();
                    case "False":
                        return FalseEntity::getInstance();
                }
            }
        }
        // pokud se jedna o prirazeni promenne, je nejprve tato promenna vyhledana, jestli vubec existuje
        if ($object instanceof Variable) {
            if ($object->name == "self" || $object->name == "super") {
                return $object;
            }
            if (array_key_exists($object->name, $lokalniPromenne)) {
                return $lokalniPromenne[$object->name];
            } else {
                // chyba, pouziti nedefinovane promenne
                throw new InterpreterError(ErrorCode::SEM_UNDEF);
            }
        }
        if ($object instanceof Block) {
            return new BlockEntity($object, $lokalniPromenne);
        }
        return new $object();
    }


    /**
     * @param array<Method> $methods
     * @param array<int, mixed> $arguments - volitelne mnozstvi argumentuu
     * */
    public function parseMethod(array $methods, string $selector, array $arguments): object
    {
        $locals = [];
        $lastAssign = null;
        foreach ($methods as $method) {
            if ($method->selector == $selector) {
                $locals = $this->fillParameters($method->block->parameters, $arguments);
                foreach ($method->block->assigns as $assign) {
                    $locals[$assign->target->name] = $this->parseExpression($assign->expr, $methods, $locals);
                    $lastAssign = $assign->target->name;
                }
                return $locals[$lastAssign];
            }
        }

        return new ObjectEntity();
    }
    /**
     * Funkce provede všechny příkazy v Bloku, pokud jsou parametry, tak si
     * parametry uloží do pole a  následně s nimi pokud jsou použity pracuje
     *
     * @param array<int, mixed> $arguments - volitelne mnozstvi argumentuu
     * @param array<Method> $methods
     * */
    public function parseBlock(BlockEntity $block, array $arguments, array $methods, bool $setParams): object
    {
        $locals = [];
        $lastAssign = null;
        if ($setParams) {
            $locals = $this->fillParameters($block->locals, $arguments);
        }

        foreach ($block->assigns as $assign) {
                $locals[$assign->target->name] = $this->parseExpression($assign->expr, $methods, $locals);
                if (isset($block->upperLocals[$assign->target->name])) {
                    $block->upperLocals[$assign->target->name] = $locals[$assign->target->name];
                }
                $lastAssign = $assign->target->name;
        }
        return $locals[$lastAssign];
    }

    /**
     * @param array<Parameter> $parameters
     * @param array<int, mixed> $arguments
     * @return array<string, mixed>
     */
    public function fillParameters(array $parameters, array $arguments): array
    {
        $locals = [];
        if (count($parameters) != count($arguments)) {
            throw new InterpreterError(ErrorCode::INT_INST_ATTR);
        }
        foreach ($parameters as $parameter) {
            $locals[$parameter->name] = $arguments[$parameter->order];
        }
        return $locals;
    }
    /**
     * @param Expr $receiver - vyraz, ktery se vyhodnoti na objekt, tento objekt je prijemcem zpravy
     * @param string $selector - metoda, co budu hledat
     * @param array<Arg> $args - volitelne mnozstvi argumentuu
     * @param array<Method> $methods
     * @param array<mixed>$locals - lokalni promenne v danem bloku metody
     */
    public function sendMsg(Expr $receiver, string $selector, array $args, array $methods, array &$locals): object
    {
        // parseAssign pro receiver
        $arguments = [];
        $argument = null;
        $object = $this->parseExpression($receiver, $methods, $locals);
        if ($args != null) {
            foreach ($args as $arg) {
                $arguments[$arg->order] = $this->parseExpression($arg->expr, $methods, $locals);
            }
//            var_dump($arguments);
            $argument = $arguments[1];
        }

        // Pokud se jedna o self metodu metodu
        if ($object instanceof Variable) {
            if ($object->name == "self") {
                foreach ($methods as $method) {
                    if ($method->selector == $selector) {
                        // provede danou metodu celou
                        return $this->parseMethod($methods, $selector, $arguments);
                    }
                }
                throw new InterpreterError(ErrorCode::SEM_UNDEF);
            }
        }


        // metody, ktere jsou pro vsechny objekty spolecne
        switch ($selector) {
            case "identicalTo:":
                return $object->identicalTo($argument);
            case "equalTo:":
                return $object->equalTo($argument);
            case "asString":
                return $object->asString();
            case "isNumber":
                return $object->isNumber();
            case "isString":
                return $object->isString();
            case "isBlock":
                return $object->isBlock();
            case "isNil":
                return $object->isNil();
            case "isBoolean":
                return $object->isBoolean();
            case "new":
                return $object::new();
            case "from:":
                return $object::from($argument->value);
        }

        // Metody, ktere muze provadet String Entity
        if ($object instanceof StringEntity) {
            switch ($selector) {
                case "print":
                    return $object->print();
                case "asInteger":
                    return $object->asInteger();
                case "concatenateWith:":
                    if ($argument != null) {
                        return $object->concatenateWith($argument);
                    }
                    break;
                case "startsWith:endsBefore:":
                    if ($arguments[1] instanceof IntegerEntity && $arguments[2] instanceof IntegerEntity) {
                        return $object->startsWidthEndsBefore($arguments[1]->value, $arguments[2]->value);
                    }
                    throw new InterpreterError(ErrorCode::INT_INVALID_ARG);

                case "length":
                    return $object->length();
            }
        }
        // metody, ktere muze provadet IntegerEntity
        if ($object instanceof IntegerEntity) {
            switch ($selector) {
                case "asInteger":
                    return $object->asInteger();
                case "greaterThan:":
                    if ($argument instanceof IntegerEntity) {
                        return $object->greaterThan($argument);
                    }
                    throw new InterpreterError(ErrorCode::INT_OTHER);
                case "plus:":
                    if ($argument instanceof IntegerEntity) {
                        return $object->plus($argument);
                    }
                    throw new InterpreterError(ErrorCode::INT_OTHER);
                case "minus:":
                    if ($argument instanceof IntegerEntity) {
                        return $object->minus($argument);
                    }
                    throw new InterpreterError(ErrorCode::INT_OTHER);
                case "multiplyBy:":
                    if ($argument instanceof IntegerEntity) {
                        return $object->multiplyBy($argument);
                    }
                    throw new InterpreterError(ErrorCode::INT_OTHER);
                case "divBy:":
                    if ($argument instanceof IntegerEntity) {
                        return $object->divBy($argument);
                    }
                    throw new InterpreterError(ErrorCode::INT_OTHER);

                case "timesRepeat:":
                    if ($argument instanceof BlockEntity) {
                        for ($i = 1; $i <= $object->value; $i++) {
                            $arr[1] = $object->timesRepeat($i);
                            $returnObject = $this->parseBlock($argument, $arr, $methods, setParams: true);
                        }
                    }
                    return $returnObject;
            }
        }

        if ($object instanceof BlockEntity) {
            switch ($selector) {
                case "value":
                    return $this->parseBlock($object, $arguments, $methods, setParams: false);
                case str_repeat("value:", count($arguments)):
                    return $this->parseBlock($object, $arguments, $methods, setParams: true);
            }
        }

        if ($object instanceof TrueEntity || $object instanceof FalseEntity) {
            switch ($selector) {

                case "not":
                    return $object->not();

                case "and:":
                    if (!$argument instanceof TrueEntity && !$argument instanceof FalseEntity) {
                        $argument = $this->parseExpression($argument, $methods, $locals);
                    }
                    return $object->and($argument);

                case "or:":
                    if (!$argument instanceof TrueEntity && !$argument instanceof FalseEntity) {
                        $argument = $this->parseExpression($argument, $methods, $locals);
                    }
                    return $object->or($argument);

                case "ifTrue:ifFalse:":
                    if ($object instanceof TrueEntity) {
                        return $this->parseBlock($arguments[1], $arguments, $methods, setParams: false);
                    }else{
                        return $this->parseBlock($arguments[2], $arguments, $methods, setParams: false);
                    }
            }
        }
        // do not understand
        throw new InterpreterError(ErrorCode::INT_DNU);
    }
}
