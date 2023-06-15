
/**
 * This file is part of the Phalcon Framework.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

namespace Phalcon\Storage\Adapter;

use Phalcon\Helper\Arr;
use Phalcon\Storage\Exception;
use Phalcon\Storage\SerializerFactory;
use Phalcon\Storage\Serializer\SerializerInterface;
use Phalcon\Storage\Adapter\AbstractAdapter;
use Phalcon\Storage\Adapter\Memory;
use Phalcon\Storage\Adapter\WeakCache;

/**
* FirstLevelCache Adapter
*/
class FirstLevelCache extends AbstractAdapter
{

    protected dirty_cache;

    protected weakcache;

    /**
    * Buffer for working with recent models
    * @var array
    */
    protected buffer = [];

    /**
    * @var int
    */
    protected buffer_size;

    /**
    * @var int
    */
    protected buffer_dump_size;

    /**
    * @var array
    */
    protected options = [];

    /**
    * Constructor
    *
    * @param array options = [
    *     'prefix' => ''
    *     'buffer_size' => 100
    *     'buffer_dump_size' => 20
    *
    * ]
    */
    public function __construct(
        <SerializerFactory> factory,
        array options = []
    ) {
        let options["defaultSerializer"] = this->getArrVal(options, "defaultSerializer", "none");
        let options["serializer"] = null;
        let this->prefix = "ph-flc-";
        let this->options = options;
        // parent::__construct(factory, options);
        // this->initSerializer();
        let this->weakcache = new WeakCache(factory);
        let this->dirty_cache = new Memory(factory);
        let this->buffer_dump_size = this->getArrVal(options, "buffer_dump_size", 100);
        let this->buffer_size = this->getArrVal(options, "buffer_size", 500);
    }

    public function setBufferSize(int size)
    {
        let this->buffer_size = size;
    }

    public function setBufferDumpSize(int size)
    {
        let this->buffer_dump_size = size;
    }

    /**
     * Flushes/clears all caches
     */
    public function clear() -> bool
    {
        this->dirty_cache->clear();
        this->weakcache->clear();
        let this->buffer = [];
        return true;
    }

    public function decrement(string! key, int value = 1) -> int | bool
    {
        return false;
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
        if array_key_exists(prefixedKey, $this->buffer){
            unset(this->buffer[prefixedKey]);
        }
        this->dirty_cache->delete(key);
        let exists = this->weakcache->delete(key);
        return exists;
    }

    public function getKeys(string prefix = "") -> array
    {
        return array_keys(this->buffer);
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
        var value, prefixedKey;
        let prefixedKey = this->getPrefixedKey(key);
        if false === this->has(key) {
            return defaultValue;
        }
        let value = this->weakcache->get(key);
        if null !== value {
            if array_key_exists(prefixedKey, this->buffer) {
                unset(this->buffer[prefixedKey]);
            }
            let this->buffer[prefixedKey] = value;
        } else {
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
        return this->weakcache->has(key) || this->dirty_cache->has(key) || array_key_exists(prefixedKey, this->buffer);
    }

    /**
    * Stores data in the adapter ttl us not used
    *
    * @param string                    key
    * @param mixed                    value
    * @param \DateInterval|int|null   ttl
    *
    * @return bool
    * @throws \Exception
    */
    public function set(string! key, var value, var ttl = null) -> bool
    {
        if true === empty(key) {
            return false;
        }
        int buffer_size, i;
        var prefixedKey;
        let prefixedKey = this->getPrefixedKey(key);
        this->weakcache->set(key, value);
        if this->buffer_size > 0 && false === array_key_exists(prefixedKey, this->buffer) {
            let this->buffer[prefixedKey] = value;
            let buffer_size = count(this->buffer);
            if this->buffer_dump_size > 0 && buffer_size > this->buffer_size {
                for i in range(1, this->buffer_dump_size) {
                    array_shift(this->buffer);
                }
            }
        }
        return true;
    }

    public function setDirty(key, value) -> bool
    {
        if null === key {
            return false;
        }
        var prefixedKey;
        let prefixedKey = this->getPrefixedKey(key);
        this->dirty_cache->set(prefixedKey, value);
        return this->set(key, value);
    }

    /**
     * Stores data in the adapter forever. The key needs to manually deleted
     * from the adapter.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return bool
     */
    public function setForever(string key, value) -> bool
    {
        return true;
    }

    public function getWeakCache() -> <WeakCache>
    {
        return this->weakcache;
    }
}
