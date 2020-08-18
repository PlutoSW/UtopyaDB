# UtopyaDB
File based Database with Php

## Connection
```
$db = new UtopyaDB("fuuyemek");
```

## Backup / Restore

#### Backup

```
$db->backupDB();
```

#### Restore

```
$db->restoreBD("file.zip");
```

## Schema Operations

#### Create Schema (Create Table)

```
$db->createSchema("Schema Name");
```

#### Select Schema (Select Table)

```
$db->schema("Schema Name");
```

#### Rename Schema (Rename Table)

```
$db->schema("Schema Name")->rename("New Schema Name");
```

#### Drop Schema (Drop Table)

```
$db->schema("Schema Name")->drop();
```


## Query

#### Insert
```
$db->schema("Schema Name")->insert(array("name"=>"Jhon", "lastname"=>"Doe", "contact"=>array("phone"=>"111")));
```
#### Find
##### Functional
```

$query = function($data){
return $data["contact"]["phone"] == "111";
}
$db->schema("Schema Name")->find($query)->limit(0,10)->result();
```

##### Array Query
```
$query = array("name"=> "jhone");
$db->schema("Schema Name")->insert($query);
$db->schema("Schema Name")->find($query)->limit(0,10)->result();
```

##### Recursive
```
$query = array("contact.phone"=> "111");
$db->schema("Schema Name")->insert($query);
$db->schema("Schema Name")->find($query)->limit(0,10)->result();
```
##### $or operator
```
$query = array("name"=>array('$or'=>array("jhon","jane")));
$db->schema("Schema Name")->find($query)->limit(0,10)->result();
```
##### $in operator
```
$query = array("hobbies"=>array('$in'=>array("Sport","Music")));
$db->schema("Schema Name")->find($query)->limit(0,10)->result();
```
