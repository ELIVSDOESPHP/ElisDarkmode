<?php

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;

// No direct access
defined('_JEXEC') or die;

/**
 * ElisDarkmode Plugin Base Class
 *
 * @author      Elias Ritter
 * @license     GNU General Public License (GPL) 2.0 or later
 *
 * @version     1.1 | 03.2023
 * @package     ElisDarkmode Plugin
 * @since       version 1.0
 * @copyright   2023 Elias Ritter
 */
class PlgSystemDarkmode extends CMSPlugin
{

    /**
     * @var boolean         Active / deactivate Logs in the Client's Browser-Console
     * @since               version 1.0
     */
    private $loginconsole = false;

    /**
     * @var array           All darkmode Javascripts as an array
     * @since               version 1.0
     */
    private $darkmode_scripts;

    /**
     * @var array           All lightmode Javascripts as an array
     * @since               version 1.0
     */
    private $lightmode_scripts;

    /**
     * @var object          The Output Object of the Repeateable Field
     * @since               version 1.0
     */
    private $variables;

    /**
     * @var string|null     The Cookie that indicates the current Mode
     * @since               version 1.0
     */
    private $inheritedStyle;

    /**
     * @var array           The Scripts, that are included into the Head
     * @since               version 1.0
     */
    private $outputScripts = array();

    /**
     * @var array           The Styles, that are included into the Head
     * @since               version 1.0
     */
    private $outputStyles = array();

    /**
     * @var boolean         The Frontend-Indicator
     * @since               version 1.0
     */
    public $issite;

    /**
     * @var object          An Object containing animation-specific Properties
     * @since               version 1.0
     */
    private $animation;

    /**
     * @var Iterator        The main Property Iterator
     * @since               version 1.0
     */
    private $fieldIterator;

    /* === Joomla! Objects === */

    /**
     * @var Joomla\Registry\Registry
     * @since 1.0
     */
    public $params;

    /**
     * @var Joomla\CMS\Application\SiteApplication
     * @since 3.2
     */
    public $app;

    /**
     * @var Joomla\CMS\WebAsset\WebAssetManager
     * @since 4.0.0
     */
    public $wam;

    /**
     * The Main Constructor
     *
     * @param $subject
     * @param $config
     * @since version 1.0
     */
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);

        $this->issite = $this->app->isClient('site');

        $this->variables = $this->params->get('variables', new stdClass());
    }

    /**
     * This event is triggered immediately before the
     * framework has rendered the application.
     *
     * @link    https://docs.joomla.org/Plugin/Events/System
     * @throws  Exception
     * @since   version 1.0
     * @return  void
     */
    public function onBeforeRender(): void
    {
        try
        {
            if ($this->issite) {
                // Check if a repeateable field exists and is valid
                if (empty($this->variables) || $this->fieldIterator->count() == 1 && empty($this->variables->variables0->name)) return;

                // If Button Rendering is activated, render it
                if ($this->params->get('showButtons') == 'true' && !empty($this->params->get('buttonclass')))
                    $this->createButton();
            }

            // Get an Instance of the Joomla!-WebAssetManager
            $this->wam = Factory::getApplication()
                ->getDocument()
                ->getWebAssetManager();

            if ($this->issite) {

                // Clean and restart the Output Buffer
                static::resetOutputBuffer();

                // Adding a function for setting a Cookie
                ?>
                function setCookie(c_name,value,exdays)
                {
                var exdate=new Date();
                exdate.setDate(exdate.getDate() + exdays);
                var c_value=escape(value) + ((exdays==null)
                ? "" : "; expires="+exdate.toUTCString())
                + "; path=/";
                document.cookie=c_name + "=" + c_value;
                }
                <?php
                $this->outputScripts[] = trim(ob_get_clean());

                // Include all Scripts and styles into the HTML-Head
                foreach ($this->outputScripts as $script)
                {
                    $this->importCleanResource($script, 'javascript');
                }

                foreach ($this->outputStyles as $style)
                {
                    $this->importCleanResource($style, 'style');
                }
            }
        }
        catch (Throwable $e)
        {
            $this->displayError($e);
            return;
        }
    }

    /**
     * Imports and minifies a script or style using
     * the Joomla! WebAssetManager
     *
     * @param string $data The Data to minify
     * @param string $type The type of Data
     * @param bool $minify Activate or deactivate Minifying
     * @return void
     * @since version 1.0
     */
    private function importCleanResource(string $data, string $type, bool $minify = true): void
    {
        if($minify) {
            $data = preg_replace('/\s+/', ' ', $data);
        }

        switch ($type) {
            case 'style':
                $this->wam->addInlineStyle($data);
                return;

            case 'javascript':
                $this->wam->addInlineScript($data);
                return;
        }
    }

    /**
     * Includes neccessary Assets into the Template
     * for Animation Playback
     *
     * @return void
     * @since version 1.0
     */
    private function animationHandler(): void
    {

        // Build the animation Configuration Object
        $this->animation                        = new stdClass();
        $this->animation->animation_name        = 'EDM_' . rand(100, 999);
        $this->animation->animation_duration    = $this->params->get('animation-duration');
        unset($randnum);

        $duration = $this->animation->animation_duration . 'ms';

        // Clean and restart the Output Buffer
        static::resetOutputBuffer();

        // Set the Animation Time
        ?>
        document.documentElement.style.setProperty('animation-duration', '<?=$duration ?>');
        document.documentElement.style.setProperty('scroll-behavior', 'smooth');
        <?php
        $this->outputScripts[] = trim(ob_get_clean());

        // Clean and restart the Output Buffer
        static::resetOutputBuffer();

        // Include the Animation into the Template
        ?>
        @-webkit-keyframes <?=$this->animation->animation_name ?> {0% {opacity: 0} to {opacity: 1}}
        @keyframes <?=$this->animation->animation_name ?> {0% {opacity: 0} to {opacity: 1}}
        <?php
        $this->outputStyles[] = trim(ob_get_clean());
    }

    /**
     * Throws an Error Message for Error Handling without crashing Joomla!
     *
     * @param   Error $e The Error-Object
     * @throws  Exception
     * @since   version
     */
    private function displayError(Throwable $e)
    {

        // Create an Object containing all needed parts for the Error Message
        $message            = new stdClass();
        $message->message   = $e->getMessage();
        $message->line      = $e->getLine();
        $message->filepath  = $e->getFile();
        $message->extra     = 'Please contact the Administrator';
        $message->prefix    = 'ElisDarkmode: ';

        // Creating the Error Message and displaying it
        $line = $message->prefix . $message->message . ' on line ' . $message->line . $message->filepath . '. ' . $message->extra;
        JFactory::getApplication()->enqueueMessage($line, 'error');
    }

    /**
     * Is executed after the Framework has been loaded
     *
     * @link    https://docs.joomla.org/Plugin/Events/System
     * @return  void
     * @since   1.0
     * @throws  Exception
     */
    public function onAfterInitialise(): void
    {
        try
        {
            if($this->params->get('animate') == 'true')
            {
                $this->animationHandler();
            }

            // Get The current Mode Cookie
            $this->inheritedStyle = $this->app->input->cookie->get('edmstyle');

            // If Console Logging is activated, set Console Logging to 'active' in Class
            if ($this->params->get('loginconsole') == 'true') $this->loginconsole = true;

            // Create all neccessary Javascript Code Snippets for Style processing
            $this->darkmode_scripts     = $this->createScripts('dark');
            $this->lightmode_scripts    = $this->createScripts('light');

            // If no Scripts are Set, close Program Execution
            if (empty($this->darkmode_scripts) || empty($this->lightmode_scripts)) return;

            // Create the Output Script
            $this->createOutputScript();

        } catch (Throwable $e)
        {
            $this->displayError($e);
            return;
        }
    }

    /**
     * Create Code Snippets used for Switching between Styles
     *
     * @param string $mode  The Mode to get the Contents of
     * @return array        The Code Snippets in an Array or null on failure
     * @since               1.0
     * @throws Exception    Throws an Exception if the Iterator ran too many times
     */
    private function createScripts(string $mode): array
    {
        $scripts            = array();

        // Creating the Main Property Iterator Instance
        $obj = new ArrayObject($this->variables);
        $this->fieldIterator = $obj->getIterator();

        $counter = 0;

        // Iterate through the Property-Fields
        while ($this->fieldIterator->valid())
        {
            $var = $this->fieldIterator->current();

            // If a repeateable Field has no name, skip it
            if (empty($var->name)) {
                $this->fieldIterator->next();
            }

            /*
             * Dynamically add the '--' Prefix for CSS-Variables.
             *
             * If the Prefix was already set manually in the Backend,
             * it will be removed and redeclared
             */
            $name = '--' . trim(str_replace('--', '', $var->name));

            /*
             * If default Values are set, use default values.
             * If custom Values are set, use them instead.
             */
            switch ($mode)
            {

                default:
                case 'light':
                    $value = ($var->alt_value == 'false') ? $var->lm_value : $var->lm_alt_value;
                    break;

                case 'dark':
                    $value = ($var->alt_value == 'false') ? $var->dm_value : $var->dm_alt_value;
                    break;
            }

            // Clean and restart the Output Buffer
            static::resetOutputBuffer();

            // Create a Code Snippet and save it in the Output Buffer
            ?>
            document.documentElement.style.setProperty('<?= $name ?>', '<?= $value ?>');
            <?php

            $scripts[] = trim(ob_get_clean());

            // Advance the Iterator Pointer
            $this->fieldIterator->next();
            ++$counter;
        }
        return $scripts;
    }

    /**
     * Create the finished Javascript, that is
     * then included in the HTML-Head
     *
     * @return void
     * @since 1.0
     */
    private function createOutputScript(): void
    {

        // Join all created Javascript Code-Snippets
        $lightmode  = implode(' ', $this->lightmode_scripts);
        $darkmode   = implode(' ', $this->darkmode_scripts);

        if($this->loginconsole)
        {
            $this->getStyleCount();
        }

        // Get the Default Style for new Site Visitors
        $default    = $this->params->get('default');

        // Clean and restart the Output Buffer
        static::resetOutputBuffer();

        ?>
        window.addEventListener("DOMContentLoaded", (event) => {
        <?php if (isset($this->inheritedStyle)) : ?>
        <?php if ($this->inheritedStyle == 'light') : ?>
        <?php if ($this->loginconsole) : ?>
        console.log("ElisDarkmode: defaulted to lightmode");
        <?php endif ?>
        <?= $lightmode ?>
        <?php elseif ($this->inheritedStyle == 'dark') : ?>
        <?php if ($this->loginconsole) : ?>
        console.log("ElisDarkmode: defaulted to darkmode");
        <?php endif ?>
        <?= $darkmode ?>
        <?php endif; ?>
        <?php else : ?>
        <?php if ($default == 'light') : ?>
        <?php if ($this->loginconsole) : ?>
        console.log("ElisDarkmode: defaulted to lightmode");
        <?php endif ?>
        <?= $lightmode ?>
        <?php elseif ($default == 'dark') : ?>
        <?php if ($this->loginconsole) : ?>
        console.log("ElisDarkmode: defaulted to darkmode");
        <?php endif ?>
        <?= $darkmode ?>
        <?php endif; ?>
        <?php endif; ?>
        });
        function DMSwitch(prop) {
        if(prop == 'auto') {
        prop = getCookie('edmstyle');
        if(prop == 'light') {
        <?php if($this->animation) : ?>
        document.documentElement.style.setProperty('animation-name', '<?=$this->animation->animation_name; ?>');
        setTimeout(function(){
        document.documentElement.style.removeProperty('animation-name', '<?=$this->animation->animation_name; ?>');
        }, <?=$this->animation->animation_duration ?>);
        <?php endif; ?>
        prop = 'dark';
        } else {
        <?php if($this->animation) : ?>
        document.documentElement.style.setProperty('animation-name', '<?=$this->animation->animation_name; ?>');
        setTimeout(function(){
        document.documentElement.style.removeProperty('animation-name', '<?=$this->animation->animation_name; ?>');
        }, <?=$this->animation->animation_duration ?>);
        <?php endif; ?>
        prop = 'light';
        }}
        if(prop == 'light') {
        <?= static::setJSCookie('light') ?>
        <?= $lightmode ?>
        <?php if ($this->loginconsole) : ?>
        console.log("ElisDarkmode: Style set to lightmode");
        <?php endif ?>
        } else if(prop == 'dark') {
        <?php if ($this->loginconsole) : ?>
        console.log("ElisDarkmode: Style set to darkmode");
        <?php endif; ?>
        <?= static::setJSCookie('dark'); ?>
        <?= $darkmode; ?>
        }};
        function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        }
        <?php
        $this->outputScripts[] = trim(ob_get_clean());
    }

	/**
	 * Generates a Console Response detailing the amount
	 * of loaded scripts
	 *
     * @return void
	 * @since version 1.0
	 */
    private function getStyleCount(): void
    {
	    $lmcount = count($this->lightmode_scripts);
	    $dmcount = count($this->darkmode_scripts);

	    static::resetOutputBuffer();
	    ?>
        console.log('ElisDarkmode: Successfully loaded <?= ($lmcount + $dmcount) ?>
        Styles; [<?= $lmcount ?> Lightmode Styles, <?= $dmcount ?> Darkmode Styles]');
	    <?php
	    $this->outputScripts[] = trim(ob_get_clean());
    }

    /**
     * Create the Switcher-Button
     *
     * @return void
     * @since 1.0
     */
    private function createButton(): void
    {

        // Clean and restart the Output Buffer
        static::resetOutputBuffer();

        // Get the parent Element, that the Button should be rendered in
        $append         = trim($this->params->get('appendButton'));
        $append         = str_replace('.', '', $append);

        // Get Button Propertys declared in the Backend
        $button         = trim($this->params->get('buttonName'));
        $buttonclass    = trim($this->params->get('buttonclass'));

        // Dynamically render the Button using Javascript
        ?>
        <?php if(!empty($append)) : ?>
        window.addEventListener("DOMContentLoaded", (event) => {
        var btn = document.createElement("BUTTON");
        var cookie = getCookie('edmstyle');
        btn.innerHTML = '<?= $button ?>';
        btn.id ='elidarkmode';
        <?php if (!empty($buttonclass)) : ?>
        btn.className = 'elidarkmode-switcher <?= $buttonclass ?>';
        <?php endif; ?>
        var ent = document.getElementsByClassName("<?= $append; ?>")[0];
        btn.onclick = function() {DMSwitch('auto');};
        ent.appendChild(btn);
        });
        <?php endif; ?>
        <?php
        $this->outputScripts[] = ob_get_clean();
    }

    /**
     * Creates Javascript-Cookies
     *
     * @param string $style     The User-selected Style
     * @return string           The Code-Snippet
     * @since                   1.0
     */
    private static function setJSCookie(string $style): string
    {
        return "setCookie('edmstyle', '$style', 100);";
    }

    /**
     * Cleans and restarts the Output Buffer
     *
     * @return void
     * @since 1.0
     */
    private static function resetOutputBuffer(): void
    {
        ob_clean();
        ob_start();
    }
}