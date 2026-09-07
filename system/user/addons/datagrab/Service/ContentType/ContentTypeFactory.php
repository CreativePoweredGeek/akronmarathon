<?php

namespace BoldMinded\DataGrab\Service\ContentType;

class ContentTypeFactory
{
    /**
     * @var ContentTypeInterface[]
     */
    private static array $instances = [];

    public static function make(string $importType = 'entry'): ContentTypeInterface
    {
        if (isset(self::$instances[$importType])) {
            return self::$instances[$importType];
        }

        self::$instances[$importType] = match ($importType) {
            'file' => new FileContentType(),
            'member' => new MemberContentType(),
            default => new ChannelContentType(),
        };

        return self::$instances[$importType];
    }

    /**
     * Resolve directly from a settings array.
     */
    public static function fromSettings(array $settings = []): ContentTypeInterface
    {
        return self::make($settings['import']['import_type'] ?? 'entry');
    }
}
