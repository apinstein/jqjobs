<?php
class Migration20130204_000001 extends Migration
{
    public function up()
    {
        $sql = <<<SQL
            ALTER TABLE jqstore_managed_job ADD COLUMN max_runtime_seconds int DEFAULT NULL;
SQL;
        $this->migrator->getDbCon()->exec($sql);
    }
    public function down()
    {
        $sql = <<<SQL
            ALTER TABLE jqstore_managed_job DROP COLUMN max_runtime_seconds;
SQL;
        $this->migrator->getDbCon()->exec($sql);
    }
    public function description()
    {
        return "Add jqstore_managed_job.max_runtime_seconds.";
    }
}
