<?php

require_once dirname(__FILE__) . '/../../JQJobs.php';

ini_set('include_path', 
    dirname(__FILE__) . "/../../externals/pear/php"
    . ":" . dirname(__FILE__) . "/../../lib/propel"
);
require_once 'propel/Propel.php';
Propel::init(dirname(__FILE__) . "/../../lib/propel/jqjobs-conf.php");

function getTestJQStore()
{
    return new JQStore_Propel('JQStoreManagedJob', Propel::getConnection(JQStoreManagedJobPeer::DATABASE_NAME));
}
