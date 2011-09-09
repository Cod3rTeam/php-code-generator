<?php

namespace CG\Proxy;

use CG\Generator\Writer;
use CG\Generator\PhpMethod;
use CG\Generator\PhpDocblock;
use CG\Generator\PhpClass;
use CG\Core\AbstractClassGenerator;

class Enhancer extends AbstractClassGenerator
{
    private $generatedClass;
    private $class;
    private $interfaces;
    private $generators;

    public function __construct(\ReflectionClass $class, array $interfaces = array(), array $generators = array())
    {
        if (empty($generators) && empty($interfaces)) {
            throw new \RuntimeException('Either generators, or interfaces must be given.');
        }

        $this->class = $class;
        $this->interfaces = $interfaces;
        $this->generators = $generators;
    }

    public function createInstance(array $args = array())
    {
        $generatedClass = $this->getClassName($this->class);

        if (!class_exists($generatedClass, false)) {
            eval($this->generateClass());
        }

        $ref = new \ReflectionClass($generatedClass);

        return $ref->newInstanceArgs($args);
    }

    public final function generateClass()
    {
        static $docBlock;
        if (empty($docBlock)) {
            $writer = new Writer();
            $writer
                ->writeln('/**')
                ->writeln(' * CG library enhanced proxy class.')
                ->writeln(' *')
                ->writeln(' * This code was generated automatically by the CG library, manual changes to it')
                ->writeln(' * will be lost upon next generation.')
                ->writeln(' */')
            ;
            $docBlock = $writer->getContent();
        }

        $this->generatedClass = new PhpClass();
        $this->generatedClass->setDocblock($docBlock);
        $this->generatedClass->setName($this->getClassName($this->class));
        $this->generatedClass->setParentClassName('\\'.$this->class->name);

        if (!empty($this->interfaces)) {
            $this->generatedClass->setInterfaceNames(array_map(function($v) { return '\\'.$v; }, $this->interfaces));

            foreach ($this->getInterfaceMethods() as $method) {
                $method = PhpMethod::fromReflection($method);
                $method->setAbstract(false);

                $this->generatedClass->setMethod($method);
            }
        }

        if (!empty($this->generators)) {
            foreach ($this->generators as $generator) {
                $generator->generate($this->class, $this->generatedClass);
            }
        }

        return $this->generateCode($this->generatedClass);
    }

    /**
     * Adds stub methods for the interfaces that have been implemented.
     */
    protected function getInterfaceMethods()
    {
        $methods = array();

        foreach ($this->interfaces as $interface) {
            $ref = new \ReflectionClass($interface);
            $methods = array_merge($methods, $ref->getMethods());
        }

        return $methods;
    }
}