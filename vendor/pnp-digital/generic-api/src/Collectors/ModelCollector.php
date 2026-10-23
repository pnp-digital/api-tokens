<?php

namespace Pipeandpiper\API\Collectors;

use Pipeandpiper\API\Transformers\ModelTypescriptTransformer;
use ReflectionClass;
use Spatie\TypeScriptTransformer\Collectors\DefaultCollector;
use Spatie\TypeScriptTransformer\Exceptions\TransformerNotFound;
use Spatie\TypeScriptTransformer\Structures\TransformedType;
use Spatie\TypeScriptTransformer\TypeReflectors\ClassTypeReflector;

class ModelCollector extends DefaultCollector
{
    /**
     * Allows collection of fake models used for TypeScript transformation
     *
     * @param ReflectionClass<object> $class
     * @return TransformedType|null
     * @throws TransformerNotFound
     */
    public function getTransformedType(ReflectionClass $class): ?TransformedType
    {
        /** @var array<int, object> $configuredTransformers */
        $configuredTransformers = $this->config->getTransformers();
        $transformers = array_map(fn($transformer) => get_class($transformer), $configuredTransformers);

        if (!in_array(ModelTypescriptTransformer::class, $transformers, true)) {
            return null;
        }

        $reflector = ClassTypeReflector::create($class);

        if ($reflector->getReflectionClass()->getNamespaceName() != 'Typescript\\Generated') {
            return null;
        }

        /** @var TransformedType $transformedType */
        $transformedType = $reflector->getType()
            ? $this->resolveAlreadyTransformedType($reflector)
            : $this->resolveTypeViaTransformer($reflector);

        if ($reflector->isInline()) {
            $transformedType->name = null;
            $transformedType->isInline = true;
        }

        return $transformedType;
    }
}
