<?php

class MigrateClean
{
    public function clean($migrator)
    {
        // hard-reset your app to a clean slate
        $sql = <<<SQL
DROP TABLE IF EXISTS jqstore_managed_job cascade;
SQL;
        $migrator->getDbCon()->exec($sql);
    }
}
