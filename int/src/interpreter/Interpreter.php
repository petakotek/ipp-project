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
use IPP\Interpreter\Classes\ObjectEntity;
use IPP\Interpreter\Classes\Common\FalseEntity;
use IPP\Interpreter\Classes\Common\IntegerEntity;
use IPP\Interpreter\Classes\Common\NilEntity;
use IPP\Interpreter\Classes\Common\StringEntity;
use IPP\Interpreter\Classes\Common\TrueEntity;
use IPP\Interpreter\Exception\ErrorCode;
use IPP\Interpreter\Exception\InterpreterError;
use IPP\Interpreter\InputModel\Arg;
use IPP\Interpreter\InputModel\ClassDef;
use IPP\Interpreter\InputModel\Expr;
use IPP\Interpreter\InputModel\Literal;
use IPP\Interpreter\InputModel\Method;
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
        $this->parseClasses($this->currentProgram->classes);
        // testy kvality :
        // xkotekp00@LAPTOP-T421UQ1R:~/rocnik2/ipp/project/int$ vendor/bin/phpstan analyse src/
    }

    /**
     * Zpracuje kazdou tridu, zatim pouze jen Main tridu
     * @param array<ClassDef> $classes
     */
    public function parseClasses(array $classes): void
    {
        $isMainClass = false;

        // procházení všech tříd a nalezení třídy Main pro start programu
        foreach ($classes as $class) {
            if ($class->name == "Main" && $class->parent != "") {
                $isMainClass = true;
                $this->parseRunMethod($class->methods);
            }
        }

        if (!$isMainClass) {
            // chybi trida Main
            throw new InterpreterError(ErrorCode::SEM_MAIN);
        }
    }




    /**
     * Funkce na zpracování metody Run, odtud startuje hlavní chování programu
     * @param array<Method> $methods
     */
    public function parseRunMethod(array $methods): void
    {
        /** @var array<string, object> $promenneMain */
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
                        $lokalniPromenne[$assign->target->name] = $this->parseExpression($assign->expr, $methods, $lokalniPromenne);
                    }
                }
            }
        }
        var_dump($lokalniPromenne);
        if (!$isRunMethod) {
            // chybi metoda run
            throw new InterpreterError(ErrorCode::SEM_MAIN);
        }
    }
    // funkce zjisti jestli se jedna o literal, zaslani zpravy a podle toho pracuje
    public function parseExpression(Expr $expr, array $methods, array $lokalniPromenne): object
    {
        if ($expr->literal != null) {   // kdyz vyrazem je literal
            return $this->assignTo($expr->literal, $lokalniPromenne);
        } elseif ($expr->block != null) {   // kdyz vyrazem bude block
            return $this->assignTo($expr->block, $lokalniPromenne);
        } elseif ($expr->send != null) {
            // receiver je nejaky vyraz
            return $this->sendMsg($expr->send->receiver, $expr->send->selector, $expr->send->args, $methods, $lokalniPromenne);
        } elseif ($expr->variable != null) {
            // vraci Variable pokud je to self
            $returnObject = $this->assignTo($expr->variable, $lokalniPromenne);
            return $returnObject;
        }
        return new ObjectEntity();
    }
    // funkce vraci vytvoreny objekt
    public function assignTo(object $object, array $lokalniPromenne): object
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
            if ($object->name == "self") {
                return $object;
            }
            if (array_key_exists($object->name, $lokalniPromenne)) {
                return $lokalniPromenne[$object->name];
            } else {
                // chyba, pouziti nedefinovane promenne
                throw new InterpreterError(ErrorCode::SEM_UNDEF);
            }
        }
        return new $object();
    }


    /**
     *
     * @param array<Arg> $args - volitelne mnozstvi argumentuu
     * */
//    public function parseMethod(array $methods, string $selector, array $args) : object {
//        $lokalniMetodaPromenne = [];
//        foreach ($args as $arg) {
//            $lokalniMetodaPromenne[$arg->]
//        }
//        foreach ($methods as $method) {
//            if ($method->selector == $selector) {
//                foreach ($method->block->assigns as $assign) {
//                    if ($assign->target->name == "_") {
//                        return $this->parseExpression($assign->expr, $methods, $lokalniMetodaPromenne);
//                    } else {
//                        $lokalniMetodaPromenne[$assign->target->name] = $this->parseExpression($assign->expr, $methods, $lokalniMetodaPromenne);
//                    }
//                }
//            }
//        }
//    }


    /**
     * @param Expr $receiver - vyraz, ktery se vyhodnoti na objekt, tento objekt je prijemcem zpravy
     * @param string $selector - metoda, co budu hledat
     * @param array<Arg> $args - volitelne mnozstvi argumentuu
     * @param array<Method> $methods
     */
    public function sendMsg(Expr $receiver, string $selector, array $args, array $methods, array $lokalniPromenne): object
    {
        // parseAssign pro receiver
        $argument = null;
        $object = $this->parseExpression($receiver, $methods, $lokalniPromenne);
        if ($args != null) {
            $argument = $this->parseExpression($args[0]->expr, $methods, $lokalniPromenne);
        }

        // TBD tomorrow
//        if ($object instanceof Variable) {
//            if ($object->name == "self") {
//                foreach ($methods as $method) {
//                    if ($method->selector == $selector) {
//                        return $this->parseMethod($methods, $selector, $args);
//                    }
//                }
//            }
//        }


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
                    return $object->concatenateWith($argument);
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

//                case "timesRepeat:":
            }
        }

        return $object;
    }
}
