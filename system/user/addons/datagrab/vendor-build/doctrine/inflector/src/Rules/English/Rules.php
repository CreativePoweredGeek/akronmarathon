<?php

declare (strict_types=1);
namespace BoldMinded\DataGrab\Dependency\Doctrine\Inflector\Rules\English;

use BoldMinded\DataGrab\Dependency\Doctrine\Inflector\Rules\Patterns;
use BoldMinded\DataGrab\Dependency\Doctrine\Inflector\Rules\Ruleset;
use BoldMinded\DataGrab\Dependency\Doctrine\Inflector\Rules\Substitutions;
use BoldMinded\DataGrab\Dependency\Doctrine\Inflector\Rules\Transformations;
final class Rules
{
    public static function getSingularRuleset() : Ruleset
    {
        return new Ruleset(new Transformations(...Inflectible::getSingular()), new Patterns(...Uninflected::getSingular()), (new Substitutions(...Inflectible::getIrregular()))->getFlippedSubstitutions());
    }
    public static function getPluralRuleset() : Ruleset
    {
        return new Ruleset(new Transformations(...Inflectible::getPlural()), new Patterns(...Uninflected::getPlural()), new Substitutions(...Inflectible::getIrregular()));
    }
}
