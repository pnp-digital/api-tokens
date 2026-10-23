<?php

namespace Pipeandpiper\API\Transformers;

use ReflectionClass;
use ReflectionException;
use Spatie\TypeScriptTransformer\Structures\MissingSymbolsCollection;
use Spatie\TypeScriptTransformer\Structures\TransformedType;
use Spatie\TypeScriptTransformer\Transformers\Transformer;
use Spatie\TypeScriptTransformer\Transformers\TransformsTypes;

class ModelTypescriptTransformer implements Transformer
{
    use TransformsTypes;

    /**
     * @param ReflectionClass<object> $class
     */
    public function canTransform(ReflectionClass $class): bool
    {
        return true;
    }

    /**
     * @param ReflectionClass<object> $class
     * @throws ReflectionException
     */
    public function transform(ReflectionClass $class, string $name): TransformedType
    {
        return TransformedType::create(
            $class,
            $name,
            "{{$this->resolveOptions($class)}}"
        );
    }

    /**
     * @param ReflectionClass<object> $class
     * @throws ReflectionException
     */
    private function resolveOptions(ReflectionClass $class): string
    {
        $namespacePrefix = 'Typescript\\Generated\\';
        $reflectionClassName = $namespacePrefix . $class->getShortName();
        $reflectionClass = new ReflectionClass(new $reflectionClassName());

        $reflectionProperties = $reflectionClass->getProperties(\ReflectionProperty::IS_PUBLIC);
        $missingSymbols = new MissingSymbolsCollection();

        $properties = array_map(
            fn(\ReflectionProperty $reflection) => "{$reflection->name}: {$this->reflectionToTypeScript($reflection, $missingSymbols)};",
            $reflectionProperties
        );

        return implode($properties);
    }
}
