<?php

declare (strict_types=1);
namespace BoldMinded\DataGrab\Dependency\Carbon\Doctrine;

use BoldMinded\DataGrab\Dependency\Carbon\Carbon;
use DateTime;
use BoldMinded\DataGrab\Dependency\Doctrine\DBAL\Platforms\AbstractPlatform;
use BoldMinded\DataGrab\Dependency\Doctrine\DBAL\Types\VarDateTimeType;
class DateTimeType extends VarDateTimeType implements CarbonDoctrineType
{
    /** @use CarbonTypeConverter<Carbon> */
    use CarbonTypeConverter;
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform) : ?Carbon
    {
        return $this->doConvertToPHPValue($value);
    }
}
