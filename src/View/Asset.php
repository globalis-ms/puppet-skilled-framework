<?php

namespace Globalis\PuppetSkilled\View;

/**
 * Asset helper class
 */
class Asset
{
    /**
     * Reference to the Asset singleton
     *
     * @var \Globalis\PuppetSkilled\View\Asset
     */
    protected static $instance;

    /**
     * Base path to css
     *
     * @var string
     */
    protected $htmlStylePath;

    /**
     * Base path to javascript
     *
     * @var string
     */
    protected $htmlScriptPath;

    /**
     * Base path to images
     *
     * @var string
     */
    protected $htmlImagePath;

    /**
     * Asset version
     *
     * @var string
     */
    protected $assetVersion;

    /**
     * Asset version insert function
     *
     * @var string
     */
    protected $assetInsertMethod;

    /**
     * Loading styles files
     *
     * @var array
     */
    protected $styles = [];

    /**
     * Loading script files
     *
     * @var array
     */
    protected $scripts = [];

    /**
     * Script csp none
     * @var array
     */
    protected $scriptNonces = [];

    /**
     * Inline script
     *
     * Build with $this->enqueueInlineScriptStart() and $this->enqueueInlineScriptEnd()
     *
     * @var string
     */
    protected $scriptInline = '';

    /**
     * Constructor
     * @param array $config Config params
     *                      <code>
     *                          $config = [
     *                              html_style_path => 'public/css/',
     *                              html_script_path => 'public/js/',
     *                              html_image_path => 'public/images/',
     *                              style_autoload => ['bootstrap.css'], // Autoload css
     *                              script_autoload => ['jquery', 'boostrap.css'], // Autoload javascript
     *                          ];
     *                      </code>
     */
    public function __construct($config = [])
    {
        $this->htmlStylePath = isset($config['html_style_path']) ? trim($config['html_style_path'], '/') . '/' : 'public/css/';
        $this->htmlScriptPath = isset($config['html_script_path']) ? trim($config['html_script_path'], '/') . '/' : 'public/js/';
        $this->htmlImagePath = isset($config['html_image_path']) ? trim($config['html_image_path'], '/') . '/' : 'public/images/';
        $this->assetVersion = isset($config['html_asset_version']) ? $config['html_asset_version'] : '';
        $this->assetInsertMethod = isset($config['html_asset_version_insert_method']) ? $config['html_asset_version_insert_method'] : [$this, 'insertVersionInUrl'];

        if (isset($config['style_autoload'])) {
            foreach ($config['style_autoload'] as $style) {
                $this->enqueueStyle($style);
            }
        }

        if (isset($config['script_autoload'])) {
            foreach ($config['script_autoload'] as $script) {
                $this->enqueueScript($script);
            }
        }
        self::$instance = $this;
    }

    protected function insertVersionInUrl($url, $version)
    {
        if ($version) {
            return $url . '?v=' .$version;
        }

        return $url;
    }

    /**
     * Display links to loaded css files
     *
     * @return void
     */
    public function printStyles()
    {
        foreach ($this->styles as $key => $options) {
            $href = (is_string($options['src'])) ? $options['src'] : $this->getStyleLink($key);
            if ($options['with_version']) {
                $href = call_user_func_array($this->assetInsertMethod, [$href, $this->assetVersion]);
            }
            echo "<link rel='stylesheet'  href='" . $href . "' type='text/css' media='" . $options["media"] . "' />\n";
        }
    }

    /**
     * Add css file to the loader
     *
     * @param  string  $slug    Slug or source file
     * @param  mixed   $src     Source file, if src = false src = slug
     * @param  string  $media   Media link type default = all
     * @return void
     */
    public function enqueueStyle($slug, $src = false, $media = 'all', $withVersion = true)
    {
        $this->styles[$slug] = [
            'src' => $src,
            'media' => $media,
            'with_version' => $withVersion,
        ];
    }

    /**
     * Dispaly or return script balsie with all inline script loaded
     *
     * @param  boolean $print If true print (default true)
     * @return mixed
     */
    public function printInlineScript($print = true)
    {
        $this->scriptNonces[] = ($nonce = $this->newNonce());
        if ($print) {
            echo  "<script type='text/javascript' nonce='".$nonce."'>" . $this->scriptInline . "</script>\n";
        } else {
            return  "<script type='text/javascript' nonce='".$nonce."'>" . $this->scriptInline . "</script>\n";
        }
    }

    protected function newNonce()
    {
        $return = '';
        for ($i = 0; $i < 8; $i++) {
            $return .= mt_rand(0, 255);
        }
        return $return;
    }

    public function getScriptNonces()
    {
        return $this->scriptNonces;
    }

    /**
     * Display links to loaded javascripts files and loaded inline script
     *
     * @return void
     */
    public function printScript()
    {
        foreach ($this->scripts as $key => $options) {
            $src = (is_string($options['src'])) ? $options['src'] : $this->getScriptLink($key);
            if ($options['with_version']) {
                $src = call_user_func_array($this->assetInsertMethod, [$src, $this->assetVersion]);
            }
            echo "<script type='text/javascript' src='$src'></script>\n";
        }
        $this->printInlineScript();
    }

    /**
     * Add javascript file to the loader
     *
     * @param  string  $slug    Slug or source file
     * @param  mixed   $src     Source file, if src = false src = slug
     * @return void
     */
    public function enqueueScript($slug, $src = false, $withVersion = true)
    {
        $this->scripts[$slug] = [
            'src' => $src,
            'with_version' => $withVersion,
        ];
    }

    /**
     * Start to enqueue inline script
     *
     * @return void
     */
    public function enqueueInlineScriptStart()
    {
        ob_start();
    }

    /**
     * Stop to enqueue inline script
     * @return void
     */
    public function enqueueInlineScriptEnd()
    {
        $script = str_replace(['<script>', '</script>'], ['', ''], ob_get_clean());
        $this->enqueueInlineScript($script);
    }

    /**
     * Add inline script
     *
     * @param  string $script
     * @return void
     */
    public function enqueueInlineScript($script)
    {
        $this->scriptInline .= $script."\n";
    }

    /**
     * Return url to the css file
     *
     * @param  string $filename
     * @return string
     */
    public function getStyleLink($filename)
    {
        return base_url() . $this->htmlStylePath . preg_replace('/(.*)\.css$/', '$1', $filename) . '.css';
    }

    /**
     * Return url to the javascript file
     *
     * @param  string $filename
     * @return string
     */
    public function getScriptLink($filename)
    {
        return base_url() . $this->htmlScriptPath . preg_replace('/(.*)\.js/', '$1', $filename) . '.js';
    }

    /**
     * Return url to the image file
     *
     * @param  string $filename
     * @return string
     */
    public function getImageLink($filename)
    {
        return base_url() . $this->htmlImagePath . $filename;
    }

    /**
     * Get the Asset singleton
     *
     * @return \Globalis\PuppetSkilled\View\Asset
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            new self();
        }
        return self::$instance;
    }
}
