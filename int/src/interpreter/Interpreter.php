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
use IPP\Interpreter\Classes\ClassEntity;
use IPP\Interpreter\Classes\Common\FalseEntity;
use IPP\Interpreter\Classes\Common\IntegerEntity;
use IPP\Interpreter\Classes\Common\NilEntity;
use IPP\Interpreter\Classes\Common\StringEntity;
use IPP\Interpreter\Classes\Common\TrueEntity;
use IPP\Interpreter\Classes\MethodEntity;
use IPP\Interpreter\Classes\ObjectEntity;
use IPP\Interpreter\Exception\ErrorCode;
use IPP\Interpreter\Exception\InterpreterError;
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
        if ($this->currentProgram === null)
        {
            throw new InterpreterError(ErrorCode::INT_OTHER, 'No program is loaded.');
        }

        $this->logger->info('Executing program');

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
            if ($class->name == "Main" && $class->parent != ""){
                $isMainClass = true;
                $this->parseRunMethod($class->methods);
            }
        }

        if (!$isMainClass){
            // chybi trida Main
            throw new InterpreterError(ErrorCode::SEM_MAIN);
        }
    }


    /** @var array<string, object> $promenne */
    public array $promenne = [];

    /**
     * Funkce na zpracování metody Run, odtud startuje hlavní chování programu
     * @param array<Method> $methods
     */
    public function parseRunMethod(array $methods): void
    {
        $isRunMethod = false;
        foreach ($methods as $method){
            if ($method->selector == "run"){
                $isRunMethod = true;
                // zpracovani vsech prirazeni
                foreach ($method->block->assigns as $assign){
                    if ($assign->target->name == "_"){
                        $this->parseExpression($assign->expr);
                    }else {
                        $this->promenne[$assign->target->name] = $this->parseExpression($assign->expr);
                    }
                }
            }
        }
        if (!$isRunMethod){
            // chybi metoda run
            throw new InterpreterError(ErrorCode::SEM_MAIN);
        }
    }
    // funkce zjisti jestli se jedna o literal, zaslani zpravy a podle toho pracuje
    public function parseExpression(Expr $expr) : object
    {
        if ($expr->literal != null)
        {   // kdyz vyrazem je literal
            return $this->assignTo($expr->literal);
        }else if ($expr->block != null)
        {   // kdyz vyrazem bude block
            return $this->assignTo($expr->block);
        }else if ($expr->send != null)
        {
            // receiver je nejaky vyraz
            return $this->sendMsg($expr->send->receiver, $expr->send->selector);
        }else if ($expr->variable != null)
        {
            return $this->assignTo($expr->variable);
        }
        return new ObjectEntity();
    }
    // funkce vraci vytvoreny objekt
    public function assignTo(object $object) : object
    {
        if ($object instanceof Literal)
        {
            if ($object->classId == "Integer")
            {
                return new IntegerEntity((int)$object->value);
            }
            if ($object->classId == "String")
            {
                return new StringEntity($object->value);
            }
            if ($object->classId == "True")
            {
                return new TrueEntity();
            }
            if ($object->classId == "False")
            {
                return new FalseEntity();
            }
            if ($object->classId == "Nil") {
                return new NilEntity();
            }

        }
        if ($object instanceof Variable)
        {
            if (array_key_exists($object->name, $this->promenne))
            {
                return $this->promenne[$object->name];
            }else
            {
                // chyba, pouziti nedefinovane promenne
                throw new InterpreterError(ErrorCode::SEM_UNDEF);
            }
        }
        return new ObjectEntity();
    }

    ///
    /// $receiver - vyraz, ktery se vyhodnoti na objekt, tento objekt je prijemcem zpravy
    /// $selector - metoda, co budu hledat
    /// ...$args - volitelne mnozstvi argumentuu
    public function sendMsg(Expr $receiver,string $selector, ...$args): object {
        // parseAssign pro receiver
        $object = $this->parseExpression($receiver);
        if ($object instanceof StringEntity){
            switch ($selector){
                case "asString":
                    return $object->asString();
                case "print":
                    return $object->print();
            }
        }
        if ($object instanceof IntegerEntity){
            switch ($selector) {
                case "asString":
                    return $object->asString();
                case "asInteger":
                    return $object->asInteger();
            }
        }
        if ($object instanceof NilEntity){
            if ($selector == "asString") {
                return $object->asString();
            }
        }
        return $object;
    }

}
