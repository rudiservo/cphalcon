
/**
 * This file is part of the Phalcon Framework.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

namespace Phalcon\Mvc\Model\Resultset;

use Phalcon\Di\Di;
use Phalcon\Di\DiInterface;
use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Exception;
use Phalcon\Mvc\Model\Resultset;
use Phalcon\Mvc\Model\Row;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Storage\Serializer\SerializerInterface;
use Phalcon\Messages\Message;
use Phalcon\Messages\MessageInterface;
use Phalcon\Storage\Adapter\FirstLevelCache;

/**
 * Phalcon\Mvc\Model\Resultset\Simple
 *
 * Simple resultsets only contains a complete objects
 * This class builds every complete object as it is required
 */
class Simple extends Resultset
{
    /**
     * @var array|string
     */
    protected columnMap;

    /**
     * @var ModelInterface|Row
     */
    protected model;

    /**
     * @var string
     */

    protected modelName;

    protected firstLevelCache = null;

    protected metaData;

    protected appended_uuid;

    /**
    * @var array;
    */
    protected appended;

    /**
    * @var array;
    */
    protected removed;

    /**
    * @var array;
    */
    protected errorMessages;

    /**
    * @var int
    */
    protected dirtyState = 1;

    /**
     * @var bool
     */
    protected keepSnapshots = false;

    /**
     * Phalcon\Mvc\Model\Resultset\Simple constructor
     *
     * @param array                             columnMap
     * @param ModelInterface|Row                model
     * @param \Phalcon\Db\ResultInterface|false result
     * @param mixed|null                        cache
     * @param bool keepSnapshots                false
     */
    public function __construct(
        var columnMap,
        var model,
        result,
        var cache = null,
        bool keepSnapshots = false
    )
    {
        let this->model     = model,
            this->columnMap = columnMap;
        /**
         * Set if the returned resultset must keep the record snapshots
         */
        let this->keepSnapshots = keepSnapshots;
        let this->modelName = "Phalcon\\Mvc\\Model";

        let this->appended_uuid = [];
        let this->appended = [];
        let this->removed = [];
        let this->errorMessages = [];
        if this->model instanceof Model {
            if globals_get("orm.late_state_binding") {
               let this->modelName = get_class(model);
            }
            let this->metaData = model->getModelsMetaData();
            let this->firstLevelCache = model->getModelsManager()->getFirstLevelCache();
        }
        parent::__construct(result, cache);
    }

    /**
     * Returns current row in the resultset
     */
    final public function current() -> <ModelInterface> | null
    {
        var row, hydrateMode, columnMap, activeRow, modelName, uuid;

        let activeRow = this->activeRow;

        if activeRow !== null {
            return activeRow;
        }

        /**
         * Current row is set by seek() operations
         */
        let row = this->row;

        /**
         * Valid records are arrays
         */
        if typeof row != "array" {
            let this->activeRow = false;

            return null;
        }

        /**
         * Get current hydration mode
         */
        let hydrateMode = this->hydrateMode;

        /**
         * Get the resultset column map
         */
        let columnMap = this->columnMap;

        /**
         * Hydrate based on the current hydration
         */
        switch hydrateMode {
            case Resultset::HYDRATE_RECORDS:
                /**
                 * Set records as dirty state PERSISTENT by default
                 * Performs the standard hydration based on objects
                 */
                if this->hasFirstLevelCache() {
                    let uuid = this->metaData->getUUID(this->model, row);
                    let activeRow = this->firstLevelCache->get(uuid);
                }
                if null === activeRow {
                    let modelName = this->modelName;
                    let activeRow = {modelName}::cloneResultMap(
                        this->model,
                        row,
                        columnMap,
                        Model::DIRTY_STATE_PERSISTENT,
                        this->keepSnapshots
                    );
                }
                if this->hasFirstLevelCache() {
                    let uuid = this->metaData->getUUID(this->model, row);
                    activeRow->setModelUUID(uuid);
                    this->firstLevelCache->set(uuid, activeRow);
                }
                break;
            default:
                /**
                 * Other kinds of hydrations
                 */
                let activeRow = Model::cloneResultMapHydrate(
                    row,
                    columnMap,
                    hydrateMode
                );

                break;
        }

        let this->activeRow = activeRow;

        return activeRow;
    }


    public function getAppended() -> array
    {
        return array_merge(this->appended, this->appended_uuid);
    }

    public function getRemoved() -> array
    {
        return this->removed;
    }

    public function hasChanged() -> bool
    {
        return false === empty(this->appended) || false === this->appended_uuid || false === this->removed;
    }

    public function getModel() -> <ModelInterface> | null
    {
        return this->model;
    }

    public function getModelName() -> string | null
    {
        return this->modelName;
    }

    public function appendMessage(<MessageInterface> message) -> <Resultset>
    {
        let this->errorMessages[] = message;
        return this;
    }

    public function append(<ModelInterface> model) -> bool
    {
        /**
        * to add a list of appended witha model UUID if it's not a new model
        *
        */
        var model_uuid;
        if model instanceof this->model {
            let model_uuid = model->getModelUUID();
            if null !== model_uuid {
                if false === array_key_exists(model_uuid, this->appended_uuid) {
                    let this->appended_uuid[model_uuid] = model;
                }
            } else {
                if false === array_search(model, this->appended) {
                    let this->appended[] = model;
                }
            }
            return true;
        }
        return false;
    }

    public function remove(<ModelInterface> model) -> bool
    {
        /**
        * add a removed list with the model uuid, if it's new remove only from appended
        */
        var model_uuid;
        var key;
        if  model instanceof this->model {
            let model_uuid = model->getModelUUID();
            if null !== model_uuid {
                if true === array_key_exists(model_uuid, this->appended_uuid) {
                    unset(this->appended_uuid[model_uuid]);
                }
            } else {
                let key = array_search(model, this->appended);
                if false !== key {
                    unset(this->appended[key]);
                }
            }
            return true;
        }
        return false;
    }

    public function persist() -> bool
    {
        array removed, appended_uuid, appended;
        var record;

        if Model::DIRTY_STATE_TRANSIENT !== this->dirtyState {
            return true;
        }
        let removed = this->removed;
        let appended_uuid = this->appended_uuid;
        let appended = this->appended;

        for record in removed {
            if false === record->delete() {
                let this->errorMessages = record->getMessages();
                return false;
            }
        }
        for record in appended {
            if false === record->persist() {
                let this->errorMessages = record->getMessages();
                return false;
            } else {
                // TODO: Add logic for objects still in memory.
            }
        }
        for record in appended_uuid {
            if false === record->persist() {
                let this->errorMessages = record->getMessages();
                return false;
            }
        }
        return true;
    }

    /**
     * Returns a complete resultset as an array, if the resultset has a big
     * number of rows it could consume more memory than currently it does.
     * Export the resultset to an array couldn't be faster with a large number
     * of records
     */
    public function toArray(bool renameColumns = true) -> array
    {
        var result, records, record, renamedKey, key, value, columnMap;
        array renamedRecords, renamed;

        /**
         * If _rows is not present, fetchAll from database
         * and keep them in memory for further operations
         */
        let records = this->rows;

        if typeof records != "array" {
            let result = this->result;

            if this->row !== null {
                // re-execute query if required and fetchAll rows
                result->execute();
            }

            let records = result->fetchAll();

            let this->row = null;
            let this->rows = records; // keep result-set in memory
        }

        /**
         * We need to rename the whole set here, this could be slow
         *
         * Only rename when it is Model
         */
        if renameColumns && !(this->model instanceof Row) {
            /**
             * Get the resultset column map
             */
            let columnMap = this->columnMap;

            if typeof columnMap != "array" {
                return records;
            }

            let renamedRecords = [];

            if typeof records == "array" {
                for record in records {
                    let renamed = [];

                    for key, value in record {
                        /**
                         * Check if the key is part of the column map
                         */
                        if unlikely !fetch renamedKey, columnMap[key] {
                            throw new Exception(
                                "Column '" . key . "' is not part of the column map"
                            );
                        }

                        if typeof renamedKey == "array" {
                            if unlikely !fetch renamedKey, renamedKey[0] {
                                throw new Exception(
                                    "Column '" . key . "' is not part of the column map"
                                );
                            }
                        }

                        let renamed[renamedKey] = value;
                    }

                    /**
                     * Append the renamed records to the main array
                     */
                    let renamedRecords[] = renamed;
                }
            }

            return renamedRecords;
        }

        return records;
    }

    /**
     * Serializing a resultset will dump all related rows into a big array
     */
    public function serialize() -> string
    {
        var container, serializer;
        array data;

        let container = Di::getDefault();
        if container === null {
            throw new Exception(
                "The dependency injector container is not valid"
            );
        }

        let data = [
            "model"         : this->model,
            "cache"         : this->cache,
            "rows"          : this->toArray(false),
            "columnMap"     : this->columnMap,
            "hydrateMode"   : this->hydrateMode,
            "keepSnapshots" : this->keepSnapshots
        ];

        if container->has("serializer") {
            let serializer = <SerializerInterface> container->getShared("serializer");
            serializer->setData(data);

            return serializer->serialize();
        }

        /**
         * Serialize the cache using the serialize function
         */
        return serialize(data);
    }

    /**
     * Unserializing a resultset will allow to only works on the rows present in
     * the saved state
     */
    public function unserialize(var data) -> void
    {
        var resultset, keepSnapshots, container, serializer;

        let container = Di::getDefault();
        if container === null {
            throw new Exception(
                "The dependency injector container is not valid"
            );
        }

        if container->has("serializer") {
            let serializer = <SerializerInterface> container->getShared("serializer");

            serializer->unserialize(data);
            let resultset = serializer->getData();
        } else {
            let resultset = unserialize(data);
        }

        if unlikely typeof resultset !== "array" {
            throw new Exception("Invalid serialization data");
        }

        let this->model       = resultset["model"],
            this->rows        = resultset["rows"],
            this->count       = count(resultset["rows"]),
            this->cache       = resultset["cache"],
            this->columnMap   = resultset["columnMap"],
            this->hydrateMode = resultset["hydrateMode"];

        if fetch keepSnapshots, resultset["keepSnapshots"] {
            let this->keepSnapshots = keepSnapshots;
        }
        let this->modelName = "Phalcon\\Mvc\\Model";
        let this->appended_uuid = [];
        let this->appended = [];
        let this->removed = [];
        let this->errorMessages = [];
        if this->model instanceof Model {
            if globals_get("orm.late_state_binding") {
               let this->modelName = get_class(this->model);
            }
            let this->metaData = this->model->getModelsMetaData();
            let this->firstLevelCache = this->model->getModelsManager()->getFirstLevelCache();
        }
    }

    public function __serialize() -> array
    {
        return [
            "model"         : this->model,
            "cache"         : this->cache,
            "rows"          : this->toArray(false),
            "columnMap"     : this->columnMap,
            "hydrateMode"   : this->hydrateMode,
            "keepSnapshots" : this->keepSnapshots
        ];
    }

    public function __unserialize(array data) -> void
    {
        var keepSnapshots;

        let this->model       = data["model"],
            this->rows        = data["rows"],
            this->count       = count(data["rows"]),
            this->cache       = data["cache"],
            this->columnMap   = data["columnMap"],
            this->hydrateMode = data["hydrateMode"];

        if fetch keepSnapshots, data["keepSnapshots"] {
            let this->keepSnapshots = keepSnapshots;
        }
        let this->modelName = "Phalcon\\Mvc\\Model";
        let this->appended_uuid = [];
        let this->appended = [];
        let this->removed = [];
        let this->errorMessages = [];
        if this->model instanceof Model {
            if globals_get("orm.late_state_binding") {
               let this->modelName = get_class(this->model);
            }
            let this->metaData = this->model->getModelsMetaData();
            let this->firstLevelCache = this->model->getModelsManager()->getFirstLevelCache();
        }
    }

    public function hasFirstLevelCache() -> bool
    {
        return null !== this->firstLevelCache && this->firstLevelCache instanceof FirstLevelCache;
    }

    public function getFirstLevelCache() -> <FirstLevelCache> | null
    {
        return this->firstLevelCache;
    }
}
