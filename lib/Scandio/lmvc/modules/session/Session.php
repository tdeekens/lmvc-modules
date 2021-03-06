<?php

namespace Scandio\lmvc\modules\session;

/**
 * Class Session
 * @package Scandio\lmvc\modules\session
 *
 * Class abstraction from session handling so that nobody needs to modify the global $_SESSION
 * varibale. Offers a few simple helper methods to interact with the the user's session.
 */
class Session
{
    protected static
        $started = false;


    /**
     * Starts the session if it has not been started yet.
     *
     * @return bool indicating result of session start.
     */
    public static function start()
    {
        if (static::$started) { return true; }

        static::$started = session_start();

        return static::$started;
    }

    /**
     * Stops / destroys the session.
     *
     * @return bool result of closing session.
     */
    public static function stop($unset = true)
    {
        if (static::$started) { static::$started = session_destroy(); }

        if ($unset) { session_unset(); }

        return static::$started;
    }

    /**
     * Sets a value $value at $attr via dot-notation.
     *
     * @param string $attr in dot-notation to the session's value to be set
     * @param mixed $value value to be set at $attr
     * @param bool $serialize boolean indicating if value should be serialized
     */
    public static function set($attr, $value, $serialize = false)
    {
        $value = ( $serialize === false ) ? $value : serialize($value);

        static::setByDotNotation($attr, $value);

        return $value;
    }

    /**
     * Gets a value at $attr via dot-notation.
     *
     * @param string $attr in dot-notation to the session's value to be set
     * @param mixed $default value that will be returned if the stored value is null
     * @param bool $serialized boolean indicating if value was serialized
     *
     * @return mixed the value behind $attr or the $default value if nothing was set at $attr
     */
    public static function get($attr, $default = null, $serialized = false)
    {
        $ordinary = static::resolveByDotNotation($attr);

        if ($ordinary === null) {
            return $default;
        } else {
            return $serialized === false ? $ordinary : unserialize($ordinary);
        }
    }

    /**
     * Backups a value at $attr via dot-notation:
     * - if an empty value is passed, the backup is returned
     * - otherwise the value is stored in the session and returned
     * - if an empty value is passed and the backup is null, the default will be returned
     *
     * @param string $attr in dot-notation to the session's value to backup
     * @param mixed $value value to backup
     * @param mixed $default value that will be returned as default
     * @param  bool $serialized boolean indication if value is stored serialized
     * @return mixed determined value
     */
    public static function backup($attr, $value, $default = null, $serialized = false)
    {
        return (empty($value) ? static::get($attr, $default, $serialized) : static::set($attr, $value, $serialized));
    }

    /**
     * Backups a value at $attr via dot-notation:
     * - if null or an unset value is passed, the backup is returned
     * - otherwise the value is stored in the session and returned
     * - if an unset value is passed and the backup is null, the default will be returned
     *
     * Needed for backing-up Numbers, as '0' is recognized as 'empty' (in Session::backup)
     *
     * @param string $attr in dot-notation to the session's value to backup
     * @param mixed $value value to backup
     * @param mixed $default value that will be returned as default
     * @param  bool $serialized boolean indication if value is stored serialized
     * @return mixed determined value
     */
    public static function backupNumber($attr, $value, $default = null, $serialized = false)
    {
        return (!isset($value) ? static::get($attr, $default, $serialized) : static::set($attr, $value, $serialized));
    }

    /**
     * Recursively replaces all values in session by key and value.
     *
     * @param array $array nested array containing values to be set in session.
     */
    public static function replace($array)
    {
        $_SESSION = array_replace_recursive($_SESSION, $array);
    }

    /**
     * Recursively merges all values in session by key and value.
     *
     * @param array $array nested array containing values to merged into session.
     */
    public static function merge($array)
    {
        $_SESSION = array_merge_recursive($_SESSION, $array);
    }

    /**
     * Checks if a value is set in the session and returns a bool indicator not the actual value.
     *
     * @param $key requested to be possibly set in session.
     */
    public static function has($key)
    {
        return ( static::resolveByDotNotation($key) !== null );
    }

    /**
     * Flushes the whole session data.
     */
    public static function flush()
    {
        static::setByDotNotation(null, []);
    }

    /**
     * Regenrates the session and its id.
     *
     * @param bool $flush indicating if the session should also be flushed (true by default)
     * @param int|string $lifetime of the session in seconds (null by default)
     */
    public static function regenerate($flush = true, $lifetime = null)
    {
        if ($lifetime !== null) {
            ini_set('session.cookie_lifetime', $lifetime);
        }

        $response = session_regenerate_id($flush);

        session_write_close();
        $backup = $flush ? [] : $_SESSION;

        static::$started = session_start();

        $_SESSION = $backup;

        return $response;
    }

    /**
     * A little helper resolving dot-notation at an array.
     *
     * @param $dot notation returning the value at the last pointer of the dot-notation.
     * @param array $array to be accessed.
     */
    private static function resolveByDotNotation($dot, $scalar = false)
    {
        if (!static::$started) { return null; }

        # Explode the $def string to array by "."
        $exploded = explode(".", $dot);
        # Initial pointer to array is the private array variable
        $arrPointer = $_SESSION;

        # Each subsequent dot-value
        foreach ($exploded as $explode) {
            # The value if set, so goto new array pointer
            if ( isset($arrPointer[$explode]) ) {
                $arrPointer = $arrPointer[$explode];
                # Not found the value by dot-notation at given loop run so return null
            } else {
                return null;
            }

            # Finished iterating the array and the value array pointer is pointing to is not another array (except you want a non scalar value)...
            if ( end($exploded) == $explode && ($scalar === false || !is_array($arrPointer)) ) {
                return $arrPointer;
            }
        }
    }

    /**
     * Sets a value in the $_SESSION array by dot notation.
     *
     * @param $dot notation for value to be set
     * @param $value value to be set
     */
    private static function setByDotNotation($dot, $value)
    {
        if ($dot == null) { $_SESSION = $value; }

        $exploded = explode('.', $dot);
        $valuePointer = &$_SESSION;

        # Loop until end of explodes and reset $_SESSION pointer to $valuePointer by reference
        $length = count($exploded);
        for($i = 0; $i < $length; $i++){
            $key = $exploded[$i];

            $valuePointer = &$valuePointer[$key];
        }

        $valuePointer = $value;
    }
}