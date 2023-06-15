
/**
 * This file is part of the Phalcon Framework.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

namespace Phalcon\Storage\Adapter;


use DateInterval;
use Exception as BaseException;
use Phalcon\Storage\SerializerFactory;
use Phalcon\Support\Exception as SupportException;
use Phalcon\Storage\Serializer\SerializerInterface;

/**
* WeakCache Adapter
*/
class WeakCache extends AbstractAdapter
{

    /**
     *
     *
     * @var int
     */
    protected fetching = null;

    /**
     * @var array
     */

    protected weakList = [];

    /**
     * @var array
     */
    protected options = [];

    /**
     * Constructor
     *
     * @param array options = [
     *     'defaultSerializer' => 'none',
     *     'lifetime' => 3600,
     *     'serializer' => null,
     *     'prefix' => ''
     * ]
     * @throws SupportException
     */
    public function __construct(<SerializerFactory> factory, array! options = [])
    {
        let options["defaultSerializer"] = this->getArrVal(options, "defaultSerializer", "none"),
            this->prefix                 = "ph-weakcache-",
            this->options                = options;
        // parent::__construct(factory, options);
        // this->initSerializer();
    }

     /**
     * Flushes/clears the cache
     */
    public function clear() -> bool
    {
        let this->weakList = [];
        return true;
    }

    /**
     * Decrements a stored number
     *
     * @param string $key
     * @param int    $value
     *
     * @return bool|int
     */
    public function decrement(string! key, int value = 1) -> int | bool
    {
        return false;
    }

    /**
     * Deletes data from the adapter
     *
     * @param string $key
     *
     * @return bool
     */
    public function delete(string! key) -> bool
    {
        var exists, prefixedKey;
        let prefixedKey = this->getPrefixedKey(key);
        if prefixedKey === this->fetching {
            return false;
        }
        let exists = array_key_exists(prefixedKey, this->weakList);
        unset(this->weakList[prefixedKey]);
        return exists;
    }

    /**
     * Stores data in the adapter
     *
     * @param string $prefix
     *
     * @return array
     */
    public function getKeys(string prefix = "") -> array
    {
        return this->getFilteredKeys(array_keys(this->weakList), prefix);
    }

    /**
     * Increments a stored number
     *
     * @param string $key
     * @param int    $value
     *
     * @return bool|int
     */
    public function increment(string! key, int value = 1) -> int | bool
    {
        return false;
    }

    /**
    * Reads data from the adapter
    *
    * @param string key
    * @param mixed|null   defaultValue
    *
    * @return mixed
    */
    public function get(string! key, var defaultValue = null) -> var
    {
        var value, wr, prefixedKey;
        let prefixedKey = this->getPrefixedKey(key);
        /**
         * while getting a key, garbage collection might be triggered, this will stop unsetting the key, will not stop however the model gets destroid by GC, this is for the destruct that is in the model, not do destroy the key before getting it.
         */
        let this->fetching = prefixedKey;
        if false === array_key_exists(prefixedKey, this->weakList) {
            let this->fetching = null;
            return defaultValue;
        }
        let wr = this->weakList[prefixedKey];
        let value = wr->get();
        let this->fetching = null;
        /**
         * value could be null, object could be destroid while fetching
         */
        if null === value {
            this->delete(key);
        }
        return value;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    protected function doGet(string key)
    {
        return this->get(key);
    }

    /**
    * Always returns null
    *
    * @return null
    */
    public function getAdapter() -> var
    {
        return this->adapter;
    }

    /**
    * Checks if an element exists in the cache
    *
    * @param string key
    *
    * @return bool
    */
    public function has(string! key) -> bool
    {
        var prefixedKey;
        let prefixedKey = this->getPrefixedKey(key);
        return array_key_exists(prefixedKey, this->weakList);
    }

    /**
     * Stores data in the adapter. If the TTL is `null` (default) or not defined
     * then the default TTL will be used, as set in this adapter. If the TTL
     * is `0` or a negative number, a `delete()` will be issued, since this
     * item has expired. If you need to set this key forever, you should use
     * the `setForever()` method.
     *
     * @param string                $key
     * @param mixed                 $value
     * @param DateInterval|int|null $ttl
     *
     * @return bool
     * @throws BaseException
     */
    public function set(string! key, var value, var ttl = null) -> bool
    {
        var prefixedKey;
        let prefixedKey = this->getPrefixedKey(key);
        if false === array_key_exists(prefixedKey, this->weakList) {
            let this->weakList[prefixedKey] = \WeakReference::create(value);
        }
        return true;
    }

    public function setForever(string key, value) -> bool
    {
        return true;
    }
}
