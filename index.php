<?php
header('Content-Type: application/json; charset=utf-8');
include 'class.utopya.php';
$db    = new UtopyaDB("demodatabase");
$db->createSchema("demoschema");
$db->schema("demoschema")->insert(array("name"=>"Demo", "content"=>array("democontent"=>"Hehehehe")));
