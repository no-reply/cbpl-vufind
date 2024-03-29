<?php
/**
 * VuFind Theme Initializer
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Theme;
use VuFind\Mobile,
    VuFind\Mvc\View\Http\InjectTemplateListener,
    Zend\Config\Config,
    Zend\Config\Reader\Ini as IniReader,
    Zend\Mvc\MvcEvent,
    Zend\Stdlib\RequestInterface as Request;

/**
 * VuFind Theme Initializer
 *
 * @category VuFind2
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Initializer
{
    protected $config;
    protected $event;
    protected $resourceContainer;
    protected $serviceManager;
    protected $session;

    /**
     * Constructor
     *
     * @param Config   $config Configuration object
     * @param MvcEvent $event  Zend MVC Event object
     * @param string   $tools  Name of Tools class
     */
    public function __construct(Config $config, MvcEvent $event,
        string $tools = null
    ) {
        // If no tools class name was passed in, use a default:
        if (is_null($tools)) {
            $tools = 'VuFind\Theme\Tools';
        }

        $this->config = $config;
        $this->event = $event;
        $this->baseDir = call_user_func(array($tools, 'getBaseDir'));

        // Grab the service manager for convenience:
        $this->serviceManager = $this->event->getApplication()->getServiceManager();

        // Grab the resource manager for tracking CSS, JS, etc.:
        $this->resourceContainer
            = call_user_func(array($tools, 'getResourceContainer'));

        // Set up a session namespace for storing theme settings:
        $this->session = call_user_func(array($tools, 'getPersistenceContainer'));
    }

    /**
     * Adjust template injection to a strategy that works better with our themes.
     * This needs to be called prior to the dispatch event, which is why it is a
     * separate static method rather than part of the init() method below.
     *
     * @param MvcEvent $event Dispatch event object
     *
     * @return void
     */
    public static function configureTemplateInjection(MvcEvent $event)
    {
        // Get access to the shared event manager:
        $sharedEvents
            = $event->getApplication()->getEventManager()->getSharedManager();

        // Detach the default listener:
        $listeners = $sharedEvents->getListeners(
            'Zend\Stdlib\DispatchableInterface', MvcEvent::EVENT_DISPATCH
        );
        foreach ($listeners as $listener) {
            $metadata = $listener->getMetadata();
            $callback = $listener->getCallback();
            if (is_a($callback[0], 'Zend\Mvc\View\Http\InjectTemplateListener')) {
                $priority = $metadata['priority'];
                $sharedEvents->detach(
                    'Zend\Stdlib\DispatchableInterface', $listener
                );
                break;
            }
        }

        // If we didn't successfully detach a listener above, priority will not be
        // set.  This is an unexpected situation, so we should throw an exception.
        if (!isset($priority)) {
            throw new \Exception('Unable to detach InjectTemplateListener');
        }

        // Attach our own listener in place of the one we removed:
        $injectTemplateListener  = new InjectTemplateListener();
        $sharedEvents->attach(
            'Zend\Stdlib\DispatchableInterface', MvcEvent::EVENT_DISPATCH,
            array($injectTemplateListener, 'injectTemplate'), $priority
        );
    }

    /**
     * Initialize the theme.  This needs to be triggered as part of the dispatch
     * event.
     *
     * @throws \Exception
     * @return void
     */
    public function init()
    {
        // Determine the current theme:
        $currentTheme = $this->pickTheme($this->event->getRequest());

        // Determine theme options:
        $this->sendThemeOptionsToView();

        // Make sure theme details are available in the session:
        $error = $this->loadThemeDetails($currentTheme);

        // Using the settings we initialized above, actually configure the themes; we
        // need to do this even if there is an error, since we need a theme in order
        // to display an error message!
        $this->setUpThemes(array_reverse($this->session->allThemeInfo));

        // If we encountered an error loading theme settings, fail now.
        if (!empty($error)) {
            throw new \Exception($error);
        }
    }

    /**
     * Support method for init() -- load all of the theme details from either the
     * session or disk (as needed).
     *
     * @param string $currentTheme The name of the user-selected theme.
     *
     * @return string Error message on problem, empty string on success.
     */
    protected function loadThemeDetails($currentTheme)
    {
        // Fill in the session if it is not already populated:
        if (!isset($this->session->currentTheme)
            || $this->session->currentTheme !== $currentTheme
        ) {
            // If the configured theme setting is illegal, switch it to "blueprint"
            // and set a flag so we can throw an Exception once everything is set
            // up:
            if (!file_exists($this->baseDir . "/$currentTheme/theme.ini")) {
                $themeLoadError = 'Cannot load theme: ' . $currentTheme;
                $currentTheme = 'blueprint';
            }

            // Remember the top-level theme setting:
            $this->session->currentTheme = $currentTheme;

            // Build an array of theme information by inheriting up the theme tree:
            $allThemeInfo = array();
            do {
                $iniReader = new IniReader();
                $currentThemeInfo = new Config(
                    $iniReader->fromFile(
                        $this->baseDir . "/$currentTheme/theme.ini"
                    )
                );

                $allThemeInfo[$currentTheme] = $currentThemeInfo;

                $currentTheme = $currentThemeInfo->extends;
            } while ($currentTheme);

            $this->session->allThemeInfo = $allThemeInfo;
        }

        // Report success or failure:
        return isset($themeLoadError) ? $themeLoadError : '';
    }

    /**
     * Support method for init() -- figure out which theme option is active.
     *
     * @param Request $request Request object (for obtaining user parameters).
     *
     * @return string
     */
    protected function pickTheme(Request $request)
    {
        // Load standard configuration options:
        $standardTheme = $this->config->Site->theme;
        $mobileTheme = isset($this->config->Site->mobile_theme)
            ? $this->config->Site->mobile_theme : false;

        // Find out if the user has a saved preference in the POST, URL or cookies:
        $selectedUI = $request->getPost()->get(
            'ui', $request->getQuery()->get(
                'ui', isset($request->getCookie()->ui)
                ? $request->getCookie()->ui : null
            )
        );
        if (empty($selectedUI)) {
            $selectedUI = ($mobileTheme && Mobile::detect())
                ? 'mobile' : 'standard';
        }

        // Save the current setting to a cookie so it persists:
        $_COOKIE['ui'] = $selectedUI;
        setcookie('ui', $selectedUI, null, '/');

        // Do we have a valid mobile selection?
        if ($mobileTheme && $selectedUI == 'mobile') {
            return $mobileTheme;
        }

        // Do we have a non-standard selection?
        if ($selectedUI != 'standard'
            && isset($this->config->Site->alternate_themes)
        ) {
            // Check the alternate theme settings for a match:
            $parts = explode(',', $this->config->Site->alternate_themes);
            foreach ($parts as $part) {
                $subparts = explode(':', $part);
                if ((trim($subparts[0]) == trim($selectedUI))
                    && isset($subparts[1]) && !empty($subparts[1])
                ) {
                    return $subparts[1];
                }
            }
        }

        // If we got this far, we either have a standard option or the user chose
        // an invalid non-standard option; either way, we need to default to the
        // standard theme:
        return $standardTheme;
    }

    /**
     * Make the theme options available to the view.
     *
     * @return void
     */
    protected function sendThemeOptionsToView()
    {
        // Get access to the view model:
        $viewModel = $this->serviceManager->get('viewmanager')->getViewModel();

        // Send down the view options:
        $viewModel->setVariable('themeOptions', $this->getThemeOptions());
    }

    /**
     * Return an array of information about user-selectable themes.  Each entry in
     * the array is an associative array with 'name', 'desc' and 'selected' keys.
     *
     * @return array
     */
    protected function getThemeOptions()
    {
        $options = array();
        if (isset($this->config->Site->selectable_themes)) {
            $parts = explode(',', $this->config->Site->selectable_themes);
            foreach ($parts as $part) {
                $subparts = explode(':', $part);
                $name = trim($subparts[0]);
                $desc = isset($subparts[1]) ? trim($subparts[1]) : '';
                $desc = empty($desc) ? $name : $desc;
                if (!empty($name)) {
                    $options[] = array(
                        'name' => $name, 'desc' => $desc,
                        'selected' => ($_COOKIE['ui'] == $name)
                    );
                }
            }
        }
        return $options;
    }

    /**
     * Support method for setUpThemes -- register view helpers.
     *
     * @param string $theme     Name of theme
     * @param string $namespace Namespace for view helpers
     * @param array  $helpers   Helpers to register
     *
     * @return void
     */
    protected function setUpThemeViewHelpers($theme, $namespace, $helpers)
    {
        // Ignore null helper array:
        if (is_null($helpers)) {
            return;
        }

        // Grab the helper loader from the view manager:
        $loader = $this->serviceManager->get('viewmanager')->getHelperManager();

        // Register all the helpers:
        foreach ($helpers as $helper) {
            $loader->setInvokableClass(strtolower($helper), "$namespace\\$helper");
        }
    }

    /**
     * Support method for init() -- set up theme once current settings are known.
     *
     * @param array $themes Theme configuration information.
     *
     * @return void
     */
    protected function setUpThemes($themes)
    {
        $templatePathStack = array();

        // Apply the loaded theme settings in reverse for proper inheritance:
        foreach ($themes as $key=>$currentThemeInfo) {
            if ($helperNS = $currentThemeInfo->get('helper_namespace')) {
                $this->setUpThemeViewHelpers(
                    $key, $helperNS, $currentThemeInfo->get('helpers_to_register')
                );
            }

            // Add template path:
            $templatePathStack[] = $this->baseDir . "/$key/templates";

            // Add CSS and JS dependencies:
            if ($css = $currentThemeInfo->get('css')) {
                $this->resourceContainer->addCss($css);
            }
            if ($js = $currentThemeInfo->get('js')) {
                $this->resourceContainer->addJs($js);
            }

            // Select encoding:
            if ($encoding = $currentThemeInfo->get('encoding')) {
                $this->resourceContainer->setEncoding($encoding);
            }

            // Select favicon:
            if ($favicon = $currentThemeInfo->get('favicon')) {
                $this->resourceContainer->setFavicon($favicon);
            }
        }

        // Inject the path stack generated above into the view resolver:
        $resolver = $this->serviceManager->get('viewmanager')->getResolver();
        if (!is_a($resolver, 'Zend\View\Resolver\AggregateResolver')) {
            throw new \Exception('Unexpected resolver: ' . get_class($resolver));
        }
        foreach ($resolver as $current) {
            if (is_a($current, 'Zend\View\Resolver\TemplatePathStack')) {
                $current->setPaths($templatePathStack);
            }
        }
    }
}