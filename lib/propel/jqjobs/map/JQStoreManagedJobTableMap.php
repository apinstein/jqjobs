<?php



/**
 * This class defines the structure of the 'jqstore_managed_job' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 * @package    propel.generator.jqjobs.map
 */
class JQStoreManagedJobTableMap extends TableMap
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'jqjobs.map.JQStoreManagedJobTableMap';

    /**
     * Initialize the table attributes, columns and validators
     * Relations are not initialized by this method since they are lazy loaded
     *
     * @return void
     * @throws PropelException
     */
    public function initialize()
    {
        // attributes
        $this->setName('jqstore_managed_job');
        $this->setPhpName('JQStoreManagedJob');
        $this->setClassname('JQStoreManagedJob');
        $this->setPackage('jqjobs');
        $this->setUseIdGenerator(true);
        $this->setPrimaryKeyMethodInfo('jqstore_managed_job_job_id_seq');
        // columns
        $this->addColumn('ATTEMPT_NUMBER', 'AttemptNumber', 'INTEGER', false, null, null);
        $this->addColumn('COALESCE_ID', 'CoalesceId', 'VARCHAR', false, 100, null);
        $this->addColumn('CREATION_DTS', 'CreationDts', 'TIMESTAMP', false, null, null);
        $this->addColumn('END_DTS', 'EndDts', 'TIMESTAMP', false, null, null);
        $this->addColumn('ERROR_MESSAGE', 'ErrorMessage', 'LONGVARCHAR', false, null, null);
        $this->addColumn('JOB', 'Job', 'LONGVARCHAR', false, null, null);
        $this->addPrimaryKey('JOB_ID', 'JobId', 'INTEGER', true, null, null);
        $this->addColumn('MAX_ATTEMPTS', 'MaxAttempts', 'INTEGER', false, null, null);
        $this->addColumn('PRIORITY', 'Priority', 'INTEGER', false, null, null);
        $this->addColumn('QUEUE_NAME', 'QueueName', 'LONGVARCHAR', false, null, null);
        $this->addColumn('START_DTS', 'StartDts', 'TIMESTAMP', false, null, null);
        $this->addColumn('STATUS', 'Status', 'LONGVARCHAR', false, null, null);
        $this->addColumn('WORK_FACTOR', 'WorkFactor', 'INTEGER', false, null, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
    } // buildRelations()

} // JQStoreManagedJobTableMap
