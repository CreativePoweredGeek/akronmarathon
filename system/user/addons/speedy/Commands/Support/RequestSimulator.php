<?php

namespace BoldMinded\Speedy\Commands\Support;

/**
 * A minimal stand-in for EE_Template used by the speedy:generate command.
 *
 * mod.speedy.php's static() and fragment() methods read everything they need
 * from ee()->TMPL (params, tagdata, the final parsed template, etc.). During a
 * real page render EE populates a full EE_Template for us. From the CLI there is
 * no request, so this class emulates just the handful of properties and methods
 * the module touches, allowing us to drive those methods "as if it were an EE
 * request" and exercise the full caching lifecycle.
 *
 * It intentionally does NOT extend EE_Template — we only implement what Speedy
 * reads, and the few parse_* methods are pass-throughs because the content we
 * generate is plain text with no EE variables to expand.
 */
class RequestSimulator
{
    /** @var array<string, string> Mirrors EE_Template::$tagparams */
    public $tagparams = [];

    /** @var array Mirrors EE_Template::$tag_data */
    public $tag_data = [];

    /** @var string Mirrors EE_Template::$tagchunk */
    public $tagchunk = '';

    /** @var string Mirrors EE_Template::$tagdata */
    public $tagdata = '';

    /** @var string The parsed page body; read by handleShutdown() for static caching. */
    public $final_template = '';

    /** @var array Mirrors EE_Template::$tagparts */
    public $tagparts = [];

    /** @var array Mirrors EE_Template::$layout_vars */
    public $layout_vars = [];

    /** @var int Mirrors EE_Template::$template_edit_date */
    public $template_edit_date = 0;

    // --- Properties copied onto the fresh EE_Template in Speedy::parseAsTemplate ---

    /** @var float Mirrors EE_Template::$start_microtime */
    public $start_microtime = 0.0;

    /** @var int Mirrors EE_Template::$depth */
    public $depth = 0;

    /** @var array Mirrors EE_Template::$plugins (list of installed plugins) */
    public $plugins = [];

    /** @var array Mirrors EE_Template::$modules (list of installed modules) */
    public $modules = [];

    /** @var array Mirrors EE_Template::$log; parseAsTemplate copies log entries back. */
    public $log = [];

    /**
     * Set the per-item tag parameters (key, ttl, tags, driver, global, etc.).
     */
    public function setParams(array $params): self
    {
        $this->tagparams = $params;

        return $this;
    }

    /**
     * Set the content to be cached and wire up the tag_data/tagchunk pair that
     * Speedy::getTagdata() walks to recover the raw block.
     */
    public function setContent(string $content): self
    {
        $this->tagchunk = $content;
        $this->tagdata = $content;
        $this->final_template = $content;
        $this->tag_data = [
            [
                'chunk' => $content,
                'block' => $content,
            ],
        ];

        return $this;
    }

    /**
     * Mirrors EE_Template::fetch_param(), including the yes/no normalization
     * Speedy relies on for its === 'yes' / === 'no' checks.
     */
    public function fetch_param($which, $default = false)
    {
        if (!isset($this->tagparams[$which])) {
            return $default;
        }

        switch ($this->tagparams[$which]) {
            case 'y':
            case 'on':
                return 'yes';
            case 'n':
            case 'off':
                return 'no';
            default:
                return $this->tagparams[$which];
        }
    }

    // ---- Pass-throughs used by the fragment() return path -------------------
    // Our generated content is plain text, so there is nothing to parse; we just
    // return it unchanged so the module's processReturn()/parseVars() chain runs
    // without needing a full template parser.

    public function remove_ee_comments($tagdata)
    {
        return $tagdata;
    }

    public function parse_variables_row($tagdata, $row = [])
    {
        return $tagdata;
    }

    public function parse_date_variables($tagdata, $dates = [])
    {
        return $tagdata;
    }
}
