<?php

$url = ee('CP/URL', 'addons')->compile();

ee('CP/Alert')->makeInline('shared-form')
    ->asWarning()
    ->cannotClose()
    ->withTitle('Update Available')
    ->addToBody(sprintf('Please run available DataGrab update from the <a href="%s">Add-Ons page</a>.', $url))
    ->now();

echo ee('CP/Alert')->get('shared-form');
