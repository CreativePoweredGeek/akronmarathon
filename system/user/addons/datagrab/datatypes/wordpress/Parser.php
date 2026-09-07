<?php

namespace BoldMinded\DataGrab\datatypes\wordpress;

use BoldMinded\DataGrab\datatypes\wordpress\Parsers\SimpleXML;
use DOMDocument;

class Parser
{
    public static function parseString(string $content, string $postType)
    {
        if (!extension_loaded('simplexml')) {
            throw new \Exception('Simplexml extension not loaded');
        }

        $parser = new SimpleXML;

        $dom = new DOMDocument();

        // Hard-deny external entity resolution (XXE) and network access during parse.
        libxml_set_external_entity_loader(static fn() => null);
        $success = $dom->loadXML(trim($content), LIBXML_NONET);

        if ( ! $success || isset($dom->doctype) ) {
            throw new \Exception( 'There was an error when reading this WXR file' );
        }

        $result = $parser->parseString(simplexml_import_dom($dom), $postType);

        return $result;
    }
}
