<?php


/**
 * Base static class for performing query and update operations on the 'jqstore_managed_job' table.
 *
 *
 *
 * @package propel.generator.jqjobs.om
 */
abstract class BaseJQStoreManagedJobPeer
{

    /** the default database name for this class */
    const DATABASE_NAME = 'jqjobs';

    /** the table name for this class */
    const TABLE_NAME = 'jqstore_managed_job';

    /** the related Propel class for this table */
    const OM_CLASS = 'JQStoreManagedJob';

    /** the related TableMap class for this table */
    const TM_CLASS = 'JQStoreManagedJobTableMap';

    /** The total number of columns. */
    const NUM_COLUMNS = 13;

    /** The number of lazy-loaded columns. */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /** The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS) */
    const NUM_HYDRATE_COLUMNS = 13;

    /** the column name for the ATTEMPT_NUMBER field */
    const ATTEMPT_NUMBER = 'jqstore_managed_job.ATTEMPT_NUMBER';

    /** the column name for the COALESCE_ID field */
    const COALESCE_ID = 'jqstore_managed_job.COALESCE_ID';

    /** the column name for the CREATION_DTS field */
    const CREATION_DTS = 'jqstore_managed_job.CREATION_DTS';

    /** the column name for the END_DTS field */
    const END_DTS = 'jqstore_managed_job.END_DTS';

    /** the column name for the ERROR_MESSAGE field */
    const ERROR_MESSAGE = 'jqstore_managed_job.ERROR_MESSAGE';

    /** the column name for the JOB field */
    const JOB = 'jqstore_managed_job.JOB';

    /** the column name for the JOB_ID field */
    const JOB_ID = 'jqstore_managed_job.JOB_ID';

    /** the column name for the MAX_ATTEMPTS field */
    const MAX_ATTEMPTS = 'jqstore_managed_job.MAX_ATTEMPTS';

    /** the column name for the PRIORITY field */
    const PRIORITY = 'jqstore_managed_job.PRIORITY';

    /** the column name for the QUEUE_NAME field */
    const QUEUE_NAME = 'jqstore_managed_job.QUEUE_NAME';

    /** the column name for the START_DTS field */
    const START_DTS = 'jqstore_managed_job.START_DTS';

    /** the column name for the STATUS field */
    const STATUS = 'jqstore_managed_job.STATUS';

    /** the column name for the WORK_FACTOR field */
    const WORK_FACTOR = 'jqstore_managed_job.WORK_FACTOR';

    /** The default string format for model objects of the related table **/
    const DEFAULT_STRING_FORMAT = 'YAML';

    /**
     * An identiy map to hold any loaded instances of JQStoreManagedJob objects.
     * This must be public so that other peer classes can access this when hydrating from JOIN
     * queries.
     * @var        array JQStoreManagedJob[]
     */
    public static $instances = array();


    /**
     * holds an array of fieldnames
     *
     * first dimension keys are the type constants
     * e.g. JQStoreManagedJobPeer::$fieldNames[JQStoreManagedJobPeer::TYPE_PHPNAME][0] = 'Id'
     */
    protected static $fieldNames = array (
        BasePeer::TYPE_PHPNAME => array ('AttemptNumber', 'CoalesceId', 'CreationDts', 'EndDts', 'ErrorMessage', 'Job', 'JobId', 'MaxAttempts', 'Priority', 'QueueName', 'StartDts', 'Status', 'WorkFactor', ),
        BasePeer::TYPE_STUDLYPHPNAME => array ('attemptNumber', 'coalesceId', 'creationDts', 'endDts', 'errorMessage', 'job', 'jobId', 'maxAttempts', 'priority', 'queueName', 'startDts', 'status', 'workFactor', ),
        BasePeer::TYPE_COLNAME => array (JQStoreManagedJobPeer::ATTEMPT_NUMBER, JQStoreManagedJobPeer::COALESCE_ID, JQStoreManagedJobPeer::CREATION_DTS, JQStoreManagedJobPeer::END_DTS, JQStoreManagedJobPeer::ERROR_MESSAGE, JQStoreManagedJobPeer::JOB, JQStoreManagedJobPeer::JOB_ID, JQStoreManagedJobPeer::MAX_ATTEMPTS, JQStoreManagedJobPeer::PRIORITY, JQStoreManagedJobPeer::QUEUE_NAME, JQStoreManagedJobPeer::START_DTS, JQStoreManagedJobPeer::STATUS, JQStoreManagedJobPeer::WORK_FACTOR, ),
        BasePeer::TYPE_RAW_COLNAME => array ('ATTEMPT_NUMBER', 'COALESCE_ID', 'CREATION_DTS', 'END_DTS', 'ERROR_MESSAGE', 'JOB', 'JOB_ID', 'MAX_ATTEMPTS', 'PRIORITY', 'QUEUE_NAME', 'START_DTS', 'STATUS', 'WORK_FACTOR', ),
        BasePeer::TYPE_FIELDNAME => array ('attempt_number', 'coalesce_id', 'creation_dts', 'end_dts', 'error_message', 'job', 'job_id', 'max_attempts', 'priority', 'queue_name', 'start_dts', 'status', 'work_factor', ),
        BasePeer::TYPE_NUM => array (0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. JQStoreManagedJobPeer::$fieldNames[BasePeer::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        BasePeer::TYPE_PHPNAME => array ('AttemptNumber' => 0, 'CoalesceId' => 1, 'CreationDts' => 2, 'EndDts' => 3, 'ErrorMessage' => 4, 'Job' => 5, 'JobId' => 6, 'MaxAttempts' => 7, 'Priority' => 8, 'QueueName' => 9, 'StartDts' => 10, 'Status' => 11, 'WorkFactor' => 12, ),
        BasePeer::TYPE_STUDLYPHPNAME => array ('attemptNumber' => 0, 'coalesceId' => 1, 'creationDts' => 2, 'endDts' => 3, 'errorMessage' => 4, 'job' => 5, 'jobId' => 6, 'maxAttempts' => 7, 'priority' => 8, 'queueName' => 9, 'startDts' => 10, 'status' => 11, 'workFactor' => 12, ),
        BasePeer::TYPE_COLNAME => array (JQStoreManagedJobPeer::ATTEMPT_NUMBER => 0, JQStoreManagedJobPeer::COALESCE_ID => 1, JQStoreManagedJobPeer::CREATION_DTS => 2, JQStoreManagedJobPeer::END_DTS => 3, JQStoreManagedJobPeer::ERROR_MESSAGE => 4, JQStoreManagedJobPeer::JOB => 5, JQStoreManagedJobPeer::JOB_ID => 6, JQStoreManagedJobPeer::MAX_ATTEMPTS => 7, JQStoreManagedJobPeer::PRIORITY => 8, JQStoreManagedJobPeer::QUEUE_NAME => 9, JQStoreManagedJobPeer::START_DTS => 10, JQStoreManagedJobPeer::STATUS => 11, JQStoreManagedJobPeer::WORK_FACTOR => 12, ),
        BasePeer::TYPE_RAW_COLNAME => array ('ATTEMPT_NUMBER' => 0, 'COALESCE_ID' => 1, 'CREATION_DTS' => 2, 'END_DTS' => 3, 'ERROR_MESSAGE' => 4, 'JOB' => 5, 'JOB_ID' => 6, 'MAX_ATTEMPTS' => 7, 'PRIORITY' => 8, 'QUEUE_NAME' => 9, 'START_DTS' => 10, 'STATUS' => 11, 'WORK_FACTOR' => 12, ),
        BasePeer::TYPE_FIELDNAME => array ('attempt_number' => 0, 'coalesce_id' => 1, 'creation_dts' => 2, 'end_dts' => 3, 'error_message' => 4, 'job' => 5, 'job_id' => 6, 'max_attempts' => 7, 'priority' => 8, 'queue_name' => 9, 'start_dts' => 10, 'status' => 11, 'work_factor' => 12, ),
        BasePeer::TYPE_NUM => array (0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, )
    );

    /**
     * Translates a fieldname to another type
     *
     * @param      string $name field name
     * @param      string $fromType One of the class type constants BasePeer::TYPE_PHPNAME, BasePeer::TYPE_STUDLYPHPNAME
     *                         BasePeer::TYPE_COLNAME, BasePeer::TYPE_FIELDNAME, BasePeer::TYPE_NUM
     * @param      string $toType   One of the class type constants
     * @return string          translated name of the field.
     * @throws PropelException - if the specified name could not be found in the fieldname mappings.
     */
    public static function translateFieldName($name, $fromType, $toType)
    {
        $toNames = JQStoreManagedJobPeer::getFieldNames($toType);
        $key = isset(JQStoreManagedJobPeer::$fieldKeys[$fromType][$name]) ? JQStoreManagedJobPeer::$fieldKeys[$fromType][$name] : null;
        if ($key === null) {
            throw new PropelException("'$name' could not be found in the field names of type '$fromType'. These are: " . print_r(JQStoreManagedJobPeer::$fieldKeys[$fromType], true));
        }

        return $toNames[$key];
    }

    /**
     * Returns an array of field names.
     *
     * @param      string $type The type of fieldnames to return:
     *                      One of the class type constants BasePeer::TYPE_PHPNAME, BasePeer::TYPE_STUDLYPHPNAME
     *                      BasePeer::TYPE_COLNAME, BasePeer::TYPE_FIELDNAME, BasePeer::TYPE_NUM
     * @return array           A list of field names
     * @throws PropelException - if the type is not valid.
     */
    public static function getFieldNames($type = BasePeer::TYPE_PHPNAME)
    {
        if (!array_key_exists($type, JQStoreManagedJobPeer::$fieldNames)) {
            throw new PropelException('Method getFieldNames() expects the parameter $type to be one of the class constants BasePeer::TYPE_PHPNAME, BasePeer::TYPE_STUDLYPHPNAME, BasePeer::TYPE_COLNAME, BasePeer::TYPE_FIELDNAME, BasePeer::TYPE_NUM. ' . $type . ' was given.');
        }

        return JQStoreManagedJobPeer::$fieldNames[$type];
    }

    /**
     * Convenience method which changes table.column to alias.column.
     *
     * Using this method you can maintain SQL abstraction while using column aliases.
     * <code>
     *		$c->addAlias("alias1", TablePeer::TABLE_NAME);
     *		$c->addJoin(TablePeer::alias("alias1", TablePeer::PRIMARY_KEY_COLUMN), TablePeer::PRIMARY_KEY_COLUMN);
     * </code>
     * @param      string $alias The alias for the current table.
     * @param      string $column The column name for current table. (i.e. JQStoreManagedJobPeer::COLUMN_NAME).
     * @return string
     */
    public static function alias($alias, $column)
    {
        return str_replace(JQStoreManagedJobPeer::TABLE_NAME.'.', $alias.'.', $column);
    }

    /**
     * Add all the columns needed to create a new object.
     *
     * Note: any columns that were marked with lazyLoad="true" in the
     * XML schema will not be added to the select list and only loaded
     * on demand.
     *
     * @param      Criteria $criteria object containing the columns to add.
     * @param      string   $alias    optional table alias
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function addSelectColumns(Criteria $criteria, $alias = null)
    {
        if (null === $alias) {
            $criteria->addSelectColumn(JQStoreManagedJobPeer::ATTEMPT_NUMBER);
            $criteria->addSelectColumn(JQStoreManagedJobPeer::COALESCE_ID);
            $criteria->addSelectColumn(JQStoreManagedJobPeer::CREATION_DTS);
            $criteria->addSelectColumn(JQStoreManagedJobPeer::END_DTS);
            $criteria->addSelectColumn(JQStoreManagedJobPeer::ERROR_MESSAGE);
            $criteria->addSelectColumn(JQStoreManagedJobPeer::JOB);
            $criteria->addSelectColumn(JQStoreManagedJobPeer::JOB_ID);
            $criteria->addSelectColumn(JQStoreManagedJobPeer::MAX_ATTEMPTS);
            $criteria->addSelectColumn(JQStoreManagedJobPeer::PRIORITY);
            $criteria->addSelectColumn(JQStoreManagedJobPeer::QUEUE_NAME);
            $criteria->addSelectColumn(JQStoreManagedJobPeer::START_DTS);
            $criteria->addSelectColumn(JQStoreManagedJobPeer::STATUS);
            $criteria->addSelectColumn(JQStoreManagedJobPeer::WORK_FACTOR);
        } else {
            $criteria->addSelectColumn($alias . '.ATTEMPT_NUMBER');
            $criteria->addSelectColumn($alias . '.COALESCE_ID');
            $criteria->addSelectColumn($alias . '.CREATION_DTS');
            $criteria->addSelectColumn($alias . '.END_DTS');
            $criteria->addSelectColumn($alias . '.ERROR_MESSAGE');
            $criteria->addSelectColumn($alias . '.JOB');
            $criteria->addSelectColumn($alias . '.JOB_ID');
            $criteria->addSelectColumn($alias . '.MAX_ATTEMPTS');
            $criteria->addSelectColumn($alias . '.PRIORITY');
            $criteria->addSelectColumn($alias . '.QUEUE_NAME');
            $criteria->addSelectColumn($alias . '.START_DTS');
            $criteria->addSelectColumn($alias . '.STATUS');
            $criteria->addSelectColumn($alias . '.WORK_FACTOR');
        }
    }

    /**
     * Returns the number of rows matching criteria.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct Whether to select only distinct columns; deprecated: use Criteria->setDistinct() instead.
     * @param      PropelPDO $con
     * @return int Number of matching rows.
     */
    public static function doCount(Criteria $criteria, $distinct = false, PropelPDO $con = null)
    {
        // we may modify criteria, so copy it first
        $criteria = clone $criteria;

        // We need to set the primary table name, since in the case that there are no WHERE columns
        // it will be impossible for the BasePeer::createSelectSql() method to determine which
        // tables go into the FROM clause.
        $criteria->setPrimaryTableName(JQStoreManagedJobPeer::TABLE_NAME);

        if ($distinct && !in_array(Criteria::DISTINCT, $criteria->getSelectModifiers())) {
            $criteria->setDistinct();
        }

        if (!$criteria->hasSelectClause()) {
            JQStoreManagedJobPeer::addSelectColumns($criteria);
        }

        $criteria->clearOrderByColumns(); // ORDER BY won't ever affect the count
        $criteria->setDbName(JQStoreManagedJobPeer::DATABASE_NAME); // Set the correct dbName

        if ($con === null) {
            $con = Propel::getConnection(JQStoreManagedJobPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }
        // BasePeer returns a PDOStatement
        $stmt = BasePeer::doCount($criteria, $con);

        if ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $count = (int) $row[0];
        } else {
            $count = 0; // no rows returned; we infer that means 0 matches.
        }
        $stmt->closeCursor();

        return $count;
    }
    /**
     * Selects one object from the DB.
     *
     * @param      Criteria $criteria object used to create the SELECT statement.
     * @param      PropelPDO $con
     * @return                 JQStoreManagedJob
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function doSelectOne(Criteria $criteria, PropelPDO $con = null)
    {
        $critcopy = clone $criteria;
        $critcopy->setLimit(1);
        $objects = JQStoreManagedJobPeer::doSelect($critcopy, $con);
        if ($objects) {
            return $objects[0];
        }

        return null;
    }
    /**
     * Selects several row from the DB.
     *
     * @param      Criteria $criteria The Criteria object used to build the SELECT statement.
     * @param      PropelPDO $con
     * @return array           Array of selected Objects
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function doSelect(Criteria $criteria, PropelPDO $con = null)
    {
        return JQStoreManagedJobPeer::populateObjects(JQStoreManagedJobPeer::doSelectStmt($criteria, $con));
    }
    /**
     * Prepares the Criteria object and uses the parent doSelect() method to execute a PDOStatement.
     *
     * Use this method directly if you want to work with an executed statement durirectly (for example
     * to perform your own object hydration).
     *
     * @param      Criteria $criteria The Criteria object used to build the SELECT statement.
     * @param      PropelPDO $con The connection to use
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     * @return PDOStatement The executed PDOStatement object.
     * @see        BasePeer::doSelect()
     */
    public static function doSelectStmt(Criteria $criteria, PropelPDO $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection(JQStoreManagedJobPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        if (!$criteria->hasSelectClause()) {
            $criteria = clone $criteria;
            JQStoreManagedJobPeer::addSelectColumns($criteria);
        }

        // Set the correct dbName
        $criteria->setDbName(JQStoreManagedJobPeer::DATABASE_NAME);

        // BasePeer returns a PDOStatement
        return BasePeer::doSelect($criteria, $con);
    }
    /**
     * Adds an object to the instance pool.
     *
     * Propel keeps cached copies of objects in an instance pool when they are retrieved
     * from the database.  In some cases -- especially when you override doSelect*()
     * methods in your stub classes -- you may need to explicitly add objects
     * to the cache in order to ensure that the same objects are always returned by doSelect*()
     * and retrieveByPK*() calls.
     *
     * @param      JQStoreManagedJob $obj A JQStoreManagedJob object.
     * @param      string $key (optional) key to use for instance map (for performance boost if key was already calculated externally).
     */
    public static function addInstanceToPool($obj, $key = null)
    {
        if (Propel::isInstancePoolingEnabled()) {
            if ($key === null) {
                $key = (string) $obj->getJobId();
            } // if key === null
            JQStoreManagedJobPeer::$instances[$key] = $obj;
        }
    }

    /**
     * Removes an object from the instance pool.
     *
     * Propel keeps cached copies of objects in an instance pool when they are retrieved
     * from the database.  In some cases -- especially when you override doDelete
     * methods in your stub classes -- you may need to explicitly remove objects
     * from the cache in order to prevent returning objects that no longer exist.
     *
     * @param      mixed $value A JQStoreManagedJob object or a primary key value.
     *
     * @return void
     * @throws PropelException - if the value is invalid.
     */
    public static function removeInstanceFromPool($value)
    {
        if (Propel::isInstancePoolingEnabled() && $value !== null) {
            if (is_object($value) && $value instanceof JQStoreManagedJob) {
                $key = (string) $value->getJobId();
            } elseif (is_scalar($value)) {
                // assume we've been passed a primary key
                $key = (string) $value;
            } else {
                $e = new PropelException("Invalid value passed to removeInstanceFromPool().  Expected primary key or JQStoreManagedJob object; got " . (is_object($value) ? get_class($value) . ' object.' : var_export($value,true)));
                throw $e;
            }

            unset(JQStoreManagedJobPeer::$instances[$key]);
        }
    } // removeInstanceFromPool()

    /**
     * Retrieves a string version of the primary key from the DB resultset row that can be used to uniquely identify a row in this table.
     *
     * For tables with a single-column primary key, that simple pkey value will be returned.  For tables with
     * a multi-column primary key, a serialize()d version of the primary key will be returned.
     *
     * @param      string $key The key (@see getPrimaryKeyHash()) for this instance.
     * @return   JQStoreManagedJob Found object or null if 1) no instance exists for specified key or 2) instance pooling has been disabled.
     * @see        getPrimaryKeyHash()
     */
    public static function getInstanceFromPool($key)
    {
        if (Propel::isInstancePoolingEnabled()) {
            if (isset(JQStoreManagedJobPeer::$instances[$key])) {
                return JQStoreManagedJobPeer::$instances[$key];
            }
        }

        return null; // just to be explicit
    }

    /**
     * Clear the instance pool.
     *
     * @return void
     */
    public static function clearInstancePool()
    {
        JQStoreManagedJobPeer::$instances = array();
    }

    /**
     * Method to invalidate the instance pool of all tables related to jqstore_managed_job
     * by a foreign key with ON DELETE CASCADE
     */
    public static function clearRelatedInstancePool()
    {
    }

    /**
     * Retrieves a string version of the primary key from the DB resultset row that can be used to uniquely identify a row in this table.
     *
     * For tables with a single-column primary key, that simple pkey value will be returned.  For tables with
     * a multi-column primary key, a serialize()d version of the primary key will be returned.
     *
     * @param      array $row PropelPDO resultset row.
     * @param      int $startcol The 0-based offset for reading from the resultset row.
     * @return string A string version of PK or null if the components of primary key in result array are all null.
     */
    public static function getPrimaryKeyHashFromRow($row, $startcol = 0)
    {
        // If the PK cannot be derived from the row, return null.
        if ($row[$startcol + 6] === null) {
            return null;
        }

        return (string) $row[$startcol + 6];
    }

    /**
     * Retrieves the primary key from the DB resultset row
     * For tables with a single-column primary key, that simple pkey value will be returned.  For tables with
     * a multi-column primary key, an array of the primary key columns will be returned.
     *
     * @param      array $row PropelPDO resultset row.
     * @param      int $startcol The 0-based offset for reading from the resultset row.
     * @return mixed The primary key of the row
     */
    public static function getPrimaryKeyFromRow($row, $startcol = 0)
    {

        return (int) $row[$startcol + 6];
    }

    /**
     * The returned array will contain objects of the default type or
     * objects that inherit from the default.
     *
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function populateObjects(PDOStatement $stmt)
    {
        $results = array();

        // set the class once to avoid overhead in the loop
        $cls = JQStoreManagedJobPeer::getOMClass();
        // populate the object(s)
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $key = JQStoreManagedJobPeer::getPrimaryKeyHashFromRow($row, 0);
            if (null !== ($obj = JQStoreManagedJobPeer::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                JQStoreManagedJobPeer::addInstanceToPool($obj, $key);
            } // if key exists
        }
        $stmt->closeCursor();

        return $results;
    }
    /**
     * Populates an object of the default type or an object that inherit from the default.
     *
     * @param      array $row PropelPDO resultset row.
     * @param      int $startcol The 0-based offset for reading from the resultset row.
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     * @return array (JQStoreManagedJob object, last column rank)
     */
    public static function populateObject($row, $startcol = 0)
    {
        $key = JQStoreManagedJobPeer::getPrimaryKeyHashFromRow($row, $startcol);
        if (null !== ($obj = JQStoreManagedJobPeer::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $startcol, true); // rehydrate
            $col = $startcol + JQStoreManagedJobPeer::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = JQStoreManagedJobPeer::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $startcol);
            JQStoreManagedJobPeer::addInstanceToPool($obj, $key);
        }

        return array($obj, $col);
    }

    /**
     * Returns the TableMap related to this peer.
     * This method is not needed for general use but a specific application could have a need.
     * @return TableMap
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function getTableMap()
    {
        return Propel::getDatabaseMap(JQStoreManagedJobPeer::DATABASE_NAME)->getTable(JQStoreManagedJobPeer::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this peer class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getDatabaseMap(BaseJQStoreManagedJobPeer::DATABASE_NAME);
      if (!$dbMap->hasTable(BaseJQStoreManagedJobPeer::TABLE_NAME)) {
        $dbMap->addTableObject(new JQStoreManagedJobTableMap());
      }
    }

    /**
     * The class that the Peer will make instances of.
     *
     *
     * @return string ClassName
     */
    public static function getOMClass()
    {
        return JQStoreManagedJobPeer::OM_CLASS;
    }

    /**
     * Performs an INSERT on the database, given a JQStoreManagedJob or Criteria object.
     *
     * @param      mixed $values Criteria or JQStoreManagedJob object containing data that is used to create the INSERT statement.
     * @param      PropelPDO $con the PropelPDO connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function doInsert($values, PropelPDO $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection(JQStoreManagedJobPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        if ($values instanceof Criteria) {
            $criteria = clone $values; // rename for clarity
        } else {
            $criteria = $values->buildCriteria(); // build Criteria from JQStoreManagedJob object
        }

        if ($criteria->containsKey(JQStoreManagedJobPeer::JOB_ID) && $criteria->keyContainsValue(JQStoreManagedJobPeer::JOB_ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.JQStoreManagedJobPeer::JOB_ID.')');
        }


        // Set the correct dbName
        $criteria->setDbName(JQStoreManagedJobPeer::DATABASE_NAME);

        try {
            // use transaction because $criteria could contain info
            // for more than one table (I guess, conceivably)
            $con->beginTransaction();
            $pk = BasePeer::doInsert($criteria, $con);
            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $pk;
    }

    /**
     * Performs an UPDATE on the database, given a JQStoreManagedJob or Criteria object.
     *
     * @param      mixed $values Criteria or JQStoreManagedJob object containing data that is used to create the UPDATE statement.
     * @param      PropelPDO $con The connection to use (specify PropelPDO connection object to exert more control over transactions).
     * @return int             The number of affected rows (if supported by underlying database driver).
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function doUpdate($values, PropelPDO $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection(JQStoreManagedJobPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        $selectCriteria = new Criteria(JQStoreManagedJobPeer::DATABASE_NAME);

        if ($values instanceof Criteria) {
            $criteria = clone $values; // rename for clarity

            $comparison = $criteria->getComparison(JQStoreManagedJobPeer::JOB_ID);
            $value = $criteria->remove(JQStoreManagedJobPeer::JOB_ID);
            if ($value) {
                $selectCriteria->add(JQStoreManagedJobPeer::JOB_ID, $value, $comparison);
            } else {
                $selectCriteria->setPrimaryTableName(JQStoreManagedJobPeer::TABLE_NAME);
            }

        } else { // $values is JQStoreManagedJob object
            $criteria = $values->buildCriteria(); // gets full criteria
            $selectCriteria = $values->buildPkeyCriteria(); // gets criteria w/ primary key(s)
        }

        // set the correct dbName
        $criteria->setDbName(JQStoreManagedJobPeer::DATABASE_NAME);

        return BasePeer::doUpdate($selectCriteria, $criteria, $con);
    }

    /**
     * Deletes all rows from the jqstore_managed_job table.
     *
     * @param      PropelPDO $con the connection to use
     * @return int             The number of affected rows (if supported by underlying database driver).
     * @throws PropelException
     */
    public static function doDeleteAll(PropelPDO $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection(JQStoreManagedJobPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }
        $affectedRows = 0; // initialize var to track total num of affected rows
        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();
            $affectedRows += BasePeer::doDeleteAll(JQStoreManagedJobPeer::TABLE_NAME, $con, JQStoreManagedJobPeer::DATABASE_NAME);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            JQStoreManagedJobPeer::clearInstancePool();
            JQStoreManagedJobPeer::clearRelatedInstancePool();
            $con->commit();

            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }
    }

    /**
     * Performs a DELETE on the database, given a JQStoreManagedJob or Criteria object OR a primary key value.
     *
     * @param      mixed $values Criteria or JQStoreManagedJob object or primary key or array of primary keys
     *              which is used to create the DELETE statement
     * @param      PropelPDO $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).  This includes CASCADE-related rows
     *				if supported by native driver or if emulated using Propel.
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
     public static function doDelete($values, PropelPDO $con = null)
     {
        if ($con === null) {
            $con = Propel::getConnection(JQStoreManagedJobPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        if ($values instanceof Criteria) {
            // invalidate the cache for all objects of this type, since we have no
            // way of knowing (without running a query) what objects should be invalidated
            // from the cache based on this Criteria.
            JQStoreManagedJobPeer::clearInstancePool();
            // rename for clarity
            $criteria = clone $values;
        } elseif ($values instanceof JQStoreManagedJob) { // it's a model object
            // invalidate the cache for this single object
            JQStoreManagedJobPeer::removeInstanceFromPool($values);
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(JQStoreManagedJobPeer::DATABASE_NAME);
            $criteria->add(JQStoreManagedJobPeer::JOB_ID, (array) $values, Criteria::IN);
            // invalidate the cache for this object(s)
            foreach ((array) $values as $singleval) {
                JQStoreManagedJobPeer::removeInstanceFromPool($singleval);
            }
        }

        // Set the correct dbName
        $criteria->setDbName(JQStoreManagedJobPeer::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();

            $affectedRows += BasePeer::doDelete($criteria, $con);
            JQStoreManagedJobPeer::clearRelatedInstancePool();
            $con->commit();

            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }
    }

    /**
     * Validates all modified columns of given JQStoreManagedJob object.
     * If parameter $columns is either a single column name or an array of column names
     * than only those columns are validated.
     *
     * NOTICE: This does not apply to primary or foreign keys for now.
     *
     * @param      JQStoreManagedJob $obj The object to validate.
     * @param      mixed $cols Column name or array of column names.
     *
     * @return mixed TRUE if all columns are valid or the error message of the first invalid column.
     */
    public static function doValidate($obj, $cols = null)
    {
        $columns = array();

        if ($cols) {
            $dbMap = Propel::getDatabaseMap(JQStoreManagedJobPeer::DATABASE_NAME);
            $tableMap = $dbMap->getTable(JQStoreManagedJobPeer::TABLE_NAME);

            if (! is_array($cols)) {
                $cols = array($cols);
            }

            foreach ($cols as $colName) {
                if ($tableMap->hasColumn($colName)) {
                    $get = 'get' . $tableMap->getColumn($colName)->getPhpName();
                    $columns[$colName] = $obj->$get();
                }
            }
        } else {

        }

        return BasePeer::doValidate(JQStoreManagedJobPeer::DATABASE_NAME, JQStoreManagedJobPeer::TABLE_NAME, $columns);
    }

    /**
     * Retrieve a single object by pkey.
     *
     * @param      int $pk the primary key.
     * @param      PropelPDO $con the connection to use
     * @return JQStoreManagedJob
     */
    public static function retrieveByPK($pk, PropelPDO $con = null)
    {

        if (null !== ($obj = JQStoreManagedJobPeer::getInstanceFromPool((string) $pk))) {
            return $obj;
        }

        if ($con === null) {
            $con = Propel::getConnection(JQStoreManagedJobPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        $criteria = new Criteria(JQStoreManagedJobPeer::DATABASE_NAME);
        $criteria->add(JQStoreManagedJobPeer::JOB_ID, $pk);

        $v = JQStoreManagedJobPeer::doSelect($criteria, $con);

        return !empty($v) > 0 ? $v[0] : null;
    }

    /**
     * Retrieve multiple objects by pkey.
     *
     * @param      array $pks List of primary keys
     * @param      PropelPDO $con the connection to use
     * @return JQStoreManagedJob[]
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function retrieveByPKs($pks, PropelPDO $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection(JQStoreManagedJobPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        $objs = null;
        if (empty($pks)) {
            $objs = array();
        } else {
            $criteria = new Criteria(JQStoreManagedJobPeer::DATABASE_NAME);
            $criteria->add(JQStoreManagedJobPeer::JOB_ID, $pks, Criteria::IN);
            $objs = JQStoreManagedJobPeer::doSelect($criteria, $con);
        }

        return $objs;
    }

} // BaseJQStoreManagedJobPeer

// This is the static code needed to register the TableMap for this table with the main Propel class.
//
BaseJQStoreManagedJobPeer::buildTableMap();

