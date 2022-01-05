<?php

namespace MhsDesign\FusionTypeHints\Fusion;

// TODO: \TypeError is used so the it escapes the exception handling of the Runtime and hits through.
// because \TypeError instanceof \Exception === false
class RuntimeTypeException extends \TypeError
{
}
