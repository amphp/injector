<?php

namespace Amp\Injector\Internal;

use Amp\Injector\Arguments;
use Amp\Injector\InjectionException;
use Amp\Injector\Injector;
use Amp\Injector\Meta\Argument;
use Amp\Injector\Meta\Executable;
use Amp\Injector\Provider;
use Amp\Injector\Provider\FactoryProvider;
use function Amp\Injector\value;

/** @internal */
final class ExecutableWeaver
{
    /**
     * @throws InjectionException
     */
    public static function build(Executable $executable, Arguments $arguments, Injector $injector): Provider
    {
        return new FactoryProvider($executable, ...self::buildArguments($executable, $arguments, $injector));
    }

    /**
     * @param Executable $executable
     * @param Arguments $arguments
     * @param Injector $injector
     * @return Argument[]
     *
     * @throws InjectionException
     */
    private static function buildArguments(Executable $executable, Arguments $arguments, Injector $injector): array
    {
        $parameters = $executable->getParameters();
        $count = \count($parameters);
        $variadic = null;
        $args = [];

        for ($index = 0; $index < $count; $index++) {
            $parameter = $parameters[$index];

            $definition = $arguments->getDefinition($parameter);
            $definition ??= $parameter->isOptional() ? value($parameter->getDefaultValue()) : null;

            if ($definition === null) {
                $type = $parameter->getType();
                if ($type && $type->isNullable()) {
                    $definition = value(null);
                }
            }

            $definition ??= throw new InjectionException('Could not find a suitable definition for ' . $parameter);

            $args[$index] = new Argument($parameter, $definition->build($injector));

            if ($parameter->isVariadic()) {
                $variadic = $parameter;
            }
        }

        // TODO
        // if ($variadic) {
        //     $variadicArguments = $this->getVariadicArguments($count - 1, $variadic);
        //     foreach ($variadicArguments as $index => $argument) {
        //         $args[$index] = $argument;
        //     }
        // }

        return $args;
    }
}
