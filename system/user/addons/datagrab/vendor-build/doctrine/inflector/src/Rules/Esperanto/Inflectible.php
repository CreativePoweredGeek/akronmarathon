<?php

declare (strict_types=1);
namespace BoldMinded\DataGrab\Dependency\Doctrine\Inflector\Rules\Esperanto;

use BoldMinded\DataGrab\Dependency\Doctrine\Inflector\Rules\Pattern;
use BoldMinded\DataGrab\Dependency\Doctrine\Inflector\Rules\Substitution;
use BoldMinded\DataGrab\Dependency\Doctrine\Inflector\Rules\Transformation;
use BoldMinded\DataGrab\Dependency\Doctrine\Inflector\Rules\Word;
class Inflectible
{
    /** @return Transformation[] */
    public static function getSingular() : iterable
    {
        (yield new Transformation(new Pattern('oj$'), 'o'));
    }
    /** @return Transformation[] */
    public static function getPlural() : iterable
    {
        (yield new Transformation(new Pattern('o$'), 'oj'));
    }
    /** @return Substitution[] */
    public static function getIrregular() : iterable
    {
        (yield new Substitution(new Word(''), new Word('')));
    }
}
