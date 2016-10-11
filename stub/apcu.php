<?php

/**
 * Caches a variable in the data store, only if it's not already stored
 *
 * @link http://php.net/manual/en/function.apcu-add.php
 * @param string $key Store the variable using this name. Keys are cache-unique,
 *                    so attempting to use apc_add() to store data with a key that already exists will not
 *                    overwrite the existing data, and will instead return FALSE. (This is the only difference
 *                    between apc_add() and apc_store().)
 * @param mixed  $var The variable to store
 * @param int    $ttl Time To Live; store var in the cache for ttl seconds. After the ttl has passed,
 *                    the stored variable will be expunged from the cache (on the next request). If no ttl is supplied
 *                    (or if the ttl is 0), the value will persist until it is removed from the cache manually,
 *                    or otherwise fails to exist in the cache (clear, restart, etc.).
 * @return bool
 */
function apcu_add($key, $var, $ttl = 0) {
}

/**
 * Retrieves cached information and meta-data from APC's data store
 *
 * @link http://php.net/manual/en/function.apc-cache-info.php
 * @param string $type    If cache_type is "user", information about the user cache will be returned.
 *                        If cache_type is "filehits", information about which files have been served from the bytecode
 *                        cache for the current request will be returned. This feature must be enabled at compile time
 *                        using --enable-filehits. If an invalid or no cache_type is specified, information about the
 *                        system cache (cached files) will be returned.
 * @param bool   $limited If limited is TRUE, the return value will exclude the individual list
 *                        of cache entries. This is useful when trying to optimize calls for statistics gathering.
 * @return array|bool Array of cached data (and meta-data) or FALSE on failure.
 */
function apcu_cache_info($type = '', $limited = false) {
}

/**
 * @link http://php.net/manual/en/function.apc-cas.php
 * @param string $key
 * @param int    $old
 * @param int    $new
 * @return bool
 */
function apcu_cas($key, $old, $new) {
}

/**
 * Clears the APC cache
 *
 * @link http://php.net/manual/en/function.apc-clear-cache.php
 * @param string $cache_type If cache_type is "user", the user cache will be cleared;
 *                           otherwise, the system cache (cached files) will be cleared.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function apcu_clear_cache($cache_type = '') {
}

/**
 * Decrease a stored number
 *
 * @link http://php.net/manual/en/function.apc-dec.php
 * @param string $key     The key of the value being decreased.
 * @param int    $step    The step, or value to decrease.
 * @param bool   $success Optionally pass the success or fail boolean value to this referenced variable.
 * @return int|bool Returns the current value of key's value on success, or FALSE on failure.
 */
function apcu_dec($key, $step = 1, &$success = null) {
}

/**
 * Removes a stored variable from the cache
 *
 * @link http://php.net/manual/en/function.apc-delete.php
 * @param string|string[]|APCIterator $key The key used to store the value (with apc_store()).
 * @return bool|string[] Returns TRUE on success or FALSE on failure. For array of keys returns list of failed keys.
 */
function apcu_delete($key) {
}

/**
 * Checks if APC key exists
 *
 * @link http://php.net/manual/en/function.apc-exists.php
 * @param bool|string[] $keys A string, or an array of strings, that contain keys.
 * @return bool|string[] Returns TRUE if the key exists, otherwise FALSE
 *                            Or if an array was passed to keys, then an array is returned that
 *                            contains all existing keys, or an empty array if none exist.
 */
function apcu_exists($keys) {
}

/**
 * Fetch a stored variable from the cache
 *
 * @link http://php.net/manual/en/function.apc-fetch.php
 * @param string|string[] $key     The key used to store the value (with apc_store()).
 *                                 If an array is passed then each element is fetched and returned.
 * @param bool            $success Set to TRUE in success and FALSE in failure.
 * @return mixed The stored variable or array of variables on success; FALSE on failure.
 */
function apcu_fetch($key, &$success = null) {
}

/**
 * Increase a stored number
 *
 * @link http://php.net/manual/en/function.apc-inc.php
 * @param string $key     The key of the value being increased.
 * @param int    $step    The step, or value to increase.
 * @param bool   $success Optionally pass the success or fail boolean value to this referenced variable.
 * @return int|bool Returns the current value of key's value on success, or FALSE on failure.
 */
function apcu_inc($key, $step = 1, &$success = null) {
}

/**
 * Retrieves APC's Shared Memory Allocation information
 *
 * @link http://php.net/manual/en/function.apc-sma-info.php
 * @param bool $limited When set to FALSE (default) apc_sma_info() will
 *                      return a detailed information about each segment.
 * @return array|bool Array of Shared Memory Allocation data; FALSE on failure.
 */
function apcu_sma_info($limited = false) {
}

/**
 * Cache a variable in the data store
 *
 * @link http://php.net/manual/en/function.apc-store.php
 * @param string|array $key String: Store the variable using this name. Keys are cache-unique,
 *                          so storing a second value with the same key will overwrite the original value.
 *                          Array: Names in key, variables in value.
 * @param mixed        $var [optional] The variable to store
 * @param int          $ttl [optional]  Time To Live; store var in the cache for ttl seconds. After the ttl has passed,
 *                          the stored variable will be expunged from the cache (on the next request). If no ttl is supplied
 *                          (or if the ttl is 0), the value will persist until it is removed from the cache manually,
 *                          or otherwise fails to exist in the cache (clear, restart, etc.).
 * @return bool|array Returns TRUE on success or FALSE on failure | array with error keys.
 */
function apcu_store($key, $var, $ttl = 0) {
}
