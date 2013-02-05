<?php
class Migration20121224_225329 extends Migration
{
    public function up()
    {
        $sql = <<<SQL
            ALTER TABLE jqstore_managed_job ADD COLUMN work_factor integer DEFAULT NULL;
SQL;
        $this->migrator->getDbCon()->exec($sql);
    }
    public function down()
    {
        $sql = <<<SQL
            ALTER TABLE jqstore_managed_job DROP COLUMN work_factor;
SQL;
        $this->migrator->getDbCon()->exec($sql);
    }
    public function description()
    {
        return "Add jqstore_managed_job.work_factor.";
    }
}
