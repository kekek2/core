<?php

namespace OPNsense\Core;

use \Phalcon\Db\Adapter\Pdo\Sqlite;
use \Phalcon\Db\Column;

class Notices
{
    private $NOTICE_PATH = ["dbname" => "/var/db/notices.db"];
    private $db = null;

    public function __construct()
    {
        $this->db = new Sqlite($this->NOTICE_PATH);
        if (!$this->db->tableExists("notices")) {
            $this->db->createTable("notices", null, [
                'columns' => [
                    new Column("datetime", [
                        'type' => Column::TYPE_TEXT,
                        'notNull' => true,
                    ]),
                    new Column("message", [
                        'type' => Column::TYPE_TEXT,
                        'notNull' => true,
                    ]),
                ]
            ]);
            $this->db->execute("CREATE VIEW notices_insert (message) AS SELECT message FROM notices");
            $this->db->execute("CREATE TRIGGER notices_insert_grigger INSTEAD OF INSERT on notices_insert BEGIN INSERT INTO notices (datetime, message) VALUES (CAST((julianday('now') - 2440587.5)*86400 AS FLOAT), NEW.message); end");
        }
    }

    public function addNotice($message)
    {
        $this->db->execute("INSERT INTO notices_insert (message) VALUES ('$message')");
    }

    public function getNotices()
    {
        return $this->db->fetchAll("SELECT * FROM notices");
    }

    public function delNotice($datetime)
    {
        if ($datetime == 'all') {
            $this->db->delete("notices");
        } else {
            $this->db->delete("notices", "datetime = $datetime");
        }
    }
}