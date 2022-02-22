<?php

/**
 * Exception thrown when a recursion loop is detected during resolution.
 *
 * @package StellarWP\Container
 */

namespace StellarWP\Container\Exceptions;

class RecursiveDependencyException extends ContainerException
{
}
