# UtopyaDB
File based Database with Php

#### Demo Project
http://fuuyemek.com


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

## Index

```
$db->schema("Schema Name")->index(array("name","time"));
```

## Query

```
$db->schema("Schema Name")->find(array("name"=>"freedom"))->limit(0,10)->result();
```
