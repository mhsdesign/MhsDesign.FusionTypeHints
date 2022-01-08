<?php

namespace MhsDesign\FusionTypeHints\Fusion;

use Duck\Types\IncompatibleTypeError;
use Duck\Types\Registry;
use Duck\Types\Type;
use Neos\Fusion\Core\Runtime;

class TypedRuntime extends Runtime
{
    /**
     * Empty override, to avoid injection settings of this 3. party package
     */
    public function injectSettings(array $settings)
    {
    }

    /**
     * Inject settings of the Neos.Fusion package, and let the original runtime handle them.
     * Configured via Objects.yaml
     */
    public function injectFusionSettings(array $settings)
    {
        parent::injectSettings($settings);
    }

    /**
     * A wrapper around Runtime->evaluate for general paths
     * If a relative @ type path exists to the $fusionPath,
     * The library attitude\duck-types-php will then check if the path value complies to the type annotation
     */
    public function evaluate(string $fusionPath, $contextObject = null, string $behaviorIfPathNotFound = self::BEHAVIOR_RETURNNULL)
    {
        self::addDuckTypeAutoloader();

        $fusionConfiguration = $this->runtimeConfiguration->forPath($fusionPath);
        $pathValue = parent::evaluate($fusionPath, $contextObject, $behaviorIfPathNotFound);
        if (isset($fusionConfiguration['__meta']['type']) === false) {
            return $pathValue;
        }
        $typeHint = $fusionConfiguration['__meta']['type'];
        try {
            Type::is($typeHint, $pathValue);
        } catch (IncompatibleTypeError $e) {
            throw new RuntimeTypeException("Runtime Type checking for '$fusionPath': " . $e->getMessage(),1641301766);
        }
        return $pathValue;
    }

    /**
     * A wrapper around Runtime->evaluateObjectOrRetrieveFromCache for fusion object paths
     * This wrapper will check for the @ return meta path and continue respective to TypedRuntime->evaluate
     * Technically, @ return is not necessary and just an alias for @ type
     * - i dont even know if this wrapper is useful, as every path has a fusion ... right? so @ return could be implemented as alias in TypedRuntime->evaluate?
     */
    protected function evaluateObjectOrRetrieveFromCache($fusionObject, $fusionPath, $fusionConfiguration, $cacheContext)
    {
        self::addDuckTypeAutoloader();

        $objectReturnValue = parent::evaluateObjectOrRetrieveFromCache($fusionObject, $fusionPath, $fusionConfiguration, $cacheContext);

        if (isset($fusionConfiguration['__meta']['return']) === false) {
            return $objectReturnValue;
        }

        $typeHint = $fusionConfiguration['__meta']['return'];
        try {
            Type::is($typeHint, $objectReturnValue);
        } catch (IncompatibleTypeError $e) {
            throw new RuntimeTypeException("Runtime Type checking for object in '$fusionPath': " . $e->getMessage(), 1641304414);
        }

        return $objectReturnValue;
    }

    /**
     * The lib attitude\duck-types-php does provide support for some annotations:
     * https://github.com/attitude/duck-types-php/blob/main/docs/Annotation.md#supported-flow-annotations
     * For things like checking if an object is the correct one and for 'void' we hook into attitude\duck-types-php and extend it.
     */
    protected static function addDuckTypeAutoloader(): void
    {
        static $typesGeneratorsSet;
        if ($typesGeneratorsSet === true) {
            return;
        }
        $typesGeneratorsSet = true;

        Registry::registerAutoloader('phpTypesAutoloader', static function(string $requestedType) {
            switch (true) {
                case $requestedType === 'void';
                    Registry::set($requestedType, static function($checkAgainst) {
                        if(is_null($checkAgainst)) {
                            return;
                        }
                        throw new IncompatibleTypeError($checkAgainst, "incompatible with void");
                    });
                    return;

                // Object instanceof check:
                // based of class-name like: 'Neos\\ContentRepository\\Domain\\Projection\\Content\\TraversableNodeInterface'
                case strpos($requestedType, '\\') !== false:
                    Registry::set($requestedType, static function($checkAgainst) use($requestedType) {
                        if ($checkAgainst instanceof $requestedType) {
                            return;
                        }
                        throw new IncompatibleTypeError($checkAgainst, "incompatible with object $requestedType");
                    });
                    return;
            }
        });
    }
}
