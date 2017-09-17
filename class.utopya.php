<?php
class UtopyaRegexTurkish
{
    public function __construct($pattern)
    {
        $arananlar = array('/I/', '/İ/', '/Ş/', '/Ö/', '/Ü/', '/Ğ/', '/Ç/');
        $yeniler   = array('ı', 'i', 'ş', 'ö', 'ü', 'ğ', 'ç');
        ksort($arananlar);
        ksort($yeniler);
        $pattern   = preg_replace($arananlar, $yeniler, $pattern);
        $this->ret = strtolower($pattern);
        return $this->ret;
    }
    public function __toString()
    {
        return $this->ret;
    }
}
class dbOperate
{
    protected function isRegex($string)
    {
        $regex = "/^\/.+\/[a-z]*$/i";
        return preg_match($regex, $string);
    }
    protected function isJson($string)
    {
        return !empty($string) && is_string($string) && is_array(json_decode($string, true)) && json_last_error() == 0;
    }
    protected function translate($p)
    {
        return preg_replace("/[^[:alnum:]+]/i", "-", trim($p));
    }

    protected function error($error, $p = '')
    {
        file_put_contents($this->utopyaPath . "files/utopya_error_log", $error . ": " . $p . "\n", FILE_APPEND);
        file_put_contents($this->utopyaPath . "files/last_error_log", $error . ": " . $p);
    }
    public function lastError()
    {
        return file_get_contents($this->utopyaPath . "files/last_error_log");
    }
    protected function saveid($id, $schema)
    {
        $ids = json_decode(file_get_contents($this->dbPath . 'last-ids.json'), true);

        if (!$ids) {
            $ids = array();
        }
        $ids[$schema] = $id;
        file_put_contents($this->dbPath . 'last-ids.json', json_encode($ids));
    }
    public function restoreBD($name)
    {
        $zip = new ZipArchive;
        if ($zip->open($this->utopyaPath . $name) === true) {
            $zip->extractTo($this->utopyaPath);
            $zip->close();
            return true;
        } else {
            return false;
        }
    }
    public function backupDB()
    {
        if (!extension_loaded('zip')) {
            return false;
        }
        $zip = new ZipArchive();
        if (!$zip->open($this->utopyaPath . $this->db . "-" . date("d-m-Y-H-i-s") . ".zip", ZIPARCHIVE::CREATE)) {
            return false;
        }
        $source = str_replace('\\', '/', realpath($this->dbPath));
        if (is_dir($source) === true) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

            foreach ($files as $file) {
                $file = str_replace('\\', '/', $file);
                if (in_array(substr($file, strrpos($file, '/') + 1), array('.', '..'))) {
                    continue;
                }
                $file = realpath($file);
                if (is_dir($file) === true) {
                    $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                } else if (is_file($file) === true) {
                    $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                }
            }
        }
        return $zip->close();
    }
<<<<<<< HEAD
    public function findRegex($pattern, $input, $flags = 0, $e)
    {
        if ($e) {
            return array_merge(
                array_intersect_key($input, array_flip(preg_grep($pattern, array_keys($input), $flags))),
                preg_grep($pattern, $input, $flags)
            );
        } else {
            $input = new UtopyaRegexTurkish($input);
            return array_merge(
                array_intersect_key($input, array_flip(preg_grep($pattern, array_keys($input), $flags))),
                preg_grep($pattern, $input, $flags)
            );
        }
=======
    public function findRegex($pattern, $input, $flags = 0)
    {
        return array_merge(
            array_intersect_key(new UtopyaRegexTurkish($input), array_flip(preg_grep($pattern, array_keys(new UtopyaRegexTurkish($input)), $flags))),
            preg_grep($pattern, new UtopyaRegexTurkish($input), $flags)
        );
>>>>>>> 7ac10187b6a673e1670a4556ac54ec29f29fbbc1
    }
}
class Schemas extends dbOperate
{
    protected function schemaExist($schema)
    {
        $schemas = $this->schemas;
        if (in_array($schema, $schemas)) {
            return true;
        } else {
            return false;
        }
    }
    protected function updateSchemas()
    {
        $sch = array();
        foreach (glob($this->dbPath . "*", GLOB_ONLYDIR) as $dir) {
            $sch[] = basename($dir);
        }
        $this->schemas = $sch;
    }
    public function getSchemas()
    {
        return $this->schemas;
    }
    protected function lastID($schema)
    {
        $ids = json_decode(file_get_contents($this->dbPath . 'last-ids.json'), true);
        return $ids[$schema];
    }
    public function schema($schema)
    {
        if (!is_array($schema)) {
            $schema = $this->translate($schema);
            if ($this->schemaExist($schema)) {
                $this->schemaName = $schema;
                $this->schema     = $this->dbPath . $schema;
                return $this;
            } else {
                $this->createSchema($schema);
                return $this;
            }
        } else {
            $this->schema = $schema;
            return $this;
        }
    }
    public function createSchema($schema)
    {
        if ($this->schemaExist($schema)) {
            $this->error("Schema already exist", $schema);
            return false;
        } else {
            mkdir($this->dbPath . $schema);
            $this->updateSchemas();
            return $this->schema($schema);
        }
    }
    public function rename($newschemaname)
    {
        if (rename($this->schema, $this->dbPath . $newschemaname)) {
            return true;
        } else {
            $this->error("Schema not found", $this->schemaName);
            return false;
        }
    }
    public function drop()
    {
        try {
            $documents = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->schema, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($documents as $docInfo) {
                $todo = ($docInfo->isDir() ? 'rmdir' : 'unlink');
                $todo($docInfo->getRealPath());
            }
            rmdir($this->schema);
            return true;
        } catch (Exception $e) {
            $this->error("Schema not found", $this->schemaName);
        }
    }
    protected function documentExist($id)
    {
        if (file_exists($this->schema . "/" . $id . ".json")) {
            return true;
        } else {
            return false;
        }
    }
}

<<<<<<< HEAD
class Query extends Schemas
=======
class Index extends Schemas
{
    protected function indexes($schema)
    {
        return json_decode(file_get_contents($this->dbPath . $schema . "-indexes.json"), true);
    }
    public function index($row = false)
    {
        $indexes = $this->indexes($this->schemaName);
        if (!$indexes) {
            $indexes = array();
        }
        if (!$row) {
            $row = $indexes;
        }
        $files = $this->schema($this->schemaName)->findAll()->result();
        $old   = array();
        foreach ($row as $r1) {
            foreach ($files as $file) {
                if (is_array($file[$r1])) {
                    foreach ($file[$r1] as $r) {
                        $old[$r1][$r][] = $file["id"];
                    }
                } else {
                    $old[$r1][$file[$r1]][] = $file["id"];
                }
            }
        }
        $indexes = array_merge($indexes, $row);
        $indexes = array_unique($indexes);
        file_put_contents($this->dbPath . $this->schemaName . "-indexes.json", json_encode($indexes));
        file_put_contents($this->dbPath . $this->schemaName . "-index.json", json_encode($old));
    }
    protected function getIndex($index)
    {
        $in     = json_decode(file_get_contents($this->dbPath . $this->schemaName . "-index.json"), true);
        $return = array();
        foreach ($index as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $k) {
                    if (!$this->isRegex($k)) {
                        foreach ($in[$key][$k] as $w) {
                            $return[] = $this->schema . "/" . $w . ".json";
                        }
                    } else {
                        foreach ($this->findRegex($k, array_keys($in[$key])) as $ret) {
                            $return[] = $this->schema . "/" . $in[$key][$ret][0] . ".json";
                        }
                    }
                }

            } else {
                foreach ($in[$key][$value] as $w) {
                    $return[] = $this->schema . "/" . $w . ".json";
                }
            }
        }
        return $return;
    }
}

class Query extends Index
>>>>>>> 7ac10187b6a673e1670a4556ac54ec29f29fbbc1
{
    private function operators($op, $key)
    {
        $vals = array_values($op)[0];
        $val  = '';
        switch (array_keys($op)[0]) {
<<<<<<< HEAD
            case '$hasKey':
                $val .= '(isset($data["' . $key . '"]' . ')) && ';
                break;
            case '$in':
                if (!$this->isRegex($vals)) {
                    if (is_array($vals)) {
                        foreach ($vals as $value) {
                            $val .= '(in_array("' . $value . '", $data["' . $key . '"])) || ';
                        }
                    } else {
                        $val .= '(in_array("' . $vals . '", $data["' . $key . '"])) && ';
                    }
                } else {
                    foreach ($vals as $value) {
                        $val .= '($this->findRegex("' . $value . '", $data["' . $key . '"])) || ';
=======
            case '$in':
                if (is_array($vals)) {
                    foreach ($vals as $value) {
                        if (!$this->isRegex($value)) {
                            $val .= '(array_search("' . $value . '", $data["' . $key . '"]) !== false) && (array_search("' . $value . '", $data["' . $key . '"]) !== null) || ';
                        } else {
                            $val .= '($this->findRegex("' . $value . '", $data["' . $key . '"])) || ';
                        }

                    }
                } else {
                    if (!$this->isRegex($vals)) {
                        $val .= '(array_search("' . $vals . '", $data["' . $key . '"]) !== false) && (array_search("' . $vals . '", $data["' . $key . '"]) !== null)';
                    } else {
                        $val .= '($this->findRegex("' . $vals . '", $data["' . $key . '"]))';
>>>>>>> 7ac10187b6a673e1670a4556ac54ec29f29fbbc1
                    }
                }
                $val = rtrim($val, ' || ');
                break;
            case '$or':
                if (is_array($vals)) {
                    foreach ($vals as $value) {
                        if (!$this->isRegex($value)) {
                            $val .= '($data["' . $key . '"] == "' . $value . '") || ';
                        } else {
                            $val .= '(preg_match("' . $value . '", new UtopyaRegexTurkish($data["' . $key . '"]))) || ';
                        }
                    }
                } else {
                    if (!$this->isRegex($vals)) {
                        $val .= '($data["' . $key . '"] == "' . $vals . '") || ';
                    } else {
                        $val .= '(preg_match("' . $vals . '", new UtopyaRegexTurkish($data["' . $key . '"]))) || ';
                    }
                }
                $val = rtrim($val, ' || ');
                break;
        }
        return $val;
    }
<<<<<<< HEAD
    protected function Condition($params)
    {
        $val = '';
        foreach (array_keys($params) as $key) {
            $value = $params[$key];
            $key   = (strstr($key, ".")) ? str_replace(".", '"]["', $key) : $key;
            if (is_array($value)) {
                $val .= $this->operators($value, $key);
                if ($val === '') {
                    foreach ($value as $w) {
                        if (!$this->isRegex($w)) {
                            $val .= '($data["' . $key . '"] == "' . $w . '") && ';
                        } else {
                            $val .= '(preg_match("' . $w . '", new UtopyaRegexTurkish($data["' . $key . '"]))) && ';
=======
    private function Condition($params, $type)
    {
        $types = array(
            null  => "&&",
            "or"  => "||",
            "and" => "&&",
        );
        $val = '';
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $val .= $this->operators($value, $key);
            } else if (strstr($key, '$in.')) {
                if (!is_array($value)) {
                    if (!$this->isRegex($value)) {
                        $val .= '(array_search("' . $value . '", $data["' . explode('$in.', $key)[1] . '"]) !== false) && (array_search("' . $value . '", $data["' . explode('$in.', $key)[1] . '"]) !== null) ' . $types[$type] . ' ';
                    } else {
                        $val .= '($this->findRegex("' . $value . '", $data["' . explode('$in.', $key)[1] . '"])) ' . $types[$type] . ' ';
                    }
                } else {
                    foreach ($value as $w) {
                        if (strstr($key, '$in.')) {
                            $types[$type] = "||";
                            if (!$this->isRegex($w)) {
                                $val .= '(array_search("' . $w . '", $data["' . explode('$in.', $key)[1] . '"]) !== false) && (array_search("' . $w . '", $data["' . explode('$in.', $key)[1] . '"]) !== null) ' . $types[$type] . ' ';
                            } else {
                                $val .= '($this->findRegex("' . $w . '", $data["' . explode('$in.', $key)[1] . '"])) ' . $types[$type] . ' ';
                            }
>>>>>>> 7ac10187b6a673e1670a4556ac54ec29f29fbbc1
                        }
                    }
                }
            } else {
<<<<<<< HEAD
                if (!$this->isRegex($value)) {
                    $val .= '($data["' . $key . '"] == "' . $value . '") && ';
                } else {
                    $val .= '(preg_match("' . $value . '", new UtopyaRegexTurkish($data["' . $key . '"]))) && ';
                }
            }

        }
        $val = rtrim($val, ' && ');
        return rtrim($val);

    }
    public function query($params)
    {
        if (!is_callable($params)) {
            $condition   = $this->Condition($params);
            $is_callable = false;
        } else {
            $is_callable = true;
        }
        $match = array();
        if (!is_array($this->schema)) {
            $files = glob($this->schema . "/*");
            natsort($files);
            foreach ($files as $file) {
                $fileContents = file_get_contents($file);
                $data         = json_decode($fileContents, true);
                if ($is_callable) {
                    if ($params($data)) {
                        array_push($match, $data);
                    }
                } else {
                    if (eval("return $condition;")) {
                        array_push($match, $data);
                    }
=======
                if (!is_array($value)) {
                    if (strstr($key, ".")) {
                        $key = str_replace(".", '"]["', $key);

                        if (!$this->isRegex($value)) {
                            $val .= '(array_search("' . $value . '", $data["' . $key . '"]) !== false) && (array_search("' . $value . '", $data["' . $key . '"]) !== null) ' . $types[$type] . ' ';
                        } else {
                            $val .= '(preg_match("' . $value . '", new UtopyaRegexTurkish($data["' . $key . '"]))) ' . $types[$type] . ' ';

                        }
                    } else {
                        if (!$this->isRegex($value)) {
                            $val .= '($data["' . $key . '"] == "' . $value . '") ' . $types[$type] . ' ';
                        } else {
                            $val .= '(preg_match("' . $value . '", new UtopyaRegexTurkish($data["' . $key . '"]))) ' . $types[$type] . ' ';
                        }
                    }
                } else {
                    foreach ($value as $w) {
                        if (!$this->isRegex($w)) {
                            $val .= '($data["' . $key . '"] == "' . $w . '") ' . $types[$type] . ' ';
                        } else {
                            $val .= '(preg_match("' . $w . '", new UtopyaRegexTurkish($data["' . $key . '"]))) ' . $types[$type] . ' ';
                        }
                    }
                }
            }
        }
        return rtrim($val, $types[$type] . ' ');
    }
    private function query($params, $type)
    {
        $condition = $this->Condition($params, $type);
        if (!is_array($this->schema)) {
            $indexes = $this->indexes($this->schemaName);
            foreach ($params as $key => $value) {
                $isindex  = $key;
                $getindex = array();
                if (!is_array($value)) {
                    if (in_array($isindex, $indexes)) {
                        $getindex[$isindex][] = $value;
                    }
                } else {
                    foreach ($value as $w) {
                        if (in_array($isindex, $indexes)) {
                            $getindex[$isindex][] = $w;
                        }
                    }
                }
            }
            $files = $this->getIndex($getindex);
            if (count($files) === 0) {
                $files = glob($this->schema . "/*");
            }
            natsort($files);
            $match = array();
            foreach ($files as $file) {
                $fileContents = file_get_contents($file);
                $data         = json_decode($fileContents, true);
                if (eval("return $condition;")) {
                    array_push($match, $data);
>>>>>>> 7ac10187b6a673e1670a4556ac54ec29f29fbbc1
                }
            }
        } else {
            $fileContents = $this->schema;
<<<<<<< HEAD
            foreach ($fileContents as $data) {
                if ($is_callable) {
                    if ($params($data)) {
                        array_push($match, $data);
                    }
                } else {
                    if (eval("return $condition;")) {
                        array_push($match, $data);
                    }
=======
            $match        = array();
            foreach ($fileContents as $data) {
                if (eval("return $condition;")) {
                    $data["id"] = (string) $data["id"];
                    array_push($match, $data);
>>>>>>> 7ac10187b6a673e1670a4556ac54ec29f29fbbc1
                }
            }
        }

        if (!$match) {
            return false;
        } else {
            return $match;
        }
    }
    public function find($param, $type = null)
    {
        $documents = $this->query($param, $type);
        if (!$documents) {
            $this->error("Document not found", $this->schemaName . " " . print_r($param, true));
        }
        return new Result($documents);
    }
    public function findAll($skip = 0, $limit = null)
    {
        if (!is_array($this->schema)) {
            $datas = glob($this->schema . "/*");
            natsort($datas);
            $datas = array_reverse($datas);
            $data  = array();
            $limit = (!$l) ? $s : $l;
            $skip  = ($l) ? $s : 0;
            $datas = array_slice($datas, $skip, $limit);
            foreach ($datas as $file) {
                $file = json_decode(file_get_contents($file), true);
                array_push($data, $file);
            }
        } else {
            $data = $this->schema;
        }
        return new Result($data);
    }
    public function insert($params)
    {
        if (!$this->schemaExist($this->schemaName)) {
            $this->createSchema($this->schemaName) or die(lastError());
        }
        $params     = (!is_array($params)) ? json_decode($params) : (object) $params;
        $lastid     = $this->lastID($this->schemaName) + 1;
        $params->id = (string) $lastid;
        try {
            file_put_contents($this->schema . "/" . $lastid . ".json", json_encode($params));
            $this->saveid($lastid, $this->schemaName);
            $this->index();
            return (array) $params;
        } catch (Exception $e) {
            $this->error($e, $this->schemaName);
            return false;
        }

    }
    public function remove($query)
    {
        $file     = false;
        $document = false;
        if (is_numeric($query)) {
            if (!$this->documentExist($query)) {
                $this->error("Document not found", $this->schemaName . " (id=$query)");
                return false;
            }
            $file       = $this->schema . "/" . $query . ".json";
            $document[] = json_decode(file_get_contents($file), true);
        } else {
            $document = $this->query($query);
        }
        if (!$document) {
            $this->error("Documents not found", $this->schemaName . " " . print_r($query, true));
            return false;
        }
        foreach ($document as $d) {
            $file = ($file) ? $file : $this->schema . "/" . $d["id"] . ".json";
            try {
                unlink($file);
                $this->index();
                return true;
            } catch (Exception $e) {
                $this->error($e, $this->schemaName);
                return false;
            }
        }
    }
    public function findOne($id)
    {
        if (!$this->documentExist($id)) {
            $this->error("Document not found", $this->schemaName . " (id=$id)");
            return false;
        }
        $document = json_decode(file_get_contents($this->schema . "/" . $id . ".json"), true);
        return $document;
    }
    public function update($query, $params)
    {
        $file     = false;
        $document = false;
        if (is_numeric($query)) {
            if (!$this->documentExist($query)) {
                $this->error("Document not found", $this->schemaName . " (id=$query)");
                return false;
            }
            $file = $this->schema . "/" . $query . ".json";

            $document[] = json_decode(file_get_contents($file), true);
        } else {
            $document = $this->query($query);
        }
        if (!$document) {
            $this->error("Documents not found", $this->schemaName . " " . print_r($query, true));
            return false;
        }
        $return = array();
        foreach ($document as $d) {
            $params = (!is_array($params)) ? json_decode($params) : (object) $params;
            foreach ($params as $key => $value) {
                $d[$key] = $value;
            }
            $return[] = $d;
            $file     = ($file) ? $file : $this->schema . "/" . $d["id"] . ".json";
            try {
                file_put_contents($file, json_encode($d));
                $this->index();
                return $return;
            } catch (Exception $e) {
                $this->error($e, $this->schemaName);
                return false;
            }
        }
    }
}

class Result
{
    public function __construct($data)
    {
        $this->skip   = 0;
        $this->limit  = null;
        $this->result = $data;
        return $this;
    }
    public function limit($s, $l)
    {
        $limit        = (!$l) ? $s : $l;
        $skip         = ($l) ? $s : 0;
        $this->result = array_slice($this->result, $skip, $limit);
        return $this;
    }
    public function sort($p)
    {
        usort($this->result, $p);
        return $this;
    }
    public function result()
    {
        return $this->result;
    }
    public function reverse()
    {
        $this->result = array_reverse($this->result);
        return $this;
    }
    public function count()
    {
        if ($this->result) {
            return count($this->result);
        } else {
            return 0;
        }
    }
}
class UtopyaDB extends Query
{
    public function __construct($db)
    {
        $this->utopyaPath = realpath(dirname(__FILE__)) . "/";
        if (file_exists($this->utopyaPath . $db)) {
            $this->db     = $db;
            $this->dbPath = $this->utopyaPath . $db . "/";
            $this->updateSchemas();
            return $this;
        } else {
            $this->createDB($db);
        }
    }
    public function createDB($db)
    {
        mkdir($this->utopyaPath . $db);
        $this->db     = $db;
        $this->dbPath = $this->utopyaPath . $db . "/";
        $this->updateSchemas();
        return $this;
    }
}
