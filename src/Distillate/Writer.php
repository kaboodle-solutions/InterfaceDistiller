<?php

namespace Kaboodle\InterfaceDistiller\Distillate;

class Writer
{
    /**
     * Characters used for indentation
     */
    const INDENT = '    ';

    /**
     * @var \SplFileObject
     */
    protected $fileObject;

    /**
     * @var bool
     */
    protected $inGlobalNamespace;

    /**
     * @var bool
     */
    protected $convertParametersToCamelCase;

    /**
     * @param \SplFileObject $fileObject
     */
    public function __construct(\SplFileObject $fileObject)
    {
        $this->fileObject = $fileObject;
    }

    /**
     * @param Accessors $distillate
     * @return void
     */
    public function writeToFile(Accessors $distillate, bool $convertParametersToCamelCase = false)
    {
        $this->convertParametersToCamelCase = $convertParametersToCamelCase;
        $this->writeString('<?php' . PHP_EOL);
        $this->writeInterfaceSignature(
            $distillate->getInterfaceName(),
            $distillate->getExtendingInterfaces()
        );
        $this->writeString('{' . PHP_EOL);
        $this->writeMethods($distillate->getInterfaceMethods());
        $this->writeString('}');
    }

    /**
     * @param string $string
     * @return void
     */
    protected function writeString($string)
    {
        $this->fileObject->fwrite($string);
    }

    /**
     * @param string $interfaceName
     * @param string $extendingInterfaces
     * @return void
     */
    protected function writeInterfaceSignature($interfaceName, $extendingInterfaces = '')
    {
        $nameParts = explode('\\', $interfaceName);
        $interfaceShortName = array_pop($nameParts);
        if ($nameParts) {
            $this->writeString('namespace ' . implode('\\', $nameParts) . ';' . PHP_EOL);
            $this->inGlobalNamespace = false;
        } else {
            $this->inGlobalNamespace = true;
        }
        $this->writeString("interface $interfaceShortName");
        if ($extendingInterfaces) {
            $this->writeString(" extends $extendingInterfaces");
        }
        $this->writeString(PHP_EOL);
    }

    /**
     * @param array $methods
     */
    protected function writeMethods(array $methods)
    {
        foreach ($methods as $method) {
            $this->writeDocCommentOfMethod($method);
            $this->writeMethod($method);
            $this->writeString(PHP_EOL);
        }
    }

    /**
     * @param \ReflectionMethod $method
     * @return void
     */
    protected function writeMethod(\ReflectionMethod $method)
    {
        $this->writeString(
            sprintf(
                static::INDENT . 'public%sfunction %s(%s)%s;',
                $method->isStatic() ? ' static ' : ' ',
                $method->name,
                $this->methodParametersToString($method),
                $this->methodReturnToString($method)
            )
        );
    }

    /**
     * @param \ReflectionMethod $method
     * @return void
     */
    protected function writeDocCommentOfMethod(\ReflectionMethod $method)
    {
        if ($method->getDocComment()) {
            $this->writeString(static::INDENT);
            $this->writeString($method->getDocComment());
            $this->writeString(PHP_EOL);
        }
    }

    /**
     * @param \ReflectionMethod $method
     * @return string
     */
    protected function methodParametersToString(\ReflectionMethod $method)
    {
        return implode(
            ', ',
            array_map(
                array($this, 'parameterToString'),
                $method->getParameters()
            )
        );
    }

    /**
     * @param \ReflectionParameter $parameter
     * @return string
     */
    protected function parameterToString(\ReflectionParameter $parameter)
    {
        $classPrefix = $this->inGlobalNamespace ? '' : '\\';
        $typeString = "";
        $nullable = $parameter->allowsNull();
        $optional = $parameter->isOptional();
        $type = $parameter->getType();
        if (!is_null($type)) {
            $typeString = ($nullable && !$optional ? "?" : "") . ($type->isBuiltin() ? "" : $classPrefix) . $type;
        }
        $parameterName = $this->convertParametersToCamelCase ? lcfirst(ucwords($parameter->name, '-')) : $parameter->name;

        return trim(
            sprintf(
                '%s %s$%s%s',
                $typeString,
                $parameter->isPassedByReference() ? '&' : '',
                $parameterName,
                $this->resolveDefaultValue($parameter)
            )
        );
    }

    /**
     * @param \ReflectionParameter $reflectionParameter
     * @return string
     */
    protected function resolveTypeHint(\ReflectionParameter $reflectionParameter)
    {
        if ($reflectionParameter->getDeclaringClass()->inNamespace()) {
            $typeHint = $reflectionParameter->getClass();
            return $typeHint->isInternal()
                ? '\\' . $typeHint->getName()
                : $typeHint->getName();
        }

        return $reflectionParameter->getClass()->getName();
    }

    /**
     * @param \ReflectionParameter $parameter
     * @return string
     */
    protected function resolveDefaultValue(\ReflectionParameter $parameter)
    {
        if (false === $parameter->isOptional()) {
            return '';
        }

        if ($parameter->isDefaultValueAvailable()) {
            $defaultValue = var_export($parameter->getDefaultValue(), true);
            return ' = ' . preg_replace('(\s)', '', $defaultValue);
        }

        return $this->handleOptionalParameterWithUnresolvableDefaultValue($parameter);
    }

    /**
     * @param \ReflectionParameter $parameter
     * @return string
     */
    protected function handleOptionalParameterWithUnresolvableDefaultValue(\ReflectionParameter $parameter)
    {
        return $parameter->allowsNull() ? ' = NULL ' : ' /* = unresolvable */ ';
    }

    /**
     * @param \ReflectionMethod $method
     * @return string
     */
    protected function methodReturnToString(\ReflectionMethod $method)
    {
        $classPrefix = $this->inGlobalNamespace ? '' : '\\';
        $returnType = $method->getReturnType();
        if (is_null($returnType)) {
            return "";
        }

        return trim(
            sprintf(
                ': %s%s%s',
                $returnType->allowsNull() ? "?" : "",
                ($returnType->isBuiltin() || (string) $returnType == "self") ? "" : $classPrefix,
                $returnType
            )
        );
    }
}
