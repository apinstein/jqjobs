<?php
class Migration20100205_000001 extends Migration
{
    public function up()
    {
        $sql = <<<END
CREATE TABLE "jqstore_managed_job"
(
    "job_id" SERIAL,
    "creation_dts" timestamptz,
    "start_dts" timestamptz,
    "end_dts" timestamptz,
    "status" text,
    "error_message" text,
    "queue_name" text,
    "job" text,
    "max_attempts" integer,
    "attempt_number" integer,
    "priority" integer,
    PRIMARY KEY ("job_id")
);
END;
        $this->migrator->getDbCon()->exec($sql);
    }
    public function down()
    {
        $sql = <<<END
DROP TABLE IF EXISTS jqstore_managed_job cascade;
END;
        $this->migrator->getDbCon()->exec($sql);
    }
    public function description()
    {
        return "Add jqstore_managed_job tables.";
    }
}
