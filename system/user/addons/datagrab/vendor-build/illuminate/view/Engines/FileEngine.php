<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\View\Engines;

use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\View\Engine;
use BoldMinded\DataGrab\Dependency\Illuminate\Filesystem\Filesystem;
class FileEngine implements Engine
{
    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;
    /**
     * Create a new file engine instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     */
    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }
    /**
     * Get the evaluated contents of the view.
     *
     * @param  string  $path
     * @param  array  $data
     * @return string
     */
    public function get($path, array $data = [])
    {
        return $this->files->get($path);
    }
}
