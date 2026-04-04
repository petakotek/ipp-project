<?php

/**
 * This module contains the main logic of the interpreter.
 *
 * IPP: You must definitely modify this file. Bend it to your will.
 *
 * Author: Ondrej Ondryas <iondryas@fit.vut.cz>
 * Author: Petr Kotek <xkotekp00>
 *
 * AI usage notice: The template author used OpenAI Codex to create the implementation of this
 *                  module based on its Python counterpart.
 */

declare(strict_types=1);

namespace IPP\Interpreter;

use DOMDocument;
use DOMElement;
use IPP\Interpreter\Classes\BlockEntity;
use IPP\Interpreter\Classes\ClassEntity;
use IPP\Interpreter\Classes\ObjectEntity;
use IPP\Interpreter\Classes\Common\FalseEntity;
use IPP\Interpreter\Classes\Common\IntegerEntity;
use IPP\Interpreter\Classes\Common\NilEntity;
use IPP\Interpreter\Classes\Common\StringEntity;
use IPP\Interpreter\Classes\Common\TrueEntity;
use IPP\Interpreter\Classes\ProgramInterface;
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
use mysql_xdevapi\Expression;
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
                    'Error parsing input.txt XML'
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
     * Executes the currently loaded program, using the provided input.txt stream as standard input.txt.
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
                foreach ($this->currentProgram->classes as $cls){
                    if ($cls->name == $parentClass) {
                        $parentClassExists = true;
                        break;
                    }
                }
                if ($parentClass == "Object") {
                    $parentClassExists = true;
                }
                if (!$parentClassExists) {
                    throw new InterpreterError(ErrorCode::SEM_UNDEF);
                }
                if ($parentClass != $class->name) {
                    $isMainClass = true;
                    $main = $this->createNewClassEntity($class->name);
                    // vytvoreni instance programoveho rozhrani
                    $program = new ProgramInterface(actualClass: $main, file: $inputIo);
                    $this->parseRunMethod($class->methods, $program);
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
        // $ vendor/bin/phpstan analyse src/
    }

    /**
     * Funkce na zpracování metody Run, odtud startuje hlavní chování programu
     * @param array<Method> $methods
     */
    public function parseRunMethod(array $methods, ProgramInterface $interface): void
    {
        $isRunMethod = false;
        foreach ($methods as $method) {
            if ($method->selector == "run") {
                $isRunMethod = true;
                // zpracovani vsech prirazeni
                foreach ($method->block->assigns as $assign) {
                    if ($assign->target->name == "_") {
                        $this->parseExpression($assign->expr, $methods, $interface);
                    } else {
                        $target = $assign->target->name;
                        $topArray = $interface->top(); // ziskani aktualniho pole
                        $topArray[$target] = $this->parseExpression($assign->expr, $methods, $interface);
                        $objectToAdd = $topArray[$target]; // ulozeni promenne, ktere chci pridat/aktualizovat

                        $topArray = $interface->top(); // ziskani aktualizovaneho pole promennych
                        $topArray[$target] = $objectToAdd;

                        $interface->pop(); // vyhozeni aktualniho
                        // navraceni aktualizovaneho zpet do interface
                        $interface->push($topArray);
                    }
                }
            }
        }
        var_dump($interface->stack);

        if (!$isRunMethod) {
            // chybi metoda run
            throw new InterpreterError(ErrorCode::SEM_MAIN);
        }
    }
    // funkce zjisti jestli se jedna o literal, zaslani zpravy a podle toho pracuje

    /**
     * @param Expr $expr
     * @param array<Method> $methods
     */
    public function parseExpression(Expr $expr, array $methods, ProgramInterface &$interface): object
    {
        if ($expr->literal != null) {   // kdyz vyrazem je literal
            return $this->assignTo($expr->literal, $interface);
        } elseif ($expr->block != null) {   // kdyz vyrazem bude block
            return $this->assignTo($expr->block, $interface);
        } elseif ($expr->send != null) {
            $receiver = $expr->send->receiver; // receiver je nejaky vyraz
            $selector = $expr->send->selector;
            $arguments = $expr->send->args;
            $returnStuff = $this->sendMsg($receiver, $selector, $arguments, $methods, $interface);
            return $returnStuff;
        } elseif ($expr->variable != null) {
            // vraci Variable pokud je to self
            $returnObject = $this->assignTo($expr->variable, $interface);
            return $returnObject;
        }
        return new ObjectEntity();
    }
    // funkce vraci vytvoreny objekt


    /***
     * @param object $object
     * @param array<mixed> $lokalniPromenne
     */
    public function assignTo(object $object, ProgramInterface $interface): object
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
                    default:
                        // vytvoreni instance nove tridy s identifikatorem value
                        return $this->createNewClassEntity($object->value);
                }
            }
        }
        // pokud se jedna o prirazeni promenne, je nejprve tato promenna vyhledana, jestli vubec existuje
        if ($object instanceof Variable) {
            if ($object->name == "self" || $object->name == "super") {
                return $object;
            }
            $objectToSearch = $interface->searchForVariable($object->name);
            if ($objectToSearch != null) {
                return $objectToSearch;
            } else {
                // chyba, pouziti nedefinovane promenne
                throw new InterpreterError(ErrorCode::SEM_UNDEF);
            }
        }
        if ($object instanceof Block) {
            return new BlockEntity($object, $interface);
        }
        return new $object();
    }

    /**
     * @param string $className
     * @return ClassEntity
     */
    public function createNewClassEntity(string $className): ClassEntity
    {
        foreach ($this->currentProgram->classes as $class) {
            if ($class->name == $className) {
                $parent = $class->parent;
                if ($parent == "") {
                    throw new InterpreterError(ErrorCode::SEM_UNDEF);
                }
                $parentClass = null;
                switch ($parent) {
                    case "Integer":
                    case "String":
                    case "Nil":
                    case "Object":
                        break;
                    default:
                        $parentClass = $this->createNewClassEntity(className: $parent);
                }
                $methods = $class->methods;
                return new ClassEntity($class->name, $parent, $methods, $parentClass);
            }
        }
        // trida nebyla nalezena
        throw new InterpreterError(ErrorCode::SEM_UNDEF);
    }

    /**
     * @param array<Method> $methods
     * @param array<int, mixed> $arguments - volitelne mnozstvi argumentuu
     * */
    public function parseMethod(array $methods, string $selector, array $arguments, ProgramInterface $interface): ?object
    {
        $methodInterface = new ProgramInterface($interface->actualClass, $interface->file);
        if ($interface->selfClass != null) {
            $methodInterface->selfClass = $interface->selfClass;
        }
        $locals = []; // lokalni promenne metody
        $lastAssign = null;
        foreach ($methods as $method) {
            if ($method->selector == $selector) {
                $locals = $this->fillParameters($method->block->parameters, $arguments);
                // naplneni aktualniho bloku metody parametry, pokud byly zadany
                $methodInterface->push($locals);
                foreach ($method->block->assigns as $assign) {
                    $lastAssign = $assign->target->name;
                    $topArray = $methodInterface->top();
                    // aktualizace
                    $topArray[$lastAssign] = $this->parseExpression($assign->expr, $methods, $methodInterface);

                    $methodInterface->pop(); // vyhozeni aktualniho
                    // navraceni zpet do interface
                    $methodInterface->push($topArray);
                }
                if ($lastAssign != null) {
                    $topArray = $methodInterface->top();
                    return $topArray[$lastAssign];
                }
            }
        }

        return null;
    }
    /**
     * Funkce provede všechny příkazy v Bloku, pokud jsou parametry, tak si
     * parametry uloží do pole a  následně s nimi pokud jsou použity pracuje
     *
     * @param array<int, mixed> $arguments - volitelne mnozstvi argumentuu
     * @param array<Method> $methods
     * */
    public function parseBlock(BlockEntity $block, array $arguments, array $methods, bool $setParams, ProgramInterface &$interface): object
    {
        $locals = [];
        $lastAssign = null;
        if ($setParams) {
            $locals = $this->fillParameters($block->parameters, $arguments);

        }
        $interface->push($locals);
        foreach ($block->assigns as $assign) {
                $lastAssign = $assign->target->name;
                // pokud nebylo nastaveno pushne se prazdne pole
                $topArray = $interface->top();
                $topArray[$lastAssign] = $this->parseExpression($assign->expr, $methods, $interface);
                $interface->pop(); // vyhodim starou
                $interface->push($topArray); // nahradim novou
                /// pokud je tato promenna uz deklarovana v nadtride, tak dojde k jeji aktualizaci
                /// v nadbloku
                if ($lastAssign != "_") {
                    $interface->updateVariableContextInUpperBlocks($lastAssign, $topArray[$lastAssign]);
                }

        }
        $topArray = $interface->top();
        // vyhozeni aktualnich promennych bloku, vracim se totiz zpet o uroven vys
        $interface->pop();
        return $topArray[$lastAssign];
    }

    /**
     * Nastavi parametry promenne pro jejich vyuziti pri volani metody s parametry
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
    public function sendMsg(Expr $receiver, string $selector, array $args, array $methods, ProgramInterface &$interface): mixed
    {
        // parseAssign pro receiver
        $arguments = [];
        $argument = null;
        $object = $this->parseExpression($receiver, $methods, $interface);
        if ($args != null) {
            foreach ($args as $arg) {
                $arguments[$arg->order] = $this->parseExpression($arg->expr, $methods, $interface);
            }
            $argument = $arguments[1];
        }

        // Pokud se jedna o self metodu metodu
        if ($object instanceof Variable) {
            if ($object->name == "self") {

                // nenalezeno v sobe nebo nadtridach, takze metoda je nejspis v objektu, ktery je prijemce
                $tmpClass = $interface->actualClass;
                $interface->actualClass = $interface->selfClass;
                $ret = $this->parseMethod($interface->actualClass->methods, $selector, $arguments, $interface);
                // v pripade, ze je argument super, tak je zaroven i self (svuj vlastni Object, v tomto pripade trida)
                if ($argument instanceof Variable) {
                    if ($argument->name == "super" || $argument->name == "self")
                        $arguments[1] = $interface->actualClass;
                }
                return $this->methodRunForSelfSuper($ret, $interface, $selector, $arguments, $tmpClass);

//                if ($interface->actualClass->parentClassDefined != null) {
//                    if ($interface->actualClass->parentClassDefined->methods != null) {
//                        $parentMethods = $interface->actualClass->parentClassDefined->methods;
//                        // v pripade, ze je argument super, tak je zaroven i self (svuj vlastni Object, v tomto pripade trida)

//                        $interface->actualClass = $interface->actualClass->parentClassDefined;
//                        return $this->parseMethod($parentMethods, $selector, $arguments, $interface);
//                    }
//                }
            }
            if ($object->name == "super") {
                // v pripade, ze je argument super, tak je zaroven i self (svuj vlastni Object, v tomto pripade trida)
                if ($argument instanceof Variable){
                    if ($argument->name == "super" || $argument->name == "self")
                        $arguments[1] = $interface->actualClass;
                }
                // zacinam hledat v parent tride
                $tmpClass = $interface->actualClass;
                $interface->actualClass = $interface->selfClass->parentClassDefined;
                $ret = $this->parseMethod($tmpClass->parentClassDefined->methods, $selector, $arguments, $interface);

                return $this->methodRunForSelfSuper($ret, $interface, $selector, $arguments, $tmpClass);


//                if ($interface->actualClass->parentClassDefined != null) {
//                    if ($interface->actualClass->parentClassDefined->methods != null) {
//                        $parentMethods = $interface->actualClass->parentClassDefined->methods;
//                        $interface->actualClass = $interface->actualClass->parentClassDefined;
//                        return $this->parseMethod($parentMethods, $selector, $arguments, $interface);
//                    }
//                }
            }
            throw new InterpreterError(ErrorCode::SEM_UNDEF);
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
                if ($object instanceof ClassEntity) {
                    return $object;
                }
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
                case "read":
                    return $object::read($interface->file);
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
                    $returnObject = null;
                    if ($argument instanceof BlockEntity) {
                        for ($i = 1; $i <= $object->value; $i++) {
                            $arr[1] = $object->timesRepeat($i);
                            $returnObject = $this->parseBlock($argument, $arr, $methods, true, $interface);
                        }
                    }
                    return $returnObject;

            }
        }

        if ($object instanceof BlockEntity) {
            switch ($selector) {
                case "value":
                    $obj = $this->parseBlock($object, $arguments, $methods, false, $object->interface);
                    return $obj;

                case str_repeat("value:", count($arguments)):
                    return $this->parseBlock($object, $arguments, $methods, true, $object->interface);
            }
        }

        if ($object instanceof TrueEntity || $object instanceof FalseEntity) {
            switch ($selector) {
                case "not":
                    return $object->not();

                case "and:":
                    // pokud se jedna jeste o nejaky vyraz a nemame jeste Objekt
                    if ($argument instanceof Expr) {
                        $argument = $this->parseExpression($argument, $methods, $interface);
                    }
                    return $object->and($argument);

                    case "or:":
                    // pokud se jedna jeste o nejaky vyraz a nemame jeste Objekt
                    if ($argument instanceof Expr) {
                        $argument = $this->parseExpression($argument, $methods, $interface);
                    }
                    return $object->or($argument);

                case "ifTrue:ifFalse:":
                    if ($arguments != null) {
                        if ($object instanceof TrueEntity) {
                            return $this->parseBlock($arguments[1], $arguments, $methods, false, $interface);
                        } else {
                            return $this->parseBlock($arguments[2], $arguments, $methods, false, $interface);
                        }
                    }
            }
        }

        if ($object instanceof ClassEntity) {
            // pokus o volani nejake metody v tride bez metod
            if ($object->methods == null) {
                throw new InterpreterError(ErrorCode::INT_OTHER);
            }

            // reference objektu, ktery zpravu prijal
            $interface->selfClass = $object;
            // nalezeni tridy kde je ta zkurvena metoda
            $resultClass = null;
            while ($resultClass == null) {
                $classWhereMethod = $object;
                foreach ($classWhereMethod->methods as $method) {
                    if ($method->selector == $selector) {
                        $resultClass = $classWhereMethod;
                        break;
                    }
                }
                if ($resultClass != null) {
                    break;
                }
                $object = $object->parentClassDefined;
                if ($object == null) {
                    throw new InterpreterError(ErrorCode::SEM_UNDEF);
                }
            }
            $actualClassTmp = $interface->actualClass;
            $interface->actualClass = $object;
            $result = $this->parseMethod($resultClass->methods, $selector, $arguments, $interface);
            $interface->actualClass = $actualClassTmp;
            return $result;
        }

        // does not understand
        throw new InterpreterError(ErrorCode::INT_DNU);
    }

    /**
     * @param mixed $ret
     * @param ProgramInterface $interface
     * @param string $selector
     * @param array $arguments
     * @param ClassEntity $tmpClass
     * @return mixed|object
     */
    public function methodRunForSelfSuper(mixed $ret, ProgramInterface $interface, string $selector, array $arguments, ClassEntity $tmpClass): mixed
    {
        while ($ret == null) {
            // metoda neexistuje ani v nadtridach
            if ($interface->actualClass->parentClassDefined == null) {
                throw new InterpreterError(ErrorCode::SEM_UNDEF);
            }
            $interface->actualClass = $interface->actualClass->parentClassDefined;
            $ret = $this->parseMethod($interface->actualClass->methods, $selector, $arguments, $interface);
        }
        $interface->actualClass = $tmpClass;
        return $ret;
    }
}
