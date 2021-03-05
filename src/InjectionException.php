<?php

namespace Amp\Injector;

final class InjectionException extends InjectorException
{
    /**
     * Add a human readable version of the invalid callable to the standard 'invalid invokable' message.
     *
     * @param array           $inProgressMakes
     * @param mixed           $callableOrMethodStr
     * @param \Throwable|null $previous
     *
     * @return InjectionException
     */
    public static function fromInvalidCallable(
        array $inProgressMakes,
        $callableOrMethodStr,
        ?\Throwable $previous = null
    ): self {
        $callableString = '';

        if (\is_string($callableOrMethodStr)) {
            $callableString = $callableOrMethodStr;
        } elseif (\is_array($callableOrMethodStr) && \array_key_exists(0, $callableOrMethodStr) && \array_key_exists(
            1,
            $callableOrMethodStr
        )) {
            if (\is_string($callableOrMethodStr[0]) && \is_string($callableOrMethodStr[1])) {
                $callableString = $callableOrMethodStr[0] . '::' . $callableOrMethodStr[1];
            } elseif (\is_object($callableOrMethodStr[0]) && \is_string($callableOrMethodStr[1])) {
                $callableString = \sprintf(
                    "[object(%s), '%s']",
                    \get_class($callableOrMethodStr[0]),
                    $callableOrMethodStr[1]
                );
            }
        }

        if ($callableString !== '') {
            // Prevent accidental usage of long strings from filling logs.
            $callableString = \substr($callableString, 0, 250);
            $message = \sprintf(
                "%s. Invalid callable was '%s'",
                Injector::M_INVOKABLE,
                $callableString
            );
        } else {
            $message = Injector::M_INVOKABLE;
        }

        return new self($inProgressMakes, $message, Injector::E_INVOKABLE, $previous);
    }

    /** @var string[] */
    private array $dependencyChain;

    public function __construct(array $inProgressMakes, $message = '', $code = 0, ?\Throwable $previous = null)
    {
        $this->dependencyChain = \array_flip($inProgressMakes);
        \ksort($this->dependencyChain);

        parent::__construct($message, $code, $previous);
    }

    /**
     * Returns the hierarchy of dependencies that were being created when
     * the exception occurred.
     *
     * @return string[]
     */
    public function getDependencyChain(): array
    {
        return $this->dependencyChain;
    }
}
