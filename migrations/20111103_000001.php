<?php
class Migration20111103_000001 extends Migration
{
    public function up()
    {
        $sql = <<<SQL
            ALTER TABLE jqstore_managed_job ADD COLUMN coalesce_id VARCHAR(100) DEFAULT NULL;
            CREATE UNIQUE INDEX idx_jqstore_managed_job_coalesce_id ON jqstore_managed_job(coalesce_id);
SQL;
        $this->migrator->getDbCon()->exec($sql);
    }
    public function down()
    {
        $sql = <<<SQL
            ALTER TABLE jqstore_managed_job DROP COLUMN coalesce_id;
SQL;
        $this->migrator->getDbCon()->exec($sql);
    }
    public function description()
    {
        return "Add jqstore_managed_job.coalesce_id";
    }
}
