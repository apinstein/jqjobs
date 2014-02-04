<?php


/**
 * Base class that represents a query for the 'jqstore_managed_job' table.
 *
 *
 *
 * @method JQStoreManagedJobQuery orderByAttemptNumber($order = Criteria::ASC) Order by the attempt_number column
 * @method JQStoreManagedJobQuery orderByCoalesceId($order = Criteria::ASC) Order by the coalesce_id column
 * @method JQStoreManagedJobQuery orderByCreationDts($order = Criteria::ASC) Order by the creation_dts column
 * @method JQStoreManagedJobQuery orderByEndDts($order = Criteria::ASC) Order by the end_dts column
 * @method JQStoreManagedJobQuery orderByErrorMessage($order = Criteria::ASC) Order by the error_message column
 * @method JQStoreManagedJobQuery orderByJob($order = Criteria::ASC) Order by the job column
 * @method JQStoreManagedJobQuery orderByJobId($order = Criteria::ASC) Order by the job_id column
 * @method JQStoreManagedJobQuery orderByMaxAttempts($order = Criteria::ASC) Order by the max_attempts column
 * @method JQStoreManagedJobQuery orderByPriority($order = Criteria::ASC) Order by the priority column
 * @method JQStoreManagedJobQuery orderByQueueName($order = Criteria::ASC) Order by the queue_name column
 * @method JQStoreManagedJobQuery orderByStartDts($order = Criteria::ASC) Order by the start_dts column
 * @method JQStoreManagedJobQuery orderByStatus($order = Criteria::ASC) Order by the status column
 * @method JQStoreManagedJobQuery orderByWorkFactor($order = Criteria::ASC) Order by the work_factor column
 *
 * @method JQStoreManagedJobQuery groupByAttemptNumber() Group by the attempt_number column
 * @method JQStoreManagedJobQuery groupByCoalesceId() Group by the coalesce_id column
 * @method JQStoreManagedJobQuery groupByCreationDts() Group by the creation_dts column
 * @method JQStoreManagedJobQuery groupByEndDts() Group by the end_dts column
 * @method JQStoreManagedJobQuery groupByErrorMessage() Group by the error_message column
 * @method JQStoreManagedJobQuery groupByJob() Group by the job column
 * @method JQStoreManagedJobQuery groupByJobId() Group by the job_id column
 * @method JQStoreManagedJobQuery groupByMaxAttempts() Group by the max_attempts column
 * @method JQStoreManagedJobQuery groupByPriority() Group by the priority column
 * @method JQStoreManagedJobQuery groupByQueueName() Group by the queue_name column
 * @method JQStoreManagedJobQuery groupByStartDts() Group by the start_dts column
 * @method JQStoreManagedJobQuery groupByStatus() Group by the status column
 * @method JQStoreManagedJobQuery groupByWorkFactor() Group by the work_factor column
 *
 * @method JQStoreManagedJobQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method JQStoreManagedJobQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method JQStoreManagedJobQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method JQStoreManagedJob findOne(PropelPDO $con = null) Return the first JQStoreManagedJob matching the query
 * @method JQStoreManagedJob findOneOrCreate(PropelPDO $con = null) Return the first JQStoreManagedJob matching the query, or a new JQStoreManagedJob object populated from the query conditions when no match is found
 *
 * @method JQStoreManagedJob findOneByAttemptNumber(int $attempt_number) Return the first JQStoreManagedJob filtered by the attempt_number column
 * @method JQStoreManagedJob findOneByCoalesceId(string $coalesce_id) Return the first JQStoreManagedJob filtered by the coalesce_id column
 * @method JQStoreManagedJob findOneByCreationDts(string $creation_dts) Return the first JQStoreManagedJob filtered by the creation_dts column
 * @method JQStoreManagedJob findOneByEndDts(string $end_dts) Return the first JQStoreManagedJob filtered by the end_dts column
 * @method JQStoreManagedJob findOneByErrorMessage(string $error_message) Return the first JQStoreManagedJob filtered by the error_message column
 * @method JQStoreManagedJob findOneByJob(string $job) Return the first JQStoreManagedJob filtered by the job column
 * @method JQStoreManagedJob findOneByJobId(int $job_id) Return the first JQStoreManagedJob filtered by the job_id column
 * @method JQStoreManagedJob findOneByMaxAttempts(int $max_attempts) Return the first JQStoreManagedJob filtered by the max_attempts column
 * @method JQStoreManagedJob findOneByPriority(int $priority) Return the first JQStoreManagedJob filtered by the priority column
 * @method JQStoreManagedJob findOneByQueueName(string $queue_name) Return the first JQStoreManagedJob filtered by the queue_name column
 * @method JQStoreManagedJob findOneByStartDts(string $start_dts) Return the first JQStoreManagedJob filtered by the start_dts column
 * @method JQStoreManagedJob findOneByStatus(string $status) Return the first JQStoreManagedJob filtered by the status column
 * @method JQStoreManagedJob findOneByWorkFactor(int $work_factor) Return the first JQStoreManagedJob filtered by the work_factor column
 *
 * @method array findByAttemptNumber(int $attempt_number) Return JQStoreManagedJob objects filtered by the attempt_number column
 * @method array findByCoalesceId(string $coalesce_id) Return JQStoreManagedJob objects filtered by the coalesce_id column
 * @method array findByCreationDts(string $creation_dts) Return JQStoreManagedJob objects filtered by the creation_dts column
 * @method array findByEndDts(string $end_dts) Return JQStoreManagedJob objects filtered by the end_dts column
 * @method array findByErrorMessage(string $error_message) Return JQStoreManagedJob objects filtered by the error_message column
 * @method array findByJob(string $job) Return JQStoreManagedJob objects filtered by the job column
 * @method array findByJobId(int $job_id) Return JQStoreManagedJob objects filtered by the job_id column
 * @method array findByMaxAttempts(int $max_attempts) Return JQStoreManagedJob objects filtered by the max_attempts column
 * @method array findByPriority(int $priority) Return JQStoreManagedJob objects filtered by the priority column
 * @method array findByQueueName(string $queue_name) Return JQStoreManagedJob objects filtered by the queue_name column
 * @method array findByStartDts(string $start_dts) Return JQStoreManagedJob objects filtered by the start_dts column
 * @method array findByStatus(string $status) Return JQStoreManagedJob objects filtered by the status column
 * @method array findByWorkFactor(int $work_factor) Return JQStoreManagedJob objects filtered by the work_factor column
 *
 * @package    propel.generator.jqjobs.om
 */
abstract class BaseJQStoreManagedJobQuery extends ModelCriteria
{
    /**
     * Initializes internal state of BaseJQStoreManagedJobQuery object.
     *
     * @param     string $dbName The dabase name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'jqjobs', $modelName = 'JQStoreManagedJob', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new JQStoreManagedJobQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     JQStoreManagedJobQuery|Criteria $criteria Optional Criteria to build the query from
     *
     * @return JQStoreManagedJobQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof JQStoreManagedJobQuery) {
            return $criteria;
        }
        $query = new JQStoreManagedJobQuery();
        if (null !== $modelAlias) {
            $query->setModelAlias($modelAlias);
        }
        if ($criteria instanceof Criteria) {
            $query->mergeWith($criteria);
        }

        return $query;
    }

    /**
     * Find object by primary key.
     * Propel uses the instance pool to skip the database if the object exists.
     * Go fast if the query is untouched.
     *
     * <code>
     * $obj  = $c->findPk(12, $con);
     * </code>
     *
     * @param mixed $key Primary key to use for the query
     * @param     PropelPDO $con an optional connection object
     *
     * @return   JQStoreManagedJob|JQStoreManagedJob[]|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = JQStoreManagedJobPeer::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is alredy in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getConnection(JQStoreManagedJobPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }
        $this->basePreSelect($con);
        if ($this->formatter || $this->modelAlias || $this->with || $this->select
         || $this->selectColumns || $this->asColumns || $this->selectModifiers
         || $this->map || $this->having || $this->joins) {
            return $this->findPkComplex($key, $con);
        } else {
            return $this->findPkSimple($key, $con);
        }
    }

    /**
     * Find object by primary key using raw SQL to go fast.
     * Bypass doSelect() and the object formatter by using generated code.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     PropelPDO $con A connection object
     *
     * @return   JQStoreManagedJob A model object, or null if the key is not found
     * @throws   PropelException
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT ATTEMPT_NUMBER, COALESCE_ID, CREATION_DTS, END_DTS, ERROR_MESSAGE, JOB, JOB_ID, MAX_ATTEMPTS, PRIORITY, QUEUE_NAME, START_DTS, STATUS, WORK_FACTOR FROM jqstore_managed_job WHERE JOB_ID = :p0';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $obj = new JQStoreManagedJob();
            $obj->hydrate($row);
            JQStoreManagedJobPeer::addInstanceToPool($obj, (string) $key);
        }
        $stmt->closeCursor();

        return $obj;
    }

    /**
     * Find object by primary key.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     PropelPDO $con A connection object
     *
     * @return JQStoreManagedJob|JQStoreManagedJob[]|mixed the result, formatted by the current formatter
     */
    protected function findPkComplex($key, $con)
    {
        // As the query uses a PK condition, no limit(1) is necessary.
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $stmt = $criteria
            ->filterByPrimaryKey($key)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->formatOne($stmt);
    }

    /**
     * Find objects by primary key
     * <code>
     * $objs = $c->findPks(array(12, 56, 832), $con);
     * </code>
     * @param     array $keys Primary keys to use for the query
     * @param     PropelPDO $con an optional connection object
     *
     * @return PropelObjectCollection|JQStoreManagedJob[]|mixed the list of results, formatted by the current formatter
     */
    public function findPks($keys, $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection($this->getDbName(), Propel::CONNECTION_READ);
        }
        $this->basePreSelect($con);
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $stmt = $criteria
            ->filterByPrimaryKeys($keys)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->format($stmt);
    }

    /**
     * Filter the query by primary key
     *
     * @param     mixed $key Primary key to use for the query
     *
     * @return JQStoreManagedJobQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(JQStoreManagedJobPeer::JOB_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return JQStoreManagedJobQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(JQStoreManagedJobPeer::JOB_ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the attempt_number column
     *
     * Example usage:
     * <code>
     * $query->filterByAttemptNumber(1234); // WHERE attempt_number = 1234
     * $query->filterByAttemptNumber(array(12, 34)); // WHERE attempt_number IN (12, 34)
     * $query->filterByAttemptNumber(array('min' => 12)); // WHERE attempt_number > 12
     * </code>
     *
     * @param     mixed $attemptNumber The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return JQStoreManagedJobQuery The current query, for fluid interface
     */
    public function filterByAttemptNumber($attemptNumber = null, $comparison = null)
    {
        if (is_array($attemptNumber)) {
            $useMinMax = false;
            if (isset($attemptNumber['min'])) {
                $this->addUsingAlias(JQStoreManagedJobPeer::ATTEMPT_NUMBER, $attemptNumber['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($attemptNumber['max'])) {
                $this->addUsingAlias(JQStoreManagedJobPeer::ATTEMPT_NUMBER, $attemptNumber['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(JQStoreManagedJobPeer::ATTEMPT_NUMBER, $attemptNumber, $comparison);
    }

    /**
     * Filter the query on the coalesce_id column
     *
     * Example usage:
     * <code>
     * $query->filterByCoalesceId('fooValue');   // WHERE coalesce_id = 'fooValue'
     * $query->filterByCoalesceId('%fooValue%'); // WHERE coalesce_id LIKE '%fooValue%'
     * </code>
     *
     * @param     string $coalesceId The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return JQStoreManagedJobQuery The current query, for fluid interface
     */
    public function filterByCoalesceId($coalesceId = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($coalesceId)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $coalesceId)) {
                $coalesceId = str_replace('*', '%', $coalesceId);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(JQStoreManagedJobPeer::COALESCE_ID, $coalesceId, $comparison);
    }

    /**
     * Filter the query on the creation_dts column
     *
     * Example usage:
     * <code>
     * $query->filterByCreationDts('2011-03-14'); // WHERE creation_dts = '2011-03-14'
     * $query->filterByCreationDts('now'); // WHERE creation_dts = '2011-03-14'
     * $query->filterByCreationDts(array('max' => 'yesterday')); // WHERE creation_dts > '2011-03-13'
     * </code>
     *
     * @param     mixed $creationDts The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return JQStoreManagedJobQuery The current query, for fluid interface
     */
    public function filterByCreationDts($creationDts = null, $comparison = null)
    {
        if (is_array($creationDts)) {
            $useMinMax = false;
            if (isset($creationDts['min'])) {
                $this->addUsingAlias(JQStoreManagedJobPeer::CREATION_DTS, $creationDts['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($creationDts['max'])) {
                $this->addUsingAlias(JQStoreManagedJobPeer::CREATION_DTS, $creationDts['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(JQStoreManagedJobPeer::CREATION_DTS, $creationDts, $comparison);
    }

    /**
     * Filter the query on the end_dts column
     *
     * Example usage:
     * <code>
     * $query->filterByEndDts('2011-03-14'); // WHERE end_dts = '2011-03-14'
     * $query->filterByEndDts('now'); // WHERE end_dts = '2011-03-14'
     * $query->filterByEndDts(array('max' => 'yesterday')); // WHERE end_dts > '2011-03-13'
     * </code>
     *
     * @param     mixed $endDts The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return JQStoreManagedJobQuery The current query, for fluid interface
     */
    public function filterByEndDts($endDts = null, $comparison = null)
    {
        if (is_array($endDts)) {
            $useMinMax = false;
            if (isset($endDts['min'])) {
                $this->addUsingAlias(JQStoreManagedJobPeer::END_DTS, $endDts['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($endDts['max'])) {
                $this->addUsingAlias(JQStoreManagedJobPeer::END_DTS, $endDts['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(JQStoreManagedJobPeer::END_DTS, $endDts, $comparison);
    }

    /**
     * Filter the query on the error_message column
     *
     * Example usage:
     * <code>
     * $query->filterByErrorMessage('fooValue');   // WHERE error_message = 'fooValue'
     * $query->filterByErrorMessage('%fooValue%'); // WHERE error_message LIKE '%fooValue%'
     * </code>
     *
     * @param     string $errorMessage The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return JQStoreManagedJobQuery The current query, for fluid interface
     */
    public function filterByErrorMessage($errorMessage = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($errorMessage)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $errorMessage)) {
                $errorMessage = str_replace('*', '%', $errorMessage);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(JQStoreManagedJobPeer::ERROR_MESSAGE, $errorMessage, $comparison);
    }

    /**
     * Filter the query on the job column
     *
     * Example usage:
     * <code>
     * $query->filterByJob('fooValue');   // WHERE job = 'fooValue'
     * $query->filterByJob('%fooValue%'); // WHERE job LIKE '%fooValue%'
     * </code>
     *
     * @param     string $job The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return JQStoreManagedJobQuery The current query, for fluid interface
     */
    public function filterByJob($job = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($job)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $job)) {
                $job = str_replace('*', '%', $job);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(JQStoreManagedJobPeer::JOB, $job, $comparison);
    }

    /**
     * Filter the query on the job_id column
     *
     * Example usage:
     * <code>
     * $query->filterByJobId(1234); // WHERE job_id = 1234
     * $query->filterByJobId(array(12, 34)); // WHERE job_id IN (12, 34)
     * $query->filterByJobId(array('min' => 12)); // WHERE job_id > 12
     * </code>
     *
     * @param     mixed $jobId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return JQStoreManagedJobQuery The current query, for fluid interface
     */
    public function filterByJobId($jobId = null, $comparison = null)
    {
        if (is_array($jobId) && null === $comparison) {
            $comparison = Criteria::IN;
        }

        return $this->addUsingAlias(JQStoreManagedJobPeer::JOB_ID, $jobId, $comparison);
    }

    /**
     * Filter the query on the max_attempts column
     *
     * Example usage:
     * <code>
     * $query->filterByMaxAttempts(1234); // WHERE max_attempts = 1234
     * $query->filterByMaxAttempts(array(12, 34)); // WHERE max_attempts IN (12, 34)
     * $query->filterByMaxAttempts(array('min' => 12)); // WHERE max_attempts > 12
     * </code>
     *
     * @param     mixed $maxAttempts The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return JQStoreManagedJobQuery The current query, for fluid interface
     */
    public function filterByMaxAttempts($maxAttempts = null, $comparison = null)
    {
        if (is_array($maxAttempts)) {
            $useMinMax = false;
            if (isset($maxAttempts['min'])) {
                $this->addUsingAlias(JQStoreManagedJobPeer::MAX_ATTEMPTS, $maxAttempts['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($maxAttempts['max'])) {
                $this->addUsingAlias(JQStoreManagedJobPeer::MAX_ATTEMPTS, $maxAttempts['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(JQStoreManagedJobPeer::MAX_ATTEMPTS, $maxAttempts, $comparison);
    }

    /**
     * Filter the query on the priority column
     *
     * Example usage:
     * <code>
     * $query->filterByPriority(1234); // WHERE priority = 1234
     * $query->filterByPriority(array(12, 34)); // WHERE priority IN (12, 34)
     * $query->filterByPriority(array('min' => 12)); // WHERE priority > 12
     * </code>
     *
     * @param     mixed $priority The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return JQStoreManagedJobQuery The current query, for fluid interface
     */
    public function filterByPriority($priority = null, $comparison = null)
    {
        if (is_array($priority)) {
            $useMinMax = false;
            if (isset($priority['min'])) {
                $this->addUsingAlias(JQStoreManagedJobPeer::PRIORITY, $priority['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($priority['max'])) {
                $this->addUsingAlias(JQStoreManagedJobPeer::PRIORITY, $priority['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(JQStoreManagedJobPeer::PRIORITY, $priority, $comparison);
    }

    /**
     * Filter the query on the queue_name column
     *
     * Example usage:
     * <code>
     * $query->filterByQueueName('fooValue');   // WHERE queue_name = 'fooValue'
     * $query->filterByQueueName('%fooValue%'); // WHERE queue_name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $queueName The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return JQStoreManagedJobQuery The current query, for fluid interface
     */
    public function filterByQueueName($queueName = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($queueName)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $queueName)) {
                $queueName = str_replace('*', '%', $queueName);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(JQStoreManagedJobPeer::QUEUE_NAME, $queueName, $comparison);
    }

    /**
     * Filter the query on the start_dts column
     *
     * Example usage:
     * <code>
     * $query->filterByStartDts('2011-03-14'); // WHERE start_dts = '2011-03-14'
     * $query->filterByStartDts('now'); // WHERE start_dts = '2011-03-14'
     * $query->filterByStartDts(array('max' => 'yesterday')); // WHERE start_dts > '2011-03-13'
     * </code>
     *
     * @param     mixed $startDts The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return JQStoreManagedJobQuery The current query, for fluid interface
     */
    public function filterByStartDts($startDts = null, $comparison = null)
    {
        if (is_array($startDts)) {
            $useMinMax = false;
            if (isset($startDts['min'])) {
                $this->addUsingAlias(JQStoreManagedJobPeer::START_DTS, $startDts['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($startDts['max'])) {
                $this->addUsingAlias(JQStoreManagedJobPeer::START_DTS, $startDts['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(JQStoreManagedJobPeer::START_DTS, $startDts, $comparison);
    }

    /**
     * Filter the query on the status column
     *
     * Example usage:
     * <code>
     * $query->filterByStatus('fooValue');   // WHERE status = 'fooValue'
     * $query->filterByStatus('%fooValue%'); // WHERE status LIKE '%fooValue%'
     * </code>
     *
     * @param     string $status The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return JQStoreManagedJobQuery The current query, for fluid interface
     */
    public function filterByStatus($status = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($status)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $status)) {
                $status = str_replace('*', '%', $status);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(JQStoreManagedJobPeer::STATUS, $status, $comparison);
    }

    /**
     * Filter the query on the work_factor column
     *
     * Example usage:
     * <code>
     * $query->filterByWorkFactor(1234); // WHERE work_factor = 1234
     * $query->filterByWorkFactor(array(12, 34)); // WHERE work_factor IN (12, 34)
     * $query->filterByWorkFactor(array('min' => 12)); // WHERE work_factor > 12
     * </code>
     *
     * @param     mixed $workFactor The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return JQStoreManagedJobQuery The current query, for fluid interface
     */
    public function filterByWorkFactor($workFactor = null, $comparison = null)
    {
        if (is_array($workFactor)) {
            $useMinMax = false;
            if (isset($workFactor['min'])) {
                $this->addUsingAlias(JQStoreManagedJobPeer::WORK_FACTOR, $workFactor['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($workFactor['max'])) {
                $this->addUsingAlias(JQStoreManagedJobPeer::WORK_FACTOR, $workFactor['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(JQStoreManagedJobPeer::WORK_FACTOR, $workFactor, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   JQStoreManagedJob $jQStoreManagedJob Object to remove from the list of results
     *
     * @return JQStoreManagedJobQuery The current query, for fluid interface
     */
    public function prune($jQStoreManagedJob = null)
    {
        if ($jQStoreManagedJob) {
            $this->addUsingAlias(JQStoreManagedJobPeer::JOB_ID, $jQStoreManagedJob->getJobId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

}
