<?php
namespace RedBrick;

/**
 * @version 1.1.2
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 *
 * A base class upon which can be built the plugin's main class.
 */
abstract class BasePlugin {
    /**
     * Singleton instance.
     *
     * @since 1.0
     * @var object
     */
    protected static $plugin;

    /**
     * Shared instance of the {@see DB} class.
     *
     * @since 1.0
     * @var object
     */
    protected $db;

    /**
     * @since 1.0
     * @var array
     */
    protected $globalObjects = array();

    /**
     * @since 1.0
     * @var bool
     */
    protected $isUninstalling;

    /**
     * Path of the main file (plugin-name.php).
     *
     * @since 1.0
     * @var string
     */
    protected $mainFilePath;

    /**
     * @since 1.0
     * @var string
     */
    protected $basename;

    /**
     * Name of the plugin's directory.
     *
     * @since 1.0
     * @var string
     */
    protected $dirName;
    
    /**
     * @since 1.0
     * @var string
     */
    protected $dirPath;

    /**
     * URL of the plugin's directory.
     *
     * @since 1.0
     * @var string
     */
    protected $dirURL;

    /**
     * @see registerAdminNoticeActionWithMessage()
     * @since 1.1
     *
     * @var string
     */
    protected $compatibilityErrorMessages = array();

    /**
     * @since 1.0
     * @var string
     */
    protected $minSuffix;

   /**
     * @since 1.0
     *
     * @param string $plugin_dir_path
     * @return bool
     */
    public static function launch( $plugin_dir_path ) {
        $class_name = static::class;
        $dir_name   = basename( $plugin_dir_path );
        
        if ( self::$plugin instanceof $class_name ) {
            return false;
        }

        self::$plugin                 = new static();
        self::$plugin->dirPath        = $plugin_dir_path . '/';
        self::$plugin->dirName        = $dir_name;
        self::$plugin->mainFilePath   = $plugin_dir_path . '/' . $dir_name . '.php';
        self::$plugin->isUninstalling = defined( 'WP_UNINSTALL_PLUGIN' );

        if ( is_admin() ) {
            self::$plugin->basename = plugin_basename( self::$plugin->mainFilePath );

            register_activation_hook( self::$plugin->mainFilePath, array( self::$plugin, 'wpDidActivatePlugin' ) );
        }

        $plugin_info = get_file_data( self::$plugin->mainFilePath, self::$plugin->getInfoToRetrieve() );

        if ( 
            !$plugin_info['version'] || 
            ( preg_match( '/[^0-9\.]/', $plugin_info['version'] ) === 1 )
        ) {
            self::$plugin->registerAdminNoticeActionWithMessage( "Unable to retrieve plugin's version." );

            return false;
        }

        foreach ( $plugin_info as $key => $value ) {
            self::$plugin->{$key} = $value;
        }

        return self::$plugin->finishLaunching();
    }

    /**
     * Returns a reference to the singleton object.
     *
     * @since 1.0
     * @return object|bool
     */
    public static function invoke() {
        return self::$plugin;
    }

    /**
     * @since 1.0
     */
    private function __construct() {}
    
    /**
     * @since 1.0
     * @return int Error code.
     */
    public function __clone() {
        return -1;
    }

    /**
     * @since 1.0
     * @return int Error code.
     */
    public function __wakeup() {
        return -1;
    }

    /**
     * @since 1.0
     * @return bool
     */
    abstract protected function finishLaunching();

    /**
     * @since 1.0
     * @return bool
     */
    abstract public function pluginDidfinishLaunching();

    /**
     * @since 1.0
     */
    public function wpDidActivatePlugin() {}

    /**
     * @since 1.0
     *
     * @param string $relative_path
     * @return object
     */
    public function load( $relative_path ) {
        include( $this->dirPath . $relative_path );
    }

    /**
     * @since 1.0
     * @param string $db_key_prefix
     */
    protected function initDB( $db_key_prefix = '' ) {
        $this->load( 'library/db.class.php' );
        
        $this->db = new DB( $this->id(), $db_key_prefix );

        if (! $this->isUninstalling ) {
            add_action( 'shutdown', array( $this->db, 'consolidate' ) );
        }
    }

    /**
     * @since 1.0
     * @return object
     */
    public function db() { 
        return $this->db;
    }


    /**
     * @since 1.0
     *
     * @param string $class_name
     * @return object|bool
     */
    public function invokeGlobalObject( $class_name ) { 
        if (! $this->globalObjects ) {
            $this->load( 'data-model/global-objects-resources.php' );
        }

        if (! isset( $this->globalObjects[$class_name]['instance'] ) ) {
            $there_are_resources_for_global_object = isset( $this->globalObjects[$class_name]['resources'] );
            
            if ( $there_are_resources_for_global_object ) {
                foreach ( $this->globalObjects[$class_name]['resources'] as $resource ) {
                    $this->load( $resource );
                }

                $namespaced_class_name = __NAMESPACE__ . '\\' . $class_name;

                $this->globalObjects[$class_name]['instance'] = new $namespaced_class_name( $this );
            }
            else {
                return false;
            }
        }
        
        return $this->globalObjects[$class_name]['instance'];       
    }

    /**
     * @since 1.0
     *
     * @param string $class_name
     * @param object $object
     */
    public function setGlobalObject( $class_name, $object ) { 
        $this->globalObjects[$class_name]['instance'] = $object;
    }

    /**
     * @since 1.0
     *
     * @param string $class_name
     * @param array $resources
     */
    protected function registerGlobalObjectResources( $class_name, $resources ) { 
        $this->globalObjects[$class_name]['resources'] = $resources;
    }

    /**
     * @since 1.0
     * @return bool
     */
    public function isUninstalling() {
        return $this->isUninstalling;
    }

    /**
     * @since 1.1
     * @return array
     */
    protected function getInfoToRetrieve() { 
        return array(
            'name'         => 'Plugin Name',
            'version'      => 'Version',
            'minWPVersion' => 'Requires',
            'pluginURI'    => 'Plugin URI',
            'authorURI'    => 'Author URI'
        );
    }

    /**
     * @since 1.0
     * @return string
     */
    public function id() { 
        return $this->dirName;
    }

    /**
     * @since 1.0
     * @return string
     */
    public function name() { 
        return $this->name;
    }
    
    /**
     * @since 1.0
     * @return string
     */
    public function version() { 
        return $this->version;
    }

    /**
     * @since 1.0
     * @return string
     */
    public function minWPVersion() { 
        return $this->minWPVersion;
    }

    /**
     * @since 1.0
     * @return string
     */
    public function mainFilePath() {
        return $this->mainFilePath;
    }

    /**
     * @since 1.0
     * @return string
     */
    public function basename() {
        return $this->basename;
    }

    /**
     * @since 1.0
     *
     * @param string $relative_URL
     * @return string
     */
    public function pluginURI( $relative_URL = '' ) { 
        return ( $this->pluginURI . $relative_URL );
    }

    /**
     * @since 1.0
     *
     * @param string $relative_URL
     * @return string
     */
    public function authorURI( $relative_URL = '' ) { 
        return ( $this->authorURI . $relative_URL );
    }

    /**
     * @since 1.0
     * @return string
     */
    public function dirPath() {
        return $this->dirPath;
    }

    /**
     * @since 1.0
     * @return string
     */
    public function dirName() {
        return $this->dirName;
    }

    /**
     * @since 1.0
     *
     * @param string $path Optional.
     * @return string
     */
    public function dirURL( $path = '' ) {
        if (! $this->dirURL ) {
            $this->dirURL = plugins_url( $this->dirName . '/' );
        }

        return $this->dirURL . $path;
    }
    
    /**
     * @since 1.0
     * @return bool
     */
    protected function verifyWordPressCompatibility() {
        global $wp_version;
        
        if ( version_compare( $wp_version, $this->minWPVersion, '>=' ) ) {
            return true;
        }

        $this->registerAdminNoticeActionWithMessage(
            'To use ' . $this->name . ' ' . $this->version
          . ' you need at least WordPress ' . $this->minWPVersion
          . '. Please, update your WordPress installation to '
          . '<a href="https://wordpress.org/download/">the latest version available</a>.'
        );

        return false;
    }

    /**
     * @since 1.0
     *
     * @param string $textdomain
     * @param string $languages_relative_path
     * @return bool
     */
    public function loadTextdomain( $textdomain = '', $languages_relative_path = '' ) {
        if (! $textdomain ) {
            $textdomain = $this->id();
        }

        if ( $languages_relative_path ) {
            $languages_relative_path = $this->dirName . '/' . $languages_relative_path;
        }
        else {
            $languages_relative_path = $this->dirName . '/languages/';
        }

        return load_plugin_textdomain( $textdomain, false, $languages_relative_path );
    }
    
    /**
     * @since 1.0
     * @param string $message
     */
    public function registerAdminNoticeActionWithMessage( $message ) {
        if ( !$this->isUninstalling && is_admin() ) {
            if ( empty( $this->compatibilityErrorMessages ) ) {
                add_action( 'admin_notices', array( $this, 'displayAdminNotice' ) );
            }

            $this->compatibilityErrorMessages[] = $message;
        }
    }
    
    /**
     * @since 1.0
     */
    public function displayAdminNotice() {
        echo '<div class="notice notice-error">';

        foreach ( $this->compatibilityErrorMessages as $message ) {
            echo '<p>', $message, '</p>';
        }

        echo '</div>';
            
        // Hides the message "Plugin Activated" 
        // if the error is triggered during activation.
        unset( $_GET['activate'] );
    }

    /**
     * @since 1.0
     * @return string
     */
    public function stylesAndScriptsSuffix() {
        if ( $this->minSuffix === null ) {
            $this->minSuffix = ( ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '-min' );
        }

        return $this->minSuffix;
    }
}
?>