#!/usr/local/bin/php
<?php
require_once("dirtys_messages.inc");
require_once("util.inc");

foreach ($dirtys_messages as $dirty => $message)
{
  if (is_subsystem_dirty($dirty))
  {
      echo "\n", $message;
      clear_subsystem_dirty($dirty);
  }
}
?>

